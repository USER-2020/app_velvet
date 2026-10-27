<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent\AgentWorkSession;
use App\Services\Agent\AgentPayload;
use App\Services\Agent\AgentWorkSessionService;
use App\Services\Agent\Idempotency;
use Illuminate\Http\Request;

class AgentSessionController extends Controller
{
    public function start(Request $request, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        $data = AgentPayload::validate($request, [
            'installationId' => 'required|uuid',
            'stationId' => 'required|integer',
            'studioId' => 'required|string|max:100',
            'userId' => 'required|integer',
            'startedAt' => AgentPayload::time(true, true),
        ]);
        $station = $request->attributes->get('agentStation');
        $user = $request->attributes->get('agentUser');
        abort_unless($station && $station->id === (int) $data['stationId'], 403, 'Station mismatch.');
        abort_unless(strtolower($data['installationId']) === $station->installation_id && $data['studioId'] === $station->studio_id, 403, 'Station identity mismatch.');
        abort_unless($user && $user->id === (int) $data['userId'], 403, 'User mismatch.');

        return $idempotency->run($request, 'station:'.$station->id.':session-start', function () use ($sessions, $station, $user) {
            $session = $sessions->start($station, $user->id);

            return [$this->payload($session), 201];
        });
    }

    public function end(Request $request, string $sessionId, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        $data = AgentPayload::validate($request, ['reason' => 'required|in:logout,revoked,timeout,service_stopped,connection_lost']);
        $station = $request->attributes->get('agentStation');
        $user = $request->attributes->get('agentUser');

        return $idempotency->run($request, 'station:'.$station->id.':session-end:'.strtolower($sessionId), function () use ($sessionId, $data, $station, $user, $sessions) {
            $session = AgentWorkSession::whereKey(strtolower($sessionId))->where('station_id', $station->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $sessions->end($session, $data['reason']);

            return [$this->payload($session->fresh()), 200];
        });
    }

    public function transmissionStart(Request $request, string $sessionId, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        $station = $request->attributes->get('agentStation');
        $user = $request->attributes->get('agentUser');
        return $idempotency->run($request, 'station:'.$station->id.':transmission-start:'.strtolower($sessionId), function () use ($sessionId, $station, $user, $sessions) {
            $session = AgentWorkSession::whereKey(strtolower($sessionId))->where('station_id', $station->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            return [$this->payload($sessions->startTransmission($session)), 200];
        });
    }

    public function transmissionStop(Request $request, string $sessionId, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        $station = $request->attributes->get('agentStation');
        $user = $request->attributes->get('agentUser');
        return $idempotency->run($request, 'station:'.$station->id.':transmission-stop:'.strtolower($sessionId), function () use ($sessionId, $station, $user, $sessions) {
            $session = AgentWorkSession::whereKey(strtolower($sessionId))->where('station_id', $station->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            return [$this->payload($sessions->stopTransmission($session)), 200];
        });
    }

    private function payload(AgentWorkSession $session): array
    {
        return [
            'sessionId' => $session->id,
            'startedAt' => $session->started_at->toIso8601String(),
            'status' => $session->status,
            'endedAt' => $session->ended_at?->toIso8601String(),
            'totalConnectedSeconds' => $session->total_connected_seconds,
            'totalStreamingSeconds' => $session->total_streaming_seconds,
            'transmissionStartedAt' => $session->transmission_started_at?->toIso8601String(),
        ];
    }
}
