<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (($legacyReviewer = Role::where('slug', 'reviewer')->first()) && ! Role::where('slug', 'hr')->exists()) {
            $legacyReviewer->update(['slug' => 'hr', 'name' => 'Recursos Humanos']);
        }

        $definitions = [
            ['name' => 'Ver dashboard', 'slug' => 'dashboard.view', 'group' => 'Dashboard'],
            ['name' => 'Ver leads', 'slug' => 'leads.view', 'group' => 'Reclutamiento'],
            ['name' => 'Crear leads', 'slug' => 'leads.create', 'group' => 'Reclutamiento'],
            ['name' => 'Gestionar leads', 'slug' => 'leads.manage', 'group' => 'Reclutamiento'],
            ['name' => 'Ver candidatos', 'slug' => 'candidates.view', 'group' => 'Reclutamiento'],
            ['name' => 'Ver todos los candidatos', 'slug' => 'candidates.view_all', 'group' => 'Reclutamiento'],
            ['name' => 'Crear candidatos', 'slug' => 'candidates.create', 'group' => 'Reclutamiento'],
            ['name' => 'Actualizar candidatos', 'slug' => 'candidates.update', 'group' => 'Reclutamiento'],
            ['name' => 'Cambiar estado de candidatos', 'slug' => 'candidates.change_status', 'group' => 'Reclutamiento'],
            ['name' => 'Activar acceso de candidatos', 'slug' => 'candidates.activate_access', 'group' => 'Reclutamiento'],
            ['name' => 'Agregar notas a candidatos', 'slug' => 'candidates.add_note', 'group' => 'Reclutamiento'],
            ['name' => 'Programar entrevistas', 'slug' => 'candidates.schedule_interview', 'group' => 'Reclutamiento'],
            ['name' => 'Solicitar documentos', 'slug' => 'candidates.request_documents', 'group' => 'Reclutamiento'],
            ['name' => 'Aprobar candidatos', 'slug' => 'candidates.approve', 'group' => 'Reclutamiento'],
            ['name' => 'Rechazar candidatos', 'slug' => 'candidates.reject', 'group' => 'Reclutamiento'],
            ['name' => 'Convertir candidatos', 'slug' => 'candidates.convert', 'group' => 'Reclutamiento'],
            ['name' => 'Ver entrevistas', 'slug' => 'interviews.view', 'group' => 'Reclutamiento'],
            ['name' => 'Ver calendario', 'slug' => 'calendar.view', 'group' => 'Reclutamiento'],
            ['name' => 'Gestionar calendario', 'slug' => 'calendar.manage', 'group' => 'Reclutamiento'],
            ['name' => 'Gestionar onboarding', 'slug' => 'onboarding.manage', 'group' => 'Onboarding'],
            ['name' => 'Ver onboarding', 'slug' => 'onboarding.view', 'group' => 'Onboarding'],
            ['name' => 'Ver documentos', 'slug' => 'documents.view', 'group' => 'Documentos'],
            ['name' => 'Administrar documentos', 'slug' => 'documents.manage', 'group' => 'Documentos'],
            ['name' => 'Solicitar documentos', 'slug' => 'documents.request', 'group' => 'Documentos'],
            ['name' => 'Verificar documentos', 'slug' => 'documents.verify', 'group' => 'Documentos'],
            ['name' => 'Ver contratos', 'slug' => 'contracts.view', 'group' => 'Contratos'],
            ['name' => 'Crear contratos', 'slug' => 'contracts.create', 'group' => 'Contratos'],
            ['name' => 'Actualizar contratos', 'slug' => 'contracts.update', 'group' => 'Contratos'],
            ['name' => 'Aprobar contratos', 'slug' => 'contracts.approve', 'group' => 'Contratos'],
            ['name' => 'Crear modelos desde candidatos', 'slug' => 'models.create_from_candidate', 'group' => 'Personas'],
            ['name' => 'Ver modelos asignados', 'slug' => 'models.view_assigned', 'group' => 'Personas'],
            ['name' => 'Ver todos los modelos', 'slug' => 'models.view_all', 'group' => 'Personas'],
            ['name' => 'Ver modelos del equipo', 'slug' => 'models.view_team', 'group' => 'Personas'],
            ['name' => 'Asignar monitor a modelos', 'slug' => 'models.assign_monitor', 'group' => 'Personas'],
            ['name' => 'Cambiar monitor de modelos', 'slug' => 'models.change_monitor', 'group' => 'Personas'],
            ['name' => 'Agregar notas a modelos', 'slug' => 'models.add_note', 'group' => 'Personas'],
            ['name' => 'Agregar seguimientos', 'slug' => 'models.add_followup', 'group' => 'Personas'],
            ['name' => 'Ver métricas asignadas', 'slug' => 'models.view_metrics', 'group' => 'Métricas'],
            ['name' => 'Ver objetivos asignados', 'slug' => 'models.view_goals', 'group' => 'Metas'],
            ['name' => 'Ver metas asignadas', 'slug' => 'goals.view_assigned', 'group' => 'Metas'],
            ['name' => 'Ver monitores del equipo', 'slug' => 'monitors.view_team', 'group' => 'Personas'],
            ['name' => 'Ver turnos asignados', 'slug' => 'shifts.view_assigned', 'group' => 'Horarios'],
            ['name' => 'Actualizar estado de turnos', 'slug' => 'shifts.update_status', 'group' => 'Horarios'],
            ['name' => 'Ver turnos del equipo', 'slug' => 'shifts.view_team', 'group' => 'Horarios'],
            ['name' => 'Gestionar turnos del equipo', 'slug' => 'shifts.manage_team', 'group' => 'Horarios'],
            ['name' => 'Crear metas', 'slug' => 'goals.create', 'group' => 'Metas'],
            ['name' => 'Actualizar metas', 'slug' => 'goals.update', 'group' => 'Metas'],
            ['name' => 'Asignar metas', 'slug' => 'goals.assign', 'group' => 'Metas'],
            ['name' => 'Ver métricas del equipo', 'slug' => 'metrics.view_team', 'group' => 'Métricas'],
            ['name' => 'Crear incidentes', 'slug' => 'incidents.create', 'group' => 'Incidentes'],
            ['name' => 'Ver incidentes asignados', 'slug' => 'incidents.view_assigned', 'group' => 'Incidentes'],
            ['name' => 'Ver incidentes del equipo', 'slug' => 'incidents.view_team', 'group' => 'Incidentes'],
            ['name' => 'Gestionar incidentes', 'slug' => 'incidents.manage', 'group' => 'Incidentes'],
            ['name' => 'Ver salas', 'slug' => 'rooms.view', 'group' => 'Sedes y salas'],
            ['name' => 'Gestionar salas', 'slug' => 'rooms.manage', 'group' => 'Sedes y salas'],
            ['name' => 'Gestionar salas del equipo', 'slug' => 'rooms.manage_team', 'group' => 'Sedes y salas'],
            ['name' => 'Ver rooms propias', 'slug' => 'rooms.view_own', 'group' => 'Sedes y salas'],
            ['name' => 'Reservar rooms propias', 'slug' => 'rooms.reserve_own', 'group' => 'Sedes y salas'],
            ['name' => 'Administrar uso propio de rooms', 'slug' => 'rooms.use_own', 'group' => 'Sedes y salas'],
            ['name' => 'Ver rooms del equipo', 'slug' => 'rooms.view_team', 'group' => 'Sedes y salas'],
            ['name' => 'Gestionar reservas de rooms', 'slug' => 'rooms.manage_reservations', 'group' => 'Sedes y salas'],
            ['name' => 'Gestionar uso de rooms', 'slug' => 'rooms.manage_usage', 'group' => 'Sedes y salas'],
            ['name' => 'Ver perfil propio', 'slug' => 'profile.view_own', 'group' => 'Portal'],
            ['name' => 'Actualizar perfil propio', 'slug' => 'profile.update_own', 'group' => 'Portal'],
            ['name' => 'Ver documentos propios', 'slug' => 'documents.view_own', 'group' => 'Portal'],
            ['name' => 'Cargar documentos propios', 'slug' => 'documents.upload_own', 'group' => 'Portal'],
            ['name' => 'Ver turnos propios', 'slug' => 'shifts.view_own', 'group' => 'Portal'],
            ['name' => 'Ver metas propias', 'slug' => 'goals.view_own', 'group' => 'Portal'],
            ['name' => 'Ver métricas propias', 'slug' => 'metrics.view_own', 'group' => 'Portal'],
            ['name' => 'Ver capacitaciones', 'slug' => 'training.view', 'group' => 'Portal'],
            ['name' => 'Crear solicitudes', 'slug' => 'requests.create', 'group' => 'Portal'],
            ['name' => 'Ver solicitudes propias', 'slug' => 'requests.view_own', 'group' => 'Portal'],
            ['name' => 'Ver pagos propios', 'slug' => 'payments.view_own', 'group' => 'Finanzas'],
            ['name' => 'Ver datos personales', 'slug' => 'personal_data.view', 'group' => 'Datos personales'],
            ['name' => 'Actualizar datos personales', 'slug' => 'personal_data.update', 'group' => 'Datos personales'],
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'group' => 'Configuración'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'group' => 'Configuración'],
            ['name' => 'Actualizar usuarios', 'slug' => 'users.update', 'group' => 'Configuración'],
            ['name' => 'Deshabilitar usuarios', 'slug' => 'users.disable', 'group' => 'Configuración'],
            ['name' => 'Ver roles', 'slug' => 'roles.view', 'group' => 'Configuración'],
            ['name' => 'Asignar roles', 'slug' => 'roles.assign', 'group' => 'Configuración'],
            ['name' => 'Gestionar roles y permisos', 'slug' => 'roles.manage', 'group' => 'Configuración'],
            ['name' => 'Ver permisos', 'slug' => 'permissions.view', 'group' => 'Configuración'],
            ['name' => 'Gestionar sedes', 'slug' => 'branches.manage', 'group' => 'Sedes y salas'],
            ['name' => 'Gestionar catálogos', 'slug' => 'catalogs.manage', 'group' => 'Configuración'],
            ['name' => 'Gestionar configuración', 'slug' => 'settings.manage', 'group' => 'Configuración'],
            ['name' => 'Ver auditoría', 'slug' => 'audit.view', 'group' => 'Configuración'],
            ['name' => 'Gestionar marketing', 'slug' => 'marketing.manage', 'group' => 'Marketing'],
            ['name' => 'Gestionar finanzas', 'slug' => 'finance.manage', 'group' => 'Finanzas'],
            ['name' => 'Gestionar compliance', 'slug' => 'compliance.manage', 'group' => 'Compliance'],
            ['name' => 'Gestionar soporte', 'slug' => 'support.manage', 'group' => 'Soporte'],
        ];

        $permissions = collect($definitions)->mapWithKeys(fn (array $permission) => [
            $permission['slug'] => Permission::firstOrCreate(['slug' => $permission['slug']], [...$permission, 'guard_name' => 'web']),
        ])->all();

        $all = array_keys($permissions);
        $recruiting = ['dashboard.view', 'leads.view', 'leads.create', 'leads.manage', 'candidates.view', 'candidates.view_all', 'candidates.create', 'candidates.update', 'candidates.change_status', 'candidates.activate_access', 'candidates.add_note', 'candidates.schedule_interview', 'candidates.request_documents', 'candidates.approve', 'candidates.reject', 'candidates.convert', 'contracts.create', 'interviews.view', 'documents.view', 'documents.verify', 'calendar.view', 'calendar.manage'];
        $hr = ['dashboard.view', 'candidates.view', 'candidates.view_all', 'onboarding.view', 'onboarding.manage', 'documents.view', 'documents.manage', 'documents.request', 'documents.verify', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.approve', 'personal_data.view', 'personal_data.update', 'models.create_from_candidate', 'calendar.view'];
        $manager = ['dashboard.view', 'models.view_team', 'models.assign_monitor', 'models.change_monitor', 'monitors.view_team', 'shifts.view_team', 'shifts.manage_team', 'goals.create', 'goals.update', 'goals.assign', 'metrics.view_team', 'incidents.view_team', 'incidents.manage', 'rooms.view', 'rooms.manage_team', 'rooms.view_team', 'rooms.manage_reservations', 'rooms.manage_usage', 'calendar.view', 'calendar.manage', 'users.view', 'users.create', 'users.update', 'users.disable'];
        $monitor = ['dashboard.view', 'models.view_assigned', 'models.view_metrics', 'models.add_note', 'models.add_followup', 'models.view_goals', 'shifts.view_assigned', 'shifts.update_status', 'incidents.create', 'incidents.view_assigned', 'goals.view_assigned', 'documents.view_own', 'rooms.view_team', 'rooms.manage_reservations', 'rooms.manage_usage'];
        $model = ['dashboard.view', 'profile.view_own', 'profile.update_own', 'documents.view_own', 'shifts.view_own', 'goals.view_own', 'metrics.view_own', 'training.view', 'requests.create', 'requests.view_own', 'payments.view_own', 'rooms.view_own', 'rooms.reserve_own', 'rooms.use_own'];
        $candidate = ['profile.view_own', 'profile.update_own', 'documents.view_own', 'documents.upload_own', 'personal_data.view', 'personal_data.update', 'requests.create', 'requests.view_own'];
        $definitionsByRole = [
            ['name' => 'Super Administrador', 'slug' => 'super_admin', 'description' => 'Acceso global a toda la plataforma.', 'parent' => null, 'is_system' => true, 'permissions' => $all],
            ['name' => 'Administrador', 'slug' => 'admin', 'description' => 'Administra la operación y la configuración permitida.', 'parent' => 'super_admin', 'is_system' => true, 'permissions' => $all],
            ['name' => 'Reclutamiento', 'slug' => 'recruiter', 'description' => 'Gestiona captación, candidatos y entrevistas.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => $recruiting],
            ['name' => 'Recursos Humanos', 'slug' => 'hr', 'description' => 'Gestiona onboarding, documentos y contratación.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => $hr],
            ['name' => 'Manager / Coordinador', 'slug' => 'manager', 'description' => 'Administra equipos, salas, metas e incidentes.', 'parent' => 'operations_director', 'is_system' => false, 'permissions' => $manager],
            ['name' => 'Monitor', 'slug' => 'monitor', 'description' => 'Acompaña únicamente a modelos asignadas.', 'parent' => 'manager', 'is_system' => false, 'permissions' => $monitor],
            ['name' => 'Modelo', 'slug' => 'model', 'description' => 'Consulta y gestiona su propio portal.', 'parent' => 'monitor', 'is_system' => false, 'permissions' => $model],
            ['name' => 'Candidato', 'slug' => 'candidate', 'description' => 'Completa su aplicación y onboarding.', 'parent' => 'recruiter', 'is_system' => false, 'permissions' => $candidate],
            ['name' => 'Director de Operaciones', 'slug' => 'operations_director', 'description' => 'Supervisa la operación de equipos y salas.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => array_values(array_unique(array_merge($manager, $hr)))],
            ['name' => 'Marketing', 'slug' => 'marketing', 'description' => 'Gestiona las iniciativas de marketing.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => ['dashboard.view', 'marketing.manage']],
            ['name' => 'Finanzas', 'slug' => 'finance', 'description' => 'Gestiona procesos financieros autorizados.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => ['dashboard.view', 'finance.manage', 'payments.view_own']],
            ['name' => 'Compliance', 'slug' => 'compliance', 'description' => 'Supervisa cumplimiento y auditoría.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => ['dashboard.view', 'documents.view', 'documents.manage', 'documents.verify', 'contracts.view', 'compliance.manage', 'audit.view']],
            ['name' => 'Soporte', 'slug' => 'support', 'description' => 'Atiende solicitudes y soporte interno.', 'parent' => 'super_admin', 'is_system' => false, 'permissions' => ['dashboard.view', 'support.manage']],
        ];

        foreach ($definitionsByRole as $definition) {
            $role = Role::firstOrCreate(['slug' => $definition['slug']], [...collect($definition)->except(['parent', 'permissions'])->all(), 'guard_name' => 'web']);
            $role->givePermissionTo(collect($definition['permissions'])->map(fn ($slug) => $permissions[$slug])->all());
        }

        foreach ($definitionsByRole as $definition) {
            Role::where('slug', $definition['slug'])->update(['parent_role_id' => $definition['parent'] ? Role::where('slug', $definition['parent'])->value('id') : null]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
