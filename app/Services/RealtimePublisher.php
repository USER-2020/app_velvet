<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Candidate;
use App\Models\Interview;
use Illuminate\Support\Facades\Http;
use App\Services\NotificationService;

class RealtimePublisher
{
    public function publishLead(Lead $lead, string $event, array $extra = []): void
    {
        $lead->loadMissing('candidate');
        $this->publish($event, array_merge([
            'lead_id' => $lead->id,
            'candidate_id' => $lead->candidate?->id,
            'name' => $lead->full_name,
            'code' => $lead->candidate?->code ?: $lead->code,
            'status' => $lead->candidate?->status?->value ?: $lead->status?->value,
            'updated_at' => now()->toIso8601String(),
        ], $extra));
    }

    public function publish(string $event, array $data = []): void
    {
        $channel = $data['channel'] ?? $this->channelFor($event);
        if (isset($data['notification'])) {
            app(NotificationService::class)->createForRoles(
                $channel,
                $event,
                $data['notification'],
                collect($data)->except('notification')->all(),
                $data['notification_roles'] ?? [],
            );
        }
        $apiKey = config('services.ably.api_key');
        if (! $apiKey || ! str_contains($apiKey, ':')) return;
        [$keyName, $keySecret] = explode(':', $apiKey, 2);

        try {
            $response = Http::withBasicAuth($keyName, $keySecret)->post(
                'https://rest.ably.io/channels/' . rawurlencode($channel) . '/messages',
                ['name' => $event, 'data' => json_encode(array_merge(['counters' => $this->counters()], $data))]
            );
            if (! $response->successful()) {
                logger()->error('Ably rechazó la publicación realtime.', [
                    'event' => $event,
                    'channel' => $channel,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function counters(): array
    {
        // Leads y candidatos comparten el contador de pendientes de gestión.
        // Un lead convertido no se duplica: pasa a contarse como candidato
        // hasta que entra a entrevista o a una etapa posterior.
        $pendingLeads = Lead::whereIn('status', ['NEW', 'CONTACTED', 'QUALIFIED', 'UNRESPONSIVE'])
            ->whereDoesntHave('candidate')
            ->count();
        $pendingCandidates = Candidate::whereIn('status', ['NEW', 'CONTACTED', 'PREQUALIFIED'])->count();

        return [
            'leads' => $pendingLeads,
            'candidates' => $pendingCandidates,
            'interviews' => Interview::whereIn('status', ['INVITED', 'SCHEDULED'])->count(),
        ];
    }

    public function channelFor(string $event): string
    {
        if (str_starts_with($event, 'room.')) return config('services.ably.channels.rooms');
        if (str_starts_with($event, 'operation.')) return config('services.ably.channels.rooms');
        if (str_starts_with($event, 'interview.')) return config('services.ably.channels.interviews');
        if (in_array($event, ['candidate.identity_updated', 'candidate.prequalification_sent', 'candidate.prequalification_completed'], true)) {
            return config('services.ably.channels.onboarding');
        }

        return config('services.ably.channels.leads');
    }

    public function candidateDocumentsChannel(Candidate $candidate): string
    {
        return 'candidate-documents-' . $candidate->getKey();
    }
}

