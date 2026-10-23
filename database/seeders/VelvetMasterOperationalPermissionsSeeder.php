<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class VelvetMasterOperationalPermissionsSeeder extends Seeder
{
    /**
     * Attachments use Spatie's additive API so local role customizations survive.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect([
            ['name' => 'Enrolar estaciones Agent', 'slug' => 'stations.enroll', 'group' => 'VELVET Agent'],
            ['name' => 'Conectarse a estaciones Agent', 'slug' => 'stations.connect', 'group' => 'VELVET Agent'],
            ['name' => 'Revocar estaciones Agent', 'slug' => 'stations.revoke', 'group' => 'VELVET Agent'],
            ['name' => 'Configurar estaciones Agent', 'slug' => 'stations.configure', 'group' => 'VELVET Agent'],
            ['name' => 'Solicitar estado de estaciones Agent', 'slug' => 'stations.commands', 'group' => 'VELVET Agent'],
            ['name' => 'Gestionar asistencia operativa', 'slug' => 'attendance.manage', 'group' => 'Asistencia'],
            ['name' => 'Gestionar checklists operativos de salas', 'slug' => 'room_checklists.manage', 'group' => 'Habitaciones'],
            ['name' => 'Ver plataformas webcam', 'slug' => 'platforms.view', 'group' => 'Plataformas webcam'],
            ['name' => 'Gestionar plataformas webcam', 'slug' => 'platforms.manage', 'group' => 'Plataformas webcam'],
            ['name' => 'Gestionar cuentas Studio', 'slug' => 'studio_accounts.manage', 'group' => 'Cuentas Studio'],
            ['name' => 'Asociar plataformas a modelos asignadas', 'slug' => 'studio_accounts.assign_assigned', 'group' => 'Cuentas Studio'],
            ['name' => 'Importar reportes de plataformas', 'slug' => 'platform_imports.manage', 'group' => 'Plataformas webcam'],
            ['name' => 'Ejecutar simulaciones de plataformas', 'slug' => 'platform_simulations.run', 'group' => 'Configuración e integraciones'],
            ['name' => 'Gestionar sesiones operativas', 'slug' => 'sessions.manage', 'group' => 'Sesiones de transmisión'],
            ['name' => 'Gestionar transmisiones', 'slug' => 'streams.manage', 'group' => 'Sesiones de transmisión'],
            ['name' => 'Gestionar productividad', 'slug' => 'productivity.manage', 'group' => 'Productividad'],
            ['name' => 'Validar métricas de modelos asignadas', 'slug' => 'productivity.validate_assigned', 'group' => 'Productividad'],
            ['name' => 'Gestionar inventario', 'slug' => 'inventory.manage', 'group' => 'Inventario y mantenimiento'],
            ['name' => 'Gestionar mantenimiento', 'slug' => 'maintenance.manage', 'group' => 'Inventario y mantenimiento'],
            ['name' => 'Gestionar evaluaciones operativas', 'slug' => 'performance_reviews.manage', 'group' => 'Modelos operativas'],
            ['name' => 'Ver reportes operativos', 'slug' => 'reports.operational.view', 'group' => 'Reportes operativos'],
            ['name' => 'Preparar exportaciones para Finanzas', 'slug' => 'integrations.finance_export', 'group' => 'Configuración e integraciones'],
        ])->mapWithKeys(fn (array $definition) => [
            $definition['slug'] => Permission::firstOrCreate(['slug' => $definition['slug']], [...$definition, 'guard_name' => 'web']),
        ]);

        $assignments = [
            'admin' => $permissions->keys()->all(),
            'operations_director' => ['attendance.manage', 'room_checklists.manage', 'platforms.view', 'sessions.manage', 'streams.manage', 'productivity.manage', 'inventory.manage', 'maintenance.manage', 'performance_reviews.manage', 'reports.operational.view'],
            'manager' => ['attendance.manage', 'room_checklists.manage', 'sessions.manage', 'streams.manage', 'productivity.manage', 'inventory.manage', 'maintenance.manage', 'performance_reviews.manage', 'reports.operational.view'],
            'hr' => ['performance_reviews.manage'],
            'monitor' => ['productivity.validate_assigned', 'studio_accounts.assign_assigned'],
            'finance' => ['integrations.finance_export', 'reports.operational.view'],
            'compliance' => ['platforms.view', 'reports.operational.view'],
            'model' => ['stations.connect'],
        ];

        foreach ($assignments as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $role->givePermissionTo($permissions->only($slugs)->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
