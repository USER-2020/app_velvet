<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent\AgentCommand;
use App\Models\Agent\AgentEnrollmentToken;
use App\Models\Agent\AgentStation;
use App\Services\Agent\AgentPayload;
use App\Services\Agent\AgentWorkSessionService;
use App\Services\Agent\Idempotency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function enroll(Request $request, Idempotency $idempotency)
    {
        $data = AgentPayload::validate($request, ['installationId' => 'required|uuid', 'hostname' => 'required|string|max:255', 'token' => 'required|string|max:4096']);

        return $idempotency->run($request, 'enroll', function () use ($data) {
            $token = AgentEnrollmentToken::where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_unless($token && ! $token->used_at && $token->expires_at->isFuture(), 401, 'Invalid enrollment token.');
            $station = AgentStation::where('installation_id', strtolower($data['installationId']))->lockForUpdate()->first();
            abort_if($station && $station->studio_id !== $token->studio_id, 409, 'Station belongs to another studio.');
            $credential = 'ved_'.bin2hex(random_bytes(32));
            $attributes = [
                'hostname' => $data['hostname'], 'studio_id' => $token->studio_id,
                'credential_hash' => hash('sha256', $credential), 'revoked_at' => null,
                'last_seen_at' => null, 'offline_at' => null, 'version' => null,
                'abilities' => ['agent:heartbeat', 'agent:configuration', 'agent:events', 'agent:results'],
            ];
            if ($station) {
                $station->update($attributes);
                // Commands reserved for the previous enrollment must never execute under a new credential.
                AgentCommand::where('station_id', $station->id)->where('state', 'pending')->update(['state' => 'cancelled']);
            } else {
                abort_unless(filter_var(config('agent.master_url'), FILTER_VALIDATE_URL) && str_starts_with(config('agent.master_url'), 'https://') && ! parse_url(config('agent.master_url'), PHP_URL_USER) && ! parse_url(config('agent.master_url'), PHP_URL_PASS), 503, 'Agent master URL is not configured for HTTPS.');
                $station = AgentStation::create([...$attributes, 'installation_id' => strtolower($data['installationId']), 'configuration' => [
                    'masterUrl' => config('agent.master_url'), 'studioId' => $token->studio_id, 'heartbeatSeconds' => config('agent.heartbeat_seconds'), 'development' => false,
                ]]);
            }
            $token->update(['used_at' => now()]);

            return [['deviceToken' => $credential], 200];
        }, true, function (array $body) use ($data) {
            $station = AgentStation::where('installation_id', strtolower($data['installationId']))->lockForUpdate()->first();
            abort_unless($station && ! $station->revoked_at && hash_equals($station->credential_hash, hash('sha256', $body['deviceToken'])), 401, 'Enrollment credential is no longer active.');
        });
    }

    public function configuration(Request $request)
    {
        $station = $request->attributes->get('agentStation');

        return response()->json([
            ...$station->configuration,
            'studioId' => $station->studio_id,
        ])->header('Cache-Control', 'no-store')->header('X-Agent-Configuration-Version', (string) $station->configuration_version);
    }

    public function heartbeat(Request $request, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        $data = AgentPayload::validate($request, AgentPayload::heartbeatRules());
        $station = $request->attributes->get('agentStation');
        $installationId = data_get($data, 'status.identity.installationId');
        $sessionId = data_get($data, 'status.sessionId') ?? data_get($data, 'sessionId');
        $sessionStartedAt = data_get($data, 'status.sessionStartedAt');
        $userId = data_get($data, 'status.userId');
        abort_unless(strtolower($installationId) === $station->installation_id, 403, 'Station identity mismatch.');

        return $idempotency->run($request, 'station:'.$station->id.':heartbeat', function () use ($data, $station, $request, $sessions, $sessionId, $sessionStartedAt, $userId, $installationId) {
            $fresh = $this->record('agent_heartbeats', $station, $data, $data['sentAt']);
            if ($fresh) {
                $obs = collect($data['status']['obs'])->map(fn ($value) => is_string($value) ? AgentPayload::clean($value) : $value)->all();
                $metrics = [...$data['status']['metrics'], 'uptimeSeconds' => $data['status']['uptimeSeconds']];
                $station->update([
                    'hostname' => $data['status']['identity']['hostname'],
                    'version' => $data['status']['version'],
                    'last_seen_at' => now(),
                    'offline_at' => null,
                    'obs_status' => $obs,
                    'system_metrics' => $metrics,
                    'obs_reported_at' => now(),
                ]);
                if (! empty($sessionId)) {
                    $oauth = $request->attributes->get('agentOAuthToken');
                    abort_unless($oauth, 401, 'OAuth session required for work session.');

                    $session = \App\Models\Agent\AgentWorkSession::whereKey($sessionId)
                        ->where('station_id', $station->id)
                        ->where('user_id', $oauth->user_id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

                    // Older Agents generate their local session id and send it
                    // on the first heartbeat without calling sessions/start.
                    // Register it here, while keeping OAuth as the authority
                    // for the user and server timestamps for duration metrics.
                    $session ??= $sessions->startFromHeartbeat($station, $oauth->user_id, $sessionId);
                    $sessionObs = $data['obs'] ?? $data['status']['obs'];
                    $sessions->heartbeat($session, $sessionObs, $data['active'] ?? true);
                }
            }

            return [null, 204];
        });
    }

    public function event(Request $request, Idempotency $idempotency)
    {
        $data = AgentPayload::validate($request, ['id' => 'required|uuid', 'at' => AgentPayload::time(), 'kind' => 'required|string|max:100|regex:/^[A-Za-z0-9._:-]+$/', 'message' => 'present|string|max:2000']);
        $station = $request->attributes->get('agentStation');

        return $idempotency->run($request, 'station:'.$station->id.':events', function () use ($data, $station) {
            $this->record('agent_events', $station, $data, $data['at']);

            return [null, 204];
        });
    }

    public function result(Request $request, string $id, Idempotency $idempotency)
    {
        $data = AgentPayload::validate($request, ['commandId' => 'required|uuid', 'success' => 'required|boolean', 'message' => 'present|string|max:2000']);
        abort_unless(strtolower($data['commandId']) === strtolower($id), 400, 'Command ID mismatch.');
        $station = $request->attributes->get('agentStation');

        return $idempotency->run($request, 'station:'.$station->id.':result:'.strtolower($id), function () use ($data, $station, $id) {
            $command = AgentCommand::where('id', strtolower($id))->where('station_id', $station->id)->lockForUpdate()->firstOrFail();
            $hash = Idempotency::hash($data);
            if ($command->result_hash) {
                abort_unless(hash_equals($command->result_hash, $hash), 409, 'Terminal result cannot be replaced.');

                return [null, 204];
            }
            abort_unless($command->operation === 'RefreshStatus' && $command->state === 'pending', 409, 'Command is not executable.');
            abort_if(! $command->expires_at->isFuture(), 409, 'Command expired.');
            $command->update(['result_hash' => $hash, 'result' => [...$data, 'message' => AgentPayload::clean($data['message'])], 'state' => $data['success'] ? 'completed' : 'failed']);

            return [null, 204];
        });
    }

    private function record(string $table, AgentStation $station, array $data, string $sentAt): bool
    {
        $hash = Idempotency::hash($data);
        $clean = function ($value) use (&$clean) {
            if (is_array($value)) {
                return array_map($clean, $value);
            }

            return is_string($value) ? AgentPayload::clean($value) : $value;
        };
        $inserted = DB::table($table)->insertOrIgnore([
            'id' => strtolower($data['id']), 'station_id' => $station->id, 'body_hash' => $hash,
            'payload' => json_encode($clean($data), JSON_THROW_ON_ERROR),
            'sent_at' => Carbon::parse($sentAt)->utc(), 'received_at' => now(),
        ]);
        $record = DB::table($table)->where('id', strtolower($data['id']))->first();
        abort_unless($record && $record->station_id === $station->id && hash_equals($record->body_hash, $hash), 409, 'Payload UUID conflict.');

        return (bool) $inserted;
    }
}
