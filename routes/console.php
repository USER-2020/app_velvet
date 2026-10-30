<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agent:maintain', function () {
    // Retain key/hash tombstones: an old retry must never create a new enrollment.
    \App\Models\Agent\AgentIdempotencyKey::where('expires_at', '<=', now())->whereNotNull('response')->update(['response' => null]);
    \App\Models\Agent\AgentCommand::where('state', 'pending')->where('expires_at', '<=', now())->update(['state' => 'expired']);
    $expiredSessions = app(\App\Services\Agent\AgentWorkSessionService::class)->expireStale();
    \App\Models\Agent\AgentStation::whereNull('revoked_at')->whereNull('offline_at')->whereNotNull('last_seen_at')->chunkById(100, function ($stations) {
        foreach ($stations as $station) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($station) {
                $station = \App\Models\Agent\AgentStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
                $seconds = max(5, (int) $station->configuration['heartbeatSeconds']) * 3 + 30;
                if (! $station->revoked_at && $station->last_seen_at?->lt(now()->subSeconds($seconds))) {
                    $station->update(['offline_at' => now()]);
                }
            });
        }
    });
    $this->info("Agent maintenance completed; expired work sessions: {$expiredSessions}.");
})->purpose('Expire Agent commands, remove replay secrets and mark offline stations');

\Illuminate\Support\Facades\Schedule::command('agent:maintain')->everyMinute()->withoutOverlapping();
