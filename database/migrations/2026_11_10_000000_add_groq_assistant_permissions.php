<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $chat = Permission::firstOrCreate(['slug' => 'ai.chat'], ['name' => 'Usar asistente IA', 'group' => 'Asistente IA', 'guard_name' => 'web']);
        $manage = Permission::firstOrCreate(['slug' => 'ai.manage'], ['name' => 'Configurar asistente IA', 'group' => 'Asistente IA', 'guard_name' => 'web']);

        Role::query()->whereIn('slug', ['super_admin', 'admin', 'recruiter', 'hr', 'manager', 'monitor', 'model', 'candidate', 'operations_director', 'marketing', 'finance', 'compliance', 'support'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($chat));
        Role::query()->where('slug', 'super_admin')->first()?->givePermissionTo($manage);
    }

    public function down(): void
    {
        DB::table('role_has_permissions')->whereIn('permission_id', Permission::whereIn('slug', ['ai.chat', 'ai.manage'])->pluck('id'))->delete();
        Permission::whereIn('slug', ['ai.chat', 'ai.manage'])->delete();
        DB::table('system_settings')->where('key', 'groq_assistant')->delete();
    }
};
