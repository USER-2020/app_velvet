<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public function createForRoles(string $channel, string $type, array $notification, array $data = [], array $roleSlugs = []): Collection
    {
        $targetSlugs = $roleSlugs ?: $this->defaultRoles();
        $users = User::query()
            ->with(['roles' => fn ($query) => $query->whereIn('slug', $targetSlugs)])
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $targetSlugs))
            ->get();
        $rows = collect();

        foreach ($users as $user) {
            $rows->push(Notification::create([
                'user_id' => $user->id,
                'role_id' => $user->roles->first()?->id,
                'channel' => $channel,
                'type' => $type,
                'title' => $notification['title'] ?? 'Nueva notificación',
                'description' => $notification['description'] ?? null,
                'data' => $data,
            ]));
        }

        return $rows;
    }

    /** @param array<int> $userIds */
    public function createForUsers(array $userIds, string $channel, string $type, array $notification, array $data = []): Collection
    {
        if ($userIds === []) return collect();

        return User::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $userIds))))
            ->get()
            ->map(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'role_id' => $user->roles()->value('id'),
                'channel' => $channel,
                'type' => $type,
                'title' => $notification['title'] ?? 'Nueva notificación',
                'description' => $notification['description'] ?? null,
                'data' => $data,
            ]));
    }

    public function forUser(User $user, ?string $channel = null, int $limit = 25): Collection
    {
        if (! Schema::hasTable('notifications')) return collect();

        return Notification::query()
            ->where('user_id', $user->id)
            ->when($channel, fn ($query) => $query->where('channel', $channel))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function paginateForUser(User $user, ?string $channel = null, int $perPage = 15)
    {
        if (! Schema::hasTable('notifications')) {
            return Notification::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return Notification::query()
            ->where('user_id', $user->id)
            ->when($channel, fn ($query) => $query->where('channel', $channel))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    private function defaultRoles(): array
    {
        return ['super_admin', 'admin', 'recruiter', 'hr', 'manager', 'operations_director'];
    }
}

