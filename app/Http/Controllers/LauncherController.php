<?php

namespace App\Http\Controllers;

use App\Models\LauncherEnrollmentToken;
use App\Models\LauncherStation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LauncherController extends Controller
{
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:4096'],
            'computerName' => ['required', 'string', 'max:255'],
            'operatingSystem' => ['required', 'string', 'max:255'],
            'launcherVersion' => ['required', 'string', 'max:100'],
            'installationId' => ['nullable', 'uuid'],
        ]);

        return DB::transaction(function () use ($data) {
            $token = LauncherEnrollmentToken::where('token_hash', hash('sha256', $data['code']))->lockForUpdate()->first();
            abort_unless($token && ! $token->used_at && $token->expires_at->isFuture(), 401, 'Invalid launcher enrollment code.');

            $installationId = strtolower($data['installationId'] ?? (string) Str::uuid());
            $station = LauncherStation::where('installation_id', $installationId)->lockForUpdate()->first();
            abort_if($station && $station->studio_id !== $token->studio_id, 409, 'Launcher station belongs to another studio.');

            $credential = 'vls_'.bin2hex(random_bytes(32));
            $attributes = [
                'hostname' => $data['computerName'],
                'operating_system' => $data['operatingSystem'],
                'studio_id' => $token->studio_id,
                'credential_hash' => hash('sha256', $credential),
                'version' => $data['launcherVersion'],
                'revoked_at' => null,
                'last_seen_at' => null,
                'configuration' => ['heartbeatSeconds' => config('launcher.heartbeat_seconds'), 'studioId' => $token->studio_id],
            ];

            $station ? $station->update($attributes) : $station = LauncherStation::create([...$attributes, 'installation_id' => $installationId]);
            $token->update(['used_at' => now()]);

            return response()->json([
                'deviceId' => (string) $station->id,
                'deviceCredential' => $credential,
                'computerName' => $station->hostname,
                'operatingSystem' => $station->operating_system,
                'launcherVersion' => $station->version,
                'roomName' => $station->room?->name,
                'installationId' => $station->installation_id,
            ]);
        });
    }

    public function station(Request $request)
    {
        $station = $request->attributes->get('launcherStation');
        $assignment = $station->currentAssignment()->with(['room', 'modelCandidate.lead', 'monitorCandidate.lead'])->first();

        return response()->json([
            'deviceId' => (string) $station->id,
            'computerName' => $station->hostname,
            'operatingSystem' => $station->operating_system,
            'launcherVersion' => $station->version,
            'roomName' => $assignment?->room?->name,
            'installationId' => $station->installation_id,
            'studioId' => $station->studio_id,
            'assignment' => $assignment ? [
                'roomId' => $assignment->room_id,
                'modelCandidateId' => $assignment->model_candidate_id,
                'monitorCandidateId' => $assignment->monitor_candidate_id,
                'startsAt' => $assignment->starts_at?->toIso8601String(),
                'endsAt' => $assignment->ends_at?->toIso8601String(),
            ] : null,
            'configuration' => $station->configuration,
        ])->header('Cache-Control', 'no-store');
    }

    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'installationId' => ['nullable', 'uuid'],
            'computerName' => ['nullable', 'string', 'max:255'],
            'operatingSystem' => ['nullable', 'string', 'max:255'],
            'launcherVersion' => ['nullable', 'string', 'max:100'],
            'sentAt' => ['nullable', 'date'],
            'status' => ['nullable', 'array'],
        ]);
        $station = $request->attributes->get('launcherStation');
        abort_unless(empty($data['installationId']) || strtolower($data['installationId']) === $station->installation_id, 403, 'Launcher identity mismatch.');

        $station->update([
            'hostname' => $data['computerName'] ?? $station->hostname,
            'operating_system' => $data['operatingSystem'] ?? $station->operating_system,
            'version' => $data['launcherVersion'] ?? $station->version,
            'last_seen_at' => now(),
        ]);

        DB::table('launcher_heartbeats')->insert([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'body_hash' => hash('sha256', $request->getContent()),
            'payload' => json_encode($request->all(), JSON_THROW_ON_ERROR),
            'sent_at' => isset($data['sentAt']) ? Carbon::parse($data['sentAt']) : now(),
            'received_at' => now(),
        ]);

        return response()->noContent();
    }
}
