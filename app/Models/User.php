<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
        hasRole as protected spatieHasRole;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
        'profile_photo_source',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function candidate()
    {
        return $this->hasOne(Candidate::class);
    }

    /** Existing application code addresses roles by slug; Spatie stores role checks by name. */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if (is_string($roles)) {
            return $this->roles()
                ->where('slug', $roles)
                ->when($guard, fn ($query) => $query->where('guard_name', $guard))
                ->exists();
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return $this->spatieHasRole($roles, $guard);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Gate also asks about policy abilities (for example, viewAssignedModel)
        // that are not persisted Spatie permissions. Let the policy handle them.
        if (is_string($permission) && ! Permission::query()->where('slug', $permission)->where('guard_name', $guardName ?: $this->getDefaultGuardName())->exists()) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }
}
