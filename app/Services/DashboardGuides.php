<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class DashboardGuides
{
    private readonly array $catalog;

    public function __construct()
    {
        $this->catalog = require resource_path('documentation/dashboard.php');
    }

    public function profiles(User $user): array
    {
        return $user->roles->map(fn ($role) => [
            'name' => $role->name,
            'summary' => $this->catalog['roles'][$role->slug]
                ?? 'El catálogo refleja los permisos asignados a tu cuenta.',
        ])->values()->all();
    }

    public function forUser(User $user): Collection
    {
        $roles = $user->roles->pluck('slug')->all();
        $permissions = $user->getAllPermissions()->pluck('slug')->all();
        $allows = fn (string $ability) => str_starts_with($ability, '@')
            ? in_array(substr($ability, 1), $roles, true)
            : in_array('super_admin', $roles, true) || in_array($ability, $permissions, true);

        return collect($this->catalog['modules'])->map(function (array $item, string $key) use ($allows) {
            [$group, $title, $route, $ability, $steps] = $item;

            if (! $this->allowed($ability, $allows)) {
                return null;
            }

            return [
                'key' => $key,
                'group' => $group,
                'title' => $title,
                'url' => in_array($key, ['ai-chat', 'dashboard-controls', 'documentation'], true)
                    ? null
                    : route($route, $item[5] ?? [], absolute: false),
                'preview_url' => route('admin.documentation.guide', ['guide' => $key], absolute: false),
                'video_url' => is_file(resource_path("documentation/videos/{$key}.mp4"))
                    ? route('admin.documentation.guide-video', ['guide' => $key], absolute: false)
                    : null,
                'steps' => collect($steps)
                    ->filter(fn (array $step) => $this->allowed($step[2] ?? null, $allows))
                    ->map(fn (array $step) => ['title' => $step[0], 'description' => $step[1]])
                    ->values()->all(),
            ];
        })->filter();
    }

    private function allowed(string|array|null $ability, callable $allows): bool
    {
        if ($ability === null) {
            return true;
        }

        if (isset($ability['all'])) {
            return collect($ability['all'])->every($allows);
        }

        return collect((array) $ability)->contains($allows);
    }
}
