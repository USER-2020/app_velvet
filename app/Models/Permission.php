<?php

namespace App\Models;

use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = ['name', 'slug', 'group', 'guard_name'];

    /** Velvet authorizes by immutable slugs while Spatie normally resolves by name. */
    public static function findByName(string $name, ?string $guardName = null): PermissionContract
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $permission = static::query()->where('slug', $name)->where('guard_name', $guardName)->first();

        if ($permission) {
            return $permission;
        }

        throw PermissionDoesNotExist::create($name, $guardName);
    }
}
