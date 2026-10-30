<?php

namespace App\Services\Agent;

use App\Models\Agent\AgentStation;
use App\Models\Agent\AgentWorkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentWorkSessionService
{
    public function start(AgentStation $station, int $userId): AgentWorkSession
    {
        return $this->createOrReuse($station, $userId);
    }

    public function startFromHeartbeat(AgentStation $station, int $userId, string $sessionId): AgentWorkSession
    {
        return $this->createOrReuse($station, $userId, $sessionId);
    }

    private function createOrReuse(AgentStation $station, int $userId, ?string $sessionId = null): AgentWorkSession
    {
        return DB::transaction(function () use ($station, $userId, $sessionId) {
            $active = AgentWorkSession::where('station_id', $station->id)->where('status', 'active')->lockForUpdate()->first();
            if ($active) {
                abort_unless($active->user_id === $userId, 409, 'The station already has an active work session.');

                return $active;
            }

            $now = now();

            return AgentWorkSession::create([
                'id' => $sessionId ?: (string) Str::uuid(),
                'user_id' => $userId,
                'station_id' => $station->id,
                'installation_id' => $station->installation_id,
                'studio_id' => $station->studio_id,
                'started_at' => $now,
                'last_heartbeat_at' => $now,
                'status' => 'active',
            ]);
        }, 3);
    }

    public function heartbeat(AgentWorkSession $session, array $obs, bool $active = true): void
    {
        $now = now();
        $last = $session->last_heartbeat_at ?: $session->started_at;
        $delta = (int) max(0, min($last->diffInSeconds($now), config('agent.session_timeout_seconds')));
        $streaming = (bool) ($obs['streaming'] ?? false);
        $streamingLast = $session->transmission_started_at && $session->transmission_started_at->greaterThan($last)
            ? $session->transmission_started_at
            : $last;
        $streamingDelta = (int) max(0, min($streamingLast->diffInSeconds($now), config('agent.session_timeout_seconds')));

        $session->total_connected_seconds += $active ? $delta : 0;
        $session->total_streaming_seconds += $active && $session->last_streaming ? $streamingDelta : 0;
        if ($streaming && ! $session->last_streaming) {
            $session->transmission_started_at = $now;
        } elseif (! $streaming && $session->last_streaming) {
            $session->transmission_started_at = null;
        }
        $session->last_heartbeat_at = $now;
        $session->last_streaming = $streaming;
        $session->last_obs = $obs;
        $session->last_obs_at = $now;
        $session->save();
    }

    public function end(AgentWorkSession $session, string $reason, string $status = 'ended', ?\DateTimeInterface $at = null): void
    {
        if ($session->status !== 'active') {
            return;
        }

        $endAt = $at ? now()->setTimestamp($at->getTimestamp()) : now();
        $last = $session->last_heartbeat_at ?: $session->started_at;
        $delta = (int) max(0, min($last->diffInSeconds($endAt), config('agent.session_timeout_seconds')));
        $streamingLast = $session->transmission_started_at && $session->transmission_started_at->greaterThan($last)
            ? $session->transmission_started_at
            : $last;
        $streamingDelta = (int) max(0, min($streamingLast->diffInSeconds($endAt), config('agent.session_timeout_seconds')));
        $session->total_connected_seconds += $delta;
        $session->total_streaming_seconds += $session->last_streaming ? $streamingDelta : 0;
        $session->transmission_started_at = null;
        $session->ended_at = $endAt;
        $session->end_reason = $reason;
        $session->status = $status;
        $session->save();
    }

    public function startTransmission(AgentWorkSession $session): AgentWorkSession
    {
        abort_if($session->status !== 'active', 409, 'Work session is not active.');
        if (! $session->last_streaming) {
            $session->last_streaming = true;
            $session->transmission_started_at = now();
            $session->last_obs = [...($session->last_obs ?: []), 'streaming' => true];
            $session->last_obs_at = now();
            $session->save();
        }
        return $session->fresh();
    }

    public function stopTransmission(AgentWorkSession $session): AgentWorkSession
    {
        abort_if($session->status !== 'active', 409, 'Work session is not active.');
        if ($session->last_streaming) {
            $now = now();
            $started = $session->transmission_started_at ?: $session->last_heartbeat_at ?: $session->started_at;
            $session->total_streaming_seconds += (int) max(0, min($started->diffInSeconds($now), config('agent.session_timeout_seconds')));
            $session->last_streaming = false;
            $session->transmission_started_at = null;
            $session->last_obs = [...($session->last_obs ?: []), 'streaming' => false];
            $session->last_obs_at = $now;
            $session->save();
        }
        return $session->fresh();
    }

    public function endForStation(int $stationId, string $reason, string $status = 'ended'): void
    {
        AgentWorkSession::where('station_id', $stationId)->where('status', 'active')->lockForUpdate()->get()
            ->each(fn (AgentWorkSession $session) => $this->end($session, $reason, $status));
    }

    public function expireStale(): int
    {
        $cutoff = now()->subSeconds(config('agent.session_timeout_seconds'));
        $count = 0;
        AgentWorkSession::where('status', 'active')->where('last_heartbeat_at', '<', $cutoff)->chunkById(100, function ($sessions) use (&$count) {
            foreach ($sessions as $session) {
                DB::transaction(function () use ($session, &$count) {
                    $current = AgentWorkSession::whereKey($session->id)->lockForUpdate()->first();
                    if ($current?->status === 'active' && $current->last_heartbeat_at?->lt(now()->subSeconds(config('agent.session_timeout_seconds')))) {
                        $this->end($current, 'timeout', 'expired', $current->last_heartbeat_at->copy()->addSeconds(config('agent.session_timeout_seconds')));
                        $count++;
                    }
                });
            }
        });

        return $count;
    }
}
