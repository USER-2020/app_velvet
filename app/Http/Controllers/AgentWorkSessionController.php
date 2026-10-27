<?php

namespace App\Http\Controllers;

use App\Enums\CandidateType;
use App\Models\Agent\AgentWorkSession;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentWorkSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $scope = 'all';
        $query = AgentWorkSession::query();

        if ($user->hasRole('model')) {
            $scope = 'own';
            $query->where('user_id', $user->id);
        } elseif ($user->hasRole('monitor')) {
            $scope = 'assigned';
            $monitorCandidateId = Candidate::where('user_id', $user->id)->value('id');
            $modelUserIds = Candidate::query()
                ->where('candidate_type', CandidateType::MODEL->value)
                ->whereNotNull('user_id')
                ->when($monitorCandidateId, fn ($builder) => $builder->whereHas('modelAssignments', fn ($assignments) => $assignments
                    ->where('monitor_candidate_id', $monitorCandidateId)
                    ->whereNull('ended_at')), fn ($builder) => $builder->whereRaw('1 = 0'))
                ->pluck('user_id');
            $query->whereIn('user_id', $modelUserIds);
        } elseif (! $user->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $summaryQuery = clone $query;
        $summary = [
            'sessions' => (clone $summaryQuery)->count(),
            'active' => (clone $summaryQuery)->where('status', 'active')->count(),
            'connectedSeconds' => (int) (clone $summaryQuery)->sum('total_connected_seconds'),
            'streamingSeconds' => (int) (clone $summaryQuery)->sum('total_streaming_seconds'),
        ];

        $sessions = $query->with(['user:id,name,email', 'station:id,hostname,studio_id'])
            ->latest('started_at')
            ->limit(200)
            ->get()
            ->map(fn (AgentWorkSession $session) => [
                'id' => $session->id,
                'user' => $session->user?->only(['id', 'name', 'email']),
                'station' => $session->station ? [
                    'id' => $session->station->id,
                    'hostname' => $session->station->hostname,
                    'studioId' => $session->station->studio_id,
                ] : null,
                'startedAt' => $session->started_at?->toIso8601String(),
                'lastHeartbeatAt' => $session->last_heartbeat_at?->toIso8601String(),
                'endedAt' => $session->ended_at?->toIso8601String(),
                'endReason' => $session->end_reason,
                'status' => $session->status,
                'totalConnectedSeconds' => (int) $session->total_connected_seconds,
                'totalStreamingSeconds' => (int) $session->total_streaming_seconds,
                'lastObs' => $session->last_obs,
            ])->values();

        return Inertia::render('Agent/WorkSessions/Index', [
            'scope' => $scope,
            'sessions' => $sessions,
            'summary' => $summary,
            'timeoutSeconds' => config('agent.session_timeout_seconds'),
        ]);
    }
}
