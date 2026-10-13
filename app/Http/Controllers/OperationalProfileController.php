<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatus;
use App\Enums\CandidateType;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\ModelPlatformAccount;
use App\Models\ModelMonitorAssignment;
use App\Models\OperationalTrainingRecord;
use App\Models\StudioAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationalProfileController extends Controller
{
    public function assignMonitor(Request $request, Candidate $model): RedirectResponse
    {
        $this->ensureActiveOperationalCandidate($model, CandidateType::MODEL, 'modelo');

        $data = $request->validate([
            'monitor_candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $model, $data): void {
            $model = Candidate::query()->lockForUpdate()->findOrFail($model->id);
            $monitor = Candidate::query()->lockForUpdate()->findOrFail($data['monitor_candidate_id']);
            $this->ensureActiveOperationalCandidate($model, CandidateType::MODEL, 'modelo');
            $this->ensureActiveOperationalCandidate($monitor, CandidateType::MONITOR, 'monitor');

            $current = ModelMonitorAssignment::query()
                ->where('model_candidate_id', $model->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->latest('assigned_at')
                ->first();

            if ($current?->monitor_candidate_id === $monitor->id) {
                throw ValidationException::withMessages([
                    'monitor_candidate_id' => 'La modelo ya está asignada a este monitor.',
                ]);
            }

            if ($current && empty($data['reason'])) {
                throw ValidationException::withMessages([
                    'reason' => 'Indica el motivo de la reasignación para conservar el historial operativo.',
                ]);
            }

            if ($current) {
                $current->update([
                    'ended_at' => now(),
                    'ended_by' => $request->user()->id,
                    'reason' => $data['reason'],
                ]);
            }

            $assignment = ModelMonitorAssignment::create([
                'model_candidate_id' => $model->id,
                'monitor_candidate_id' => $monitor->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ]);

            $model->load('lead');
            $monitor->load('lead');
            CandidateActivity::create([
                'candidate_id' => $model->id,
                'user_id' => $request->user()->id,
                'type' => $current ? 'monitor_reassigned' : 'monitor_assigned',
                'description' => $current
                    ? "Monitor reasignado a {$monitor->lead->full_name}."
                    : "Monitor asignado: {$monitor->lead->full_name}.",
                'metadata' => [
                    'assignment_id' => $assignment->id,
                    'previous_monitor_candidate_id' => $current?->monitor_candidate_id,
                    'monitor_candidate_id' => $monitor->id,
                    'reason' => $data['reason'] ?? null,
                ],
            ]);
        });

        return back()->with('success', 'Asignación de monitor actualizada y registrada en el historial.');
    }

    public function endMonitorAssignment(Request $request, Candidate $model, ModelMonitorAssignment $assignment): RedirectResponse
    {
        $this->ensureActiveOperationalCandidate($model, CandidateType::MODEL, 'modelo');
        abort_unless($assignment->model_candidate_id === $model->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $model, $assignment, $data): void {
            $assignment = ModelMonitorAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->model_candidate_id === $model->id, 404);

            if ($assignment->ended_at) {
                throw ValidationException::withMessages(['assignment' => 'Esta asignación ya fue finalizada.']);
            }

            $assignment->update([
                'ended_at' => now(),
                'ended_by' => $request->user()->id,
                'reason' => $data['reason'],
            ]);

            CandidateActivity::create([
                'candidate_id' => $model->id,
                'user_id' => $request->user()->id,
                'type' => 'monitor_unassigned',
                'description' => 'Asignación de monitor finalizada. Motivo: ' . $data['reason'],
                'metadata' => ['assignment_id' => $assignment->id, 'reason' => $data['reason']],
            ]);
        });

        return back()->with('success', 'La asignación se finalizó sin borrar su historial.');
    }

    public function storeTrainingRecord(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->ensureOperationalCandidate($candidate);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['ONBOARDING', 'TRAINING'])],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'is_required' => ['required', 'boolean'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = OperationalTrainingRecord::create([
            ...$data,
            'candidate_id' => $candidate->id,
            'status' => 'PENDING',
            'created_by' => $request->user()->id,
        ]);

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'operational_training_created',
            'description' => "Se registró {$record->kind} pendiente: {$record->title}.",
            'metadata' => ['training_record_id' => $record->id, 'kind' => $record->kind],
        ]);

        return back()->with('success', 'Registro de onboarding o capacitación creado.');
    }

    public function updateTrainingRecord(Request $request, Candidate $candidate, OperationalTrainingRecord $trainingRecord): RedirectResponse
    {
        $this->ensureOperationalCandidate($candidate);
        abort_unless($trainingRecord->candidate_id === $candidate->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['PENDING', 'IN_PROGRESS', 'COMPLETED', 'WAIVED'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $complete = in_array($data['status'], ['COMPLETED', 'WAIVED'], true);
        $trainingRecord->update([
            ...$data,
            'completed_at' => $complete ? now() : null,
            'completed_by' => $complete ? $request->user()->id : null,
        ]);

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'operational_training_updated',
            'description' => "{$trainingRecord->kind} {$trainingRecord->title} actualizado a {$trainingRecord->status}.",
            'metadata' => ['training_record_id' => $trainingRecord->id, 'status' => $trainingRecord->status],
        ]);

        return back()->with('success', 'Seguimiento operativo actualizado.');
    }

    public function linkAssignedPlatformAccount(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->ensureAssignedModelPlatformAccess($request, $candidate);

        $data = $request->validate([
            'studio_account_id' => ['required', 'integer', 'exists:studio_accounts,id'],
            'account_identifier' => ['required', 'string', 'max:180'],
            'ownership_type' => ['required', Rule::in(['STUDIO', 'MODEL', 'THIRD_PARTY'])],
            'is_authorized' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $studioAccount = StudioAccount::query()
            ->with('platform')
            ->whereKey($data['studio_account_id'])
            ->whereHas('platform', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $existing = ModelPlatformAccount::query()
            ->where('studio_account_id', $studioAccount->id)
            ->where('account_identifier', $data['account_identifier'])
            ->first();

        if ($existing && $existing->candidate_id !== $candidate->id) {
            throw ValidationException::withMessages([
                'account_identifier' => 'Esta cuenta ya está asociada a otra modelo.',
            ]);
        }

        ModelPlatformAccount::updateOrCreate(
            [
                'studio_account_id' => $studioAccount->id,
                'account_identifier' => $data['account_identifier'],
            ],
            [
                'candidate_id' => $candidate->id,
                'ownership_type' => $data['ownership_type'],
                'is_authorized' => $data['is_authorized'],
                'notes' => $data['notes'] ?? null,
                'status' => 'PENDING',
                'linked_at' => now(),
                'unlinked_at' => null,
                'linked_by' => $request->user()->id,
            ],
        );

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'platform_account_linked',
            'description' => "Cuenta {$studioAccount->name} asociada a la modelo.",
            'metadata' => [
                'studio_account_id' => $studioAccount->id,
                'platform' => $studioAccount->platform?->name,
                'account_identifier' => $data['account_identifier'],
                'ownership_type' => $data['ownership_type'],
            ],
        ]);

        return back()->with('success', 'Plataforma asociada a la modelo.');
    }

    public function unlinkAssignedPlatformAccount(Request $request, Candidate $candidate, ModelPlatformAccount $modelPlatformAccount): RedirectResponse
    {
        $this->ensureAssignedModelPlatformAccess($request, $candidate);
        abort_unless($modelPlatformAccount->candidate_id === $candidate->id, 404);

        $modelPlatformAccount->update(['unlinked_at' => now()]);

        CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'platform_account_unlinked',
            'description' => 'Cuenta de plataforma desvinculada de la modelo.',
            'metadata' => ['model_platform_account_id' => $modelPlatformAccount->id],
        ]);

        return back()->with('success', 'Plataforma desvinculada.');
    }

    private function ensureOperationalCandidate(Candidate $candidate): void
    {
        abort_unless(in_array($candidate->candidate_type, [CandidateType::MODEL, CandidateType::MONITOR], true), 422);
    }

    private function ensureActiveOperationalCandidate(Candidate $candidate, CandidateType $type, string $label): void
    {
        $this->ensureOperationalCandidate($candidate);

        if ($candidate->candidate_type !== $type || $candidate->status !== CandidateStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'candidate' => "El {$label} debe tener un perfil operativo activo.",
            ]);
        }
    }

    private function ensureAssignedModelPlatformAccess(Request $request, Candidate $candidate): void
    {
        abort_unless($candidate->candidate_type === CandidateType::MODEL, 404);
        abort_unless(Gate::forUser($request->user())->allows('viewAssignedModel', $candidate), 403);
    }
}
