<?php

namespace App\Models;

use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'parent_role_id', 'guard_name'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** Roles are addressed by slug throughout the existing application. */
    public static function findByName(string $name, ?string $guardName = null): RoleContract
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $role = static::query()->where('slug', $name)->where('guard_name', $guardName)->first();

        if ($role) {
            return $role;
        }

        throw RoleDoesNotExist::named($name, $guardName);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_role_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_role_id');
    }
}
