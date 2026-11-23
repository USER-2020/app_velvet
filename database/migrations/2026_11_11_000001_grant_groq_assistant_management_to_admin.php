<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $chat = Permission::firstOrCreate(
            ['slug' => 'ai.chat'],
            ['name' => 'Usar asistente IA', 'group' => 'Asistente IA', 'guard_name' => 'web'],
        );
        $manage = Permission::firstOrCreate(
            ['slug' => 'ai.manage'],
            ['name' => 'Configurar asistente IA', 'group' => 'Asistente IA', 'guard_name' => 'web'],
        );

        Role::query()->whereIn('slug', ['super_admin', 'admin'])->get()->each(function (Role $role) use ($chat, $manage): void {
            $role->givePermissionTo([$chat, $manage]);
        });
    }

    public function down(): void
    {
        $permissionIds = Permission::query()->whereIn('slug', ['ai.chat', 'ai.manage'])->pluck('id');
        Role::query()->whereIn('slug', ['super_admin', 'admin'])->get()->each(
            fn (Role $role) => $role->revokePermissionTo($permissionIds),
        );
    }
};
