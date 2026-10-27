<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\RealtimePublisher;
use App\Services\NotificationService;
use App\Enums\CandidateStatus;
use App\Models\UserTourProgress;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->load('roles.permissions');

        $candidate = $user?->candidate;
        $portalRole = $user?->hasRole(['model', 'monitor']) ?? false;

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'agentToken' => fn () => $request->session()->get('agentToken'),
            ],
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'profile_photo_url' => $user->profile_photo_path ? route('profile.photo', ['v' => $user->updated_at?->timestamp]) : null,
                    'candidate_id' => $candidate?->id,
                    'candidate_status' => $candidate?->status?->value,
                    'candidate_access_active' => (bool) $user->is_active,
                    'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug])->values(),
                    'permissions' => $user->roles->flatMap(fn ($role) => $role->permissions->pluck('slug'))->unique()->values(),
                ]) : null,
            ],
            'operationalAccess' => $portalRole ? [
                'is_portal' => true,
                'is_active' => $candidate?->status === CandidateStatus::ACTIVE && (bool) $candidate?->user?->is_active,
                'status' => $candidate?->status?->value,
                'label' => $candidate?->status?->label(),
                'training_mode' => $candidate?->status !== CandidateStatus::ACTIVE || ! $candidate?->user?->is_active,
            ] : null,
            'tourProgress' => $this->tourProgress($user),
            'guidedTours' => [
                'enabled' => $this->guidedToursEnabled(),
            ],
            'realtime' => $user ? [
                'counters' => app(RealtimePublisher::class)->counters(),
                'notifications' => app(NotificationService::class)->forUser($user),
            ] : null,
        ];
    }

    private function tourProgress(?User $user): array
    {
        if (! $user) {
            return [];
        }

        try {
            return UserTourProgress::query()
                ->where('user_id', $user->id)
                ->get(['tour_key', 'version', 'completed_at', 'skipped_at'])
                ->mapWithKeys(fn (UserTourProgress $tour) => [$tour->tour_key => [
                    'version' => $tour->version,
                    'completed_at' => $tour->completed_at?->toIso8601String(),
                    'skipped_at' => $tour->skipped_at?->toIso8601String(),
                ]])
                ->all();
        } catch (QueryException $exception) {
            // A deployment should not take the whole panel down if its migration is pending.
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    private function guidedToursEnabled(): bool
    {
        try {
            return SystemSetting::guidedToursEnabled();
        } catch (QueryException $exception) {
            // A pending settings migration should not block access to the platform.
            if ($this->isMissingTable($exception)) {
                return true;
            }

            throw $exception;
        }
    }

    private function isMissingTable(QueryException $exception): bool
    {
        return $exception->getCode() === '42S02'
            || str_contains(strtolower($exception->getMessage()), 'no such table');
    }
}
