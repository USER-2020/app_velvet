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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentAdministrationController extends Controller
{
    public function index(Request $request)
    {
        $stations = AgentStation::query()->latest('last_seen_at')->latest('id')->get()->map(function (AgentStation $station) {
            return [
                'id' => $station->id,
                'installationId' => $station->installation_id,
                'hostname' => $station->hostname,
                'studioId' => $station->studio_id,
                'version' => $station->version,
                'lastSeenAt' => $station->last_seen_at?->toIso8601String(),
                'offlineAt' => $station->offline_at?->toIso8601String(),
                'revokedAt' => $station->revoked_at?->toIso8601String(),
                'configuration' => $station->configuration,
                'configurationVersion' => $station->configuration_version,
                'abilities' => $station->abilities,
                'heartbeatReceivedAt' => $station->obs_reported_at?->toIso8601String(),
                'obs' => $station->obs_status,
                'metrics' => $station->system_metrics,
            ];
        })->values();

        $tokens = AgentEnrollmentToken::query()->with('issuer:id,name')->latest()->limit(12)->get()->map(fn (AgentEnrollmentToken $token) => [
            'id' => $token->id,
            'studioId' => $token->studio_id,
            'expiresAt' => $token->expires_at?->toIso8601String(),
            'usedAt' => $token->used_at?->toIso8601String(),
            'issuedBy' => $token->issuer?->name,
        ])->values();

        return \Inertia\Inertia::render('Admin/Agent/Index', [
            'stations' => $stations,
            'tokens' => $tokens,
            'studios' => config('agent.studios'),
            'masterUrl' => config('agent.master_url'),
            'enrollmentMinutes' => config('agent.enrollment_minutes'),
        ]);
    }

    public function issuePanel(Request $request)
    {
        $data = $request->validate(['studioId' => ['required', 'string', 'max:100', Rule::in(config('agent.studios'))]]);
        $token = 'vea_'.bin2hex(random_bytes(32));
        $expires = now()->addMinutes(config('agent.enrollment_minutes'));

        AgentEnrollmentToken::create([
            'token_hash' => hash('sha256', $token),
            'studio_id' => $data['studioId'],
            'issued_by' => $request->user()->id,
            'expires_at' => $expires,
        ]);

        return back()->with('agentToken', [
            'token' => $token,
            'studioId' => $data['studioId'],
            'expiresAt' => $expires->toIso8601String(),
        ]);
    }

    public function issue(Request $request, Idempotency $idempotency)
    {
        $data = AgentPayload::validate($request, ['studioId' => ['required', 'string', 'max:100', Rule::in(config('agent.studios'))]]);

        return $idempotency->run($request, 'admin:'.$request->user()->id.':issue', function () use ($request, $data) {
            $token = 'vea_'.bin2hex(random_bytes(32));
            $expires = now()->addMinutes(config('agent.enrollment_minutes'));
            AgentEnrollmentToken::create(['token_hash' => hash('sha256', $token), 'studio_id' => $data['studioId'], 'issued_by' => $request->user()->id, 'expires_at' => $expires]);

            return [['token' => $token, 'expiresAt' => $expires->toIso8601String()], 201];
        }, true);
    }

    public function revoke(Request $request, AgentStation $station, Idempotency $idempotency, AgentWorkSessionService $sessions)
    {
        return $idempotency->run($request, 'admin:'.$request->user()->id.':revoke:'.$station->id, function () use ($station, $sessions) {
            $station = AgentStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
            $station->update(['revoked_at' => $station->revoked_at ?? now()]);
            $sessions->endForStation($station->id, 'revoked', 'revoked');
            AgentCommand::where('station_id', $station->id)->where('state', 'pending')->update(['state' => 'cancelled']);

            return [null, 204];
        });
    }

    public function configure(Request $request, AgentStation $station)
    {
        $data = AgentPayload::validate($request, ['heartbeatSeconds' => 'required|integer|between:5,3600']);
        DB::transaction(function () use ($station, $data) {
            $station = AgentStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
            abort_if($station->revoked_at, 409, 'Station revoked.');
            if ((int) $station->configuration['heartbeatSeconds'] === (int) $data['heartbeatSeconds']) {
                return;
            }
            $station->update(['configuration' => [...$station->configuration, ...$data], 'configuration_version' => $station->configuration_version + 1]);
        });

        return response()->noContent();
    }

    public function command(Request $request, AgentStation $station, Idempotency $idempotency)
    {
        $data = AgentPayload::validate($request, ['operation' => 'required|in:RefreshStatus', 'expiresAt' => AgentPayload::time()]);

        return $idempotency->run($request, 'admin:'.$request->user()->id.':command:'.$station->id, function () use ($request, $station, $data) {
            abort_unless(Carbon::parse($data['expiresAt'])->isFuture(), 400, 'Command expiry must be in the future.');
            $station = AgentStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
            abort_if($station->revoked_at, 409, 'Station revoked.');
            $command = AgentCommand::create(['id' => (string) Str::uuid(), 'station_id' => $station->id, 'operation' => $data['operation'], 'expires_at' => $data['expiresAt'], 'issued_by' => $request->user()->id]);

            // Reservation only. Agent has no command transport enabled yet.
            return [['id' => $command->id, 'operation' => $command->operation, 'expiresAt' => $command->expires_at->toIso8601String()], 201];
        });
    }
}
