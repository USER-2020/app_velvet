<?php

namespace App\Http\Controllers;

use App\Models\LeadDocument;
use App\Models\Lead;
use App\Services\AuditLogger;
use App\Services\ContractingInvitationService;
use App\Services\RealtimePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LeadDocument::query()->with(['lead.candidate.user']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('lead', fn ($lead) => $lead->where(fn ($leadQuery) => $leadQuery
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")));
        }

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('status')->upper()->value()) {
            $query->where('status', $status);
        }

        $statsQuery = clone $query;
        $documentRows = $query->latest()->get();
        $people = $documentRows->groupBy('lead_id')->map(fn ($personDocuments, $leadId) => [
            'id' => (int) $leadId,
            'person' => $this->payload($personDocuments->first())['person'],
            'documents' => $personDocuments->map(fn (LeadDocument $document) => $this->payload($document))->values()->all(),
        ])->values();

        return Inertia::render('Admin/Documents/Index', [
            'documents' => ['data' => $people],
            'filters' => $request->only('search', 'type', 'status'),
            'types' => LeadDocument::query()->select('type')->distinct()->orderBy('type')->pluck('type')->values(),
            'stats' => [
                'total' => $statsQuery->count(),
                'people' => $statsQuery->clone()->distinct('lead_id')->count('lead_id'),
                'pending' => $statsQuery->clone()->where('status', 'PENDING')->count(),
            ],
            'canManage' => $request->user()->hasPermissionTo('documents.manage'),
        ]);
    }

    public function preview(LeadDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        app(AuditLogger::class)->record('documents.previewed', $document, ['lead_id' => $document->lead_id, 'type' => $document->type]);

        return response()->file(Storage::disk('local')->path($document->path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"',
        ]);
    }

    public function download(LeadDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        app(AuditLogger::class)->record('documents.downloaded', $document, ['lead_id' => $document->lead_id, 'type' => $document->type]);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    public function updateStatus(Request $request, LeadDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['PENDING', 'VERIFIED', 'REJECTED'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $document->update([
            'status' => $data['status'],
            'review_note' => $data['status'] === 'REJECTED' ? $data['review_note'] : null,
        ]);
        app(AuditLogger::class)->record('documents.status_updated', $document, ['status' => $data['status'], 'has_review_note' => filled($data['review_note'] ?? null)]);
        $this->sendContractingInvitationIfReady($document, $request);

        return back()->with('success', 'Estado del documento actualizado correctamente.');
    }

    public function updatePersonStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['VERIFIED', 'REJECTED'])],
        ]);

        $updated = $lead->documents()->update(['status' => $data['status']]);
        $candidate = $lead->candidate()->with('lead')->first();
        if ($candidate) $this->sendContractingInvitationIfReady($candidate->lead->documents()->latest()->first(), $request);

        return back()->with('success', $updated . ' documentos actualizados correctamente.');
    }

    private function sendContractingInvitationIfReady(?LeadDocument $document, Request $request): void
    {
        $candidate = $document?->lead?->candidate;
        if (! $candidate || $candidate->status->value !== 'WAITING') return;

        $required = ['identity_document', 'bank_certificate', 'tax_document'];
        $latestByRequirement = $candidate->lead->documents()
            ->where('type', 'contract_document')
            ->whereNotNull('metadata')
            ->get()
            ->filter(fn (LeadDocument $item) => filled(data_get($item->metadata, 'requirement_key')))
            ->sortByDesc('id')
            ->unique(fn (LeadDocument $item) => data_get($item->metadata, 'requirement_key'))
            ->keyBy(fn (LeadDocument $item) => data_get($item->metadata, 'requirement_key'));

        $ready = collect($required)->every(fn (string $key) => $latestByRequirement->get($key)?->status === 'VERIFIED');
        if (! $ready) return;

        try {
            $invitation = app(ContractingInvitationService::class)->send($candidate, $request->user());
            if ($invitation) {
                app(RealtimePublisher::class)->publishLead($candidate->fresh('lead')->lead, 'candidate.contracting_invited', [
                    'candidate_id' => $candidate->id,
                    'invitation_id' => $invitation->id,
                    'notification' => ['title' => 'Agenda de contratación enviada', 'description' => "Se enviaron horarios de contratación a {$candidate->lead->full_name}."],
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function payload(LeadDocument $document): array
    {
        $candidate = $document->lead?->candidate;

        return [
            'id' => $document->id,
            'name' => $document->original_name,
            'type' => $document->type,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'status' => $document->status,
            'review_note' => $document->review_note,
            'created_at' => $document->created_at?->toIso8601String(),
            'preview_url' => route('admin.documents.preview', $document),
            'download_url' => route('admin.documents.download', $document),
            'person' => [
                'id' => $document->lead_id,
                'name' => $document->lead?->full_name,
                'email' => $document->lead?->email,
                'code' => $candidate?->code ?: $document->lead?->code,
                'type' => $candidate?->candidate_type?->value ?: $document->lead?->candidate_type?->value,
                'status' => $candidate?->status?->value ?: $document->lead?->status?->value,
                'user_active' => (bool) $candidate?->user?->is_active,
            ],
        ];
    }
}
