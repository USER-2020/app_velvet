<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\PlatformCatalog;
use App\Models\StudioAccount;
use App\Services\Platforms\PlatformAdapterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MasterOperationsController extends Controller
{
    public function platforms(Request $request): Response
    {
        $this->authorizePermission($request, 'platforms.view');
        return Inertia::render('Admin/Platforms/Index', [
            'platforms' => PlatformCatalog::query()->with('studioAccounts')->orderBy('name')->get()->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'adapter_key' => $item->adapter_key, 'is_active' => $item->is_active, 'accounts' => $item->studioAccounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'status' => $account->status, 'connection_mode' => $account->connection_mode, 'legal_holder' => $account->legal_holder, 'last_error' => $account->last_error, 'last_synced_at' => $account->last_synced_at?->toIso8601String()])])->values(),
            'models' => Candidate::query()->with('lead:id,first_name,last_name')->where('candidate_type', 'MODEL')->where('status', 'ACTIVE')->get(['id','code','lead_id'])->map(fn ($item) => ['id' => $item->id, 'name' => $item->lead?->full_name, 'code' => $item->code])->values(),
        ]);
    }

    public function storePlatform(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'platforms.manage');
        $data = $request->validate(['code' => ['required','string','max:50','alpha_dash','unique:platform_catalogs,code'], 'name' => ['required','string','max:120']]);
        PlatformCatalog::create([...$data, 'adapter_key' => 'simulation', 'is_active' => true]);
        return back()->with('success', 'Plataforma creada. Solo admite modos Manual o Simulado hasta contar con integracion oficial.');
    }

    public function storeStudioAccount(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'studio_accounts.manage');
        $data = $request->validate(['platform_catalog_id' => ['required','exists:platform_catalogs,id'], 'name' => ['required','string','max:120'], 'legal_holder' => ['nullable','string','max:180'], 'payment_receiver' => ['nullable','string','max:180'], 'connection_mode' => ['required', Rule::in(['MANUAL','SIMULATED'])], 'credentials' => ['nullable','array']]);
        StudioAccount::create([...collect($data)->except('credentials')->all(), 'status' => $data['connection_mode'] === 'SIMULATED' ? 'MANUAL' : 'UNCONFIGURED', 'encrypted_credentials' => ! empty($data['credentials']) ? Crypt::encryptString(json_encode($data['credentials'], JSON_THROW_ON_ERROR)) : null, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Cuenta Studio registrada sin habilitar ninguna conexion real.');
    }

    public function linkModelAccount(Request $request, StudioAccount $studioAccount): RedirectResponse
    {
        $this->authorizePermission($request, 'studio_accounts.manage');
        $data = $request->validate(['candidate_id' => ['required','exists:candidates,id'], 'account_identifier' => ['required','string','max:180'], 'ownership_type' => ['required', Rule::in(['STUDIO','MODEL','THIRD_PARTY'])], 'is_authorized' => ['required','boolean'], 'notes' => ['nullable','string','max:2000']]);
        $candidate = Candidate::findOrFail($data['candidate_id']);
        if ($candidate->candidate_type?->value !== 'MODEL') throw ValidationException::withMessages(['candidate_id' => 'Solo se pueden vincular cuentas a modelos.']);
        DB::table('model_platform_accounts')->updateOrInsert(['studio_account_id' => $studioAccount->id, 'account_identifier' => $data['account_identifier']], [...$data, 'studio_account_id' => $studioAccount->id, 'linked_at' => now(), 'linked_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]);
        return back()->with('success', 'Cuenta de plataforma vinculada de forma auditable.');
    }

    public function simulateSync(Request $request, StudioAccount $studioAccount, PlatformAdapterRegistry $registry): RedirectResponse
    {
        $this->authorizePermission($request, 'platform_simulations.run');
        if ($studioAccount->connection_mode !== 'SIMULATED') return back()->withErrors(['account' => 'La sincronizacion real esta desactivada. Cambia esta cuenta a modo Simulado para probarla.']);
        $runId = DB::table('platform_sync_runs')->insertGetId(['studio_account_id' => $studioAccount->id, 'mode' => 'SIMULATED', 'status' => 'RUNNING', 'started_by' => $request->user()->id, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        try {
            $payload = $registry->for($studioAccount->load('platform'))->simulate($studioAccount);
            DB::table('platform_sync_runs')->where('id', $runId)->update(['status' => 'COMPLETED', 'summary' => json_encode($payload), 'finished_at' => now(), 'updated_at' => now()]);
            $studioAccount->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            report($exception); DB::table('platform_sync_runs')->where('id', $runId)->update(['status' => 'ERROR', 'error_message' => $exception->getMessage(), 'finished_at' => now(), 'updated_at' => now()]); $studioAccount->update(['status' => 'ERROR', 'last_error' => 'Fallo en simulacion.']);
        }
        return back()->with('success', 'Simulacion ejecutada. No se contacto ningun proveedor externo.');
    }

    public function importManual(Request $request, StudioAccount $studioAccount): RedirectResponse
    {
        $this->authorizePermission($request, 'platform_imports.manage');
        $data = $request->validate(['content' => ['required','string','max:200000'], 'original_name' => ['nullable','string','max:255']]);
        $rows = collect(preg_split('/\r\n|\r|\n/', trim($data['content'])))->filter()->values();
        $headers = $rows->isNotEmpty() ? str_getcsv($rows->shift()) : [];
        $parsed = $rows->map(fn ($line) => array_combine($headers, array_pad(str_getcsv($line), count($headers), null)) ?: [])->values();
        DB::table('platform_import_batches')->insert(['studio_account_id' => $studioAccount->id, 'source' => 'CSV', 'status' => 'IMPORTED', 'original_name' => $data['original_name'] ?? 'manual.csv', 'rows_total' => $parsed->count(), 'rows_imported' => $parsed->count(), 'payload' => json_encode($parsed), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', "Importacion manual registrada con {$parsed->count()} filas. No se alteraron datos externos.");
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'sessions.manage');
        $data = $request->validate(['candidate_id' => ['required','exists:candidates,id'], 'operational_shift_id' => ['nullable','exists:operational_shifts,id'], 'started_at' => ['required','date'], 'source' => ['required', Rule::in(['MANUAL','SIMULATED','IMPORT'])], 'notes' => ['nullable','string','max:2000']]);
        DB::table('operational_sessions')->insert([...$data, 'status' => 'OPEN', 'opened_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Sesion operativa iniciada.');
    }

    public function storeStream(Request $request, int $session): RedirectResponse
    {
        $this->authorizePermission($request, 'streams.manage');
        $data = $request->validate(['model_platform_account_id' => ['nullable','exists:model_platform_accounts,id'], 'platform_name' => ['required','string','max:120'], 'started_at' => ['required','date'], 'data_origin' => ['required', Rule::in(['MANUAL','SIMULATED','IMPORT'])]]);
        DB::table('stream_sessions')->insert([...$data, 'operational_session_id' => $session, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Transmision registrada; puede coexistir con otras sin duplicar la duracion de la sesion.');
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'inventory.manage');
        $data = $request->validate(['asset_tag' => ['required','string','max:80','unique:inventory_assets,asset_tag'], 'name' => ['required','string','max:160'], 'category' => ['required','string','max:60'], 'branch_id' => ['nullable','exists:branches,id'], 'room_id' => ['nullable','exists:rooms,id']]);
        DB::table('inventory_assets')->insert([...$data, 'status' => 'AVAILABLE', 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Activo de inventario registrado.');
    }

    public function exportFinance(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'integrations.finance_export');
        $payload = ['generated_at' => now()->toIso8601String(), 'source' => 'VELVET_MASTER', 'sessions' => DB::table('operational_sessions')->count(), 'streams' => DB::table('stream_sessions')->count(), 'note' => 'Lote preparado; no se envio a una aplicacion externa.'];
        DB::table('finance_export_batches')->insert(['status' => 'PENDING', 'format' => 'JSON', 'payload' => json_encode($payload), 'checksum' => hash('sha256', json_encode($payload)), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Lote de integracion financiera preparado, sin enviar datos a Finanzas.');
    }

    public function productivity(Request $request): Response
    {
        $this->authorizePermission($request, 'reports.operational.view');

        $sessions = DB::table('operational_sessions as session')
            ->leftJoin('candidates as candidate', 'candidate.id', '=', 'session.candidate_id')
            ->leftJoin('leads as lead', 'lead.id', '=', 'candidate.lead_id')
            ->select('session.*', 'candidate.code', 'lead.first_name', 'lead.last_name')
            ->latest('session.started_at')
            ->limit(30)
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'model' => trim(($session->first_name ?? '').' '.($session->last_name ?? '')) ?: $session->code,
                'status' => $session->status,
                'source' => $session->source,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'duration_minutes' => $session->ended_at
                    ? max(0, \Carbon\Carbon::parse($session->started_at)->diffInMinutes(\Carbon\Carbon::parse($session->ended_at)))
                    : max(0, \Carbon\Carbon::parse($session->started_at)->diffInMinutes(now())),
            ]);

        return Inertia::render('Admin/Productivity/Index', [
            'models' => Candidate::query()->with('lead:id,first_name,last_name')->where('candidate_type', 'MODEL')->where('status', 'ACTIVE')->get(['id', 'code', 'lead_id'])->map(fn ($model) => ['id' => $model->id, 'name' => $model->lead?->full_name ?: $model->code])->values(),
            'sessions' => $sessions,
            'streams' => DB::table('stream_sessions')->latest('started_at')->limit(30)->get(),
            'metrics' => DB::table('production_metrics')->latest('recorded_at')->limit(30)->get(),
            'summary' => [
                'sessions_open' => DB::table('operational_sessions')->where('status', 'OPEN')->count(),
                'session_minutes' => $sessions->sum('duration_minutes'),
                'streams_open' => DB::table('stream_sessions')->where('status', 'OPEN')->count(),
            ],
        ]);
    }

    public function closeSession(Request $request, int $session): RedirectResponse
    {
        $this->authorizePermission($request, 'sessions.manage');
        DB::table('operational_sessions')->where('id', $session)->where('status', 'OPEN')->update(['status' => 'CLOSED', 'ended_at' => now(), 'closed_by' => $request->user()->id, 'updated_at' => now()]);
        return back()->with('success', 'Sesion cerrada. La duracion se calcula solo una vez por sesion, incluso con transmisiones simultaneas.');
    }

    public function closeStream(Request $request, int $stream): RedirectResponse
    {
        $this->authorizePermission($request, 'streams.manage');
        DB::table('stream_sessions')->where('id', $stream)->where('status', 'OPEN')->update(['status' => 'CLOSED', 'ended_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Transmision cerrada.');
    }

    public function storeMetric(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'productivity.manage');
        $data = $request->validate([
            'operational_session_id' => ['nullable', 'exists:operational_sessions,id'],
            'stream_session_id' => ['nullable', 'exists:stream_sessions,id'],
            'metric_key' => ['required', 'string', 'max:80'],
            'metric_value' => ['required', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'data_origin' => ['required', Rule::in(['MANUAL', 'SIMULATED', 'IMPORT'])],
            'recorded_at' => ['required', 'date'],
        ]);
        if (empty($data['operational_session_id']) && empty($data['stream_session_id'])) {
            throw ValidationException::withMessages(['operational_session_id' => 'Selecciona una sesion o una transmision para la metrica.']);
        }
        $data['origin'] = $data['data_origin'];
        unset($data['data_origin']);
        DB::table('production_metrics')->insert([...$data, 'is_validated' => false, 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Metrica registrada con su origen de datos.');
    }

    public function validateMetric(Request $request, int $metric): RedirectResponse
    {
        $user = $request->user();
        $isTeamManager = $user->hasPermissionTo('productivity.manage');
        abort_unless($isTeamManager || $user->hasPermissionTo('productivity.validate_assigned'), 403);

        if (! $isTeamManager) {
            $monitorId = Candidate::query()->where('user_id', $user->id)->value('id');
            $modelId = DB::table('production_metrics as metric')
                ->leftJoin('operational_sessions as direct_session', 'direct_session.id', '=', 'metric.operational_session_id')
                ->leftJoin('stream_sessions as stream', 'stream.id', '=', 'metric.stream_session_id')
                ->leftJoin('operational_sessions as stream_session', 'stream_session.id', '=', 'stream.operational_session_id')
                ->where('metric.id', $metric)
                ->value(DB::raw('COALESCE(direct_session.candidate_id, stream_session.candidate_id)'));
            abort_unless($monitorId && $modelId && DB::table('model_monitor_assignments')->where('monitor_candidate_id', $monitorId)->where('model_candidate_id', $modelId)->whereNull('ended_at')->exists(), 403);
        }

        DB::table('production_metrics')->where('id', $metric)->update(['is_validated' => true, 'validated_by' => $user->id, 'validated_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Metrica validada por monitor de forma auditable.');
    }

    public function administration(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo('branches.manage'), 403);
        return Inertia::render('Admin/Administration/Index', [
            'branches' => DB::table('branches')->orderBy('name')->get(),
            'models' => Candidate::query()->with('lead:id,first_name,last_name')->where('candidate_type', 'MODEL')->where('status', 'ACTIVE')->get(['id', 'code', 'lead_id'])->map(fn ($model) => ['id' => $model->id, 'name' => $model->lead?->full_name ?: $model->code])->values(),
            'assets' => DB::table('inventory_assets as asset')->leftJoin('branches as branch', 'branch.id', '=', 'asset.branch_id')->select('asset.*', 'branch.name as branch_name')->latest('asset.created_at')->limit(50)->get(),
            'maintenance' => DB::table('maintenance_records as record')->leftJoin('inventory_assets as asset', 'asset.id', '=', 'record.inventory_asset_id')->select('record.*', 'asset.name as asset_name')->latest('record.created_at')->limit(50)->get(),
            'reviews' => DB::table('performance_reviews')->latest('reviewed_on')->limit(50)->get(),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('branches.manage'), 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:branches,code'], 'name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255']]);
        DB::table('branches')->insert([...$data, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Sede registrada.');
    }

    public function storeMaintenance(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'maintenance.manage');
        $data = $request->validate(['inventory_asset_id' => ['required', 'exists:inventory_assets,id'], 'kind' => ['required', Rule::in(['PREVENTIVE', 'CORRECTIVE'])], 'description' => ['required', 'string', 'max:2000'], 'scheduled_for' => ['nullable', 'date']]);
        $data['scheduled_at'] = $data['scheduled_for'] ?? null;
        unset($data['scheduled_for']);
        DB::table('maintenance_records')->insert([...$data, 'status' => 'SCHEDULED', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Mantenimiento registrado.');
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'performance_reviews.manage');
        $data = $request->validate(['candidate_id' => ['required', 'exists:candidates,id'], 'score' => ['required', 'integer', 'min:0', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000'], 'reviewed_at' => ['required', 'date']]);
        DB::table('performance_reviews')->insert(['candidate_id' => $data['candidate_id'], 'score' => $data['score'], 'summary' => $data['notes'] ?? '', 'reviewed_on' => $data['reviewed_at'], 'reviewed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Evaluacion de desempeno registrada.');
    }

    private function authorizePermission(Request $request, string $permission): void { abort_unless($request->user()->hasPermissionTo($permission), 403); }
}
