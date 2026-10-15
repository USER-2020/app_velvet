# Roles y permisos

## Regla general

Los permisos se definen en `database/seeders/RolePermissionSeeder.php`. El frontend recibe roles y la unión de permisos en `HandleInertiaRequests`; `resources/js/lib/authorization.js` expone `can(permission)`. El backend usa Spatie Laravel Permission mediante el middleware `permission:*` y el trait `HasRoles` en `User`.

`User::hasPermissionTo()` concede acceso global al rol `super_admin`, aunque el permiso no esté asignado explícitamente. `admin` recibe todos los permisos sembrados, pero no sustituye las comprobaciones especiales que estén implementadas por rol; por ejemplo, el control global de visitas guiadas llama explícitamente a `ensureSuperAdmin`.

## Roles sembrados

| Slug | Nombre | Alcance |
| --- | --- | --- |
| `super_admin` | Super Administrador | bypass global y control global de tours. |
| `admin` | Administrador | todos los permisos definidos por el seeder. |
| `recruiter` | Reclutamiento | captación, leads, candidatos, entrevistas y calendario. |
| `hr` | Recursos Humanos | onboarding, documentos, contratos y datos personales. |
| `manager` | Manager / Coordinador | equipos, rooms, metas, incidentes, usuarios y calendario. |
| `monitor` | Monitor | modelos asignadas, métricas/notas, rooms y seguimiento propio/equipo. |
| `model` | Modelo | portal propio, rooms propias, documentos, metas, turnos y solicitudes. |
| `candidate` | Candidato | perfil, datos, documentos propios y solicitudes. |
| `operations_director` | Director de Operaciones | unión de manager y parte de RR. HH. |
| `marketing` | Marketing | dashboard y `marketing.manage`. |
| `finance` | Finanzas | dashboard, `finance.manage`, `payments.view_own`. |
| `compliance` | Compliance | documentos, contratos, compliance y auditoría. |
| `support` | Soporte | dashboard y `support.manage`. |

## Grupos de permisos relevantes

### Reclutamiento y candidatos

`dashboard.view`, `leads.view`, `leads.create`, `leads.manage`, `candidates.view`, `candidates.view_all`, `candidates.create`, `candidates.update`, `candidates.change_status`, `candidates.activate_access`, `candidates.add_note`, `candidates.schedule_interview`, `candidates.request_documents`, `candidates.approve`, `candidates.reject`, `candidates.convert`, `interviews.view`, `calendar.view`, `calendar.manage`.

### Onboarding, documentos y contratos

`onboarding.view`, `onboarding.manage`, `documents.view`, `documents.manage`, `documents.request`, `documents.verify`, `contracts.view`, `contracts.create`, `contracts.update`, `contracts.approve`.

### Personas y operación

`models.create_from_candidate`, `models.view_assigned`, `models.view_all`, `models.view_team`, `models.assign_monitor`, `models.change_monitor`, `models.add_note`, `models.add_followup`, `monitors.view_team`, `rooms.view`, `rooms.manage`, `rooms.manage_team`, `rooms.view_own`, `rooms.reserve_own`, `rooms.use_own`, `rooms.view_team`, `rooms.manage_reservations`, `rooms.manage_usage`.

### Portal y configuración

`profile.view_own`, `profile.update_own`, `documents.view_own`, `documents.upload_own`, `training.view`, `requests.create`, `requests.view_own`, `personal_data.view`, `personal_data.update`, `users.view`, `users.create`, `users.update`, `users.disable`, `roles.view`, `roles.assign`, `roles.manage`, `permissions.view`, `settings.manage`, `audit.view`.

### Velvet Master

`shifts.view_team`, `shifts.manage_team`, `metrics.view_team`, `incidents.view_team`, `incidents.manage`, `branches.manage`, `catalogs.manage` y `finance.manage` controlan los módulos nuevos. `models.view_metrics` permite a un monitor validar solamente métricas de sus modelos con una asignación activa; no concede acceso al equipo completo.

También existen permisos para metas, métricas, turnos, incidentes, marketing, finanzas, compliance, soporte, pagos, sedes y catálogos. El seeder es la referencia exacta de nombres y asignaciones.

## Seeder incremental de Velvet Master

`VelvetMasterOperationalPermissionsSeeder` se ejecuta después de `RolePermissionSeeder`. El proyecto usa Spatie Laravel Permission: usa `Permission::firstOrCreate()`, `Role::givePermissionTo()` y limpia `PermissionRegistrar` para preservar personalizaciones administrativas. No elimina pivots ni crea roles.

Sus permisos cubren asistencia, checklists, plataformas, cuentas Studio, importaciones/simulaciones, sesiones, transmisiones, productividad, inventario, mantenimiento, evaluaciones, reportes y exportaciones locales. Consulta `docs/VELVET_MASTER_PERMISSIONS.md` para la matriz de mínimo privilegio vigente.

## Usuarios de prueba

`php artisan db:seed --class=TestUsersSeeder` ejecuta también `RolePermissionSeeder` y crea/actualiza usuarios activos y verificados:

`superadmin@velvet.test`, `administrador@velvet.test`, `reclutamiento@velvet.test`, `rh@velvet.test`, `reclutamiento.rh@velvet.test`, `manager@velvet.test`, `monitor@velvet.test`, `modelo@velvet.test`, `candidato@velvet.test`, `director.operaciones@velvet.test`, `marketing@velvet.test`, `finanzas@velvet.test`, `compliance@velvet.test`, `soporte@velvet.test`.

La contraseña viene de `VELVET_TEST_USERS_PASSWORD`; no se incluye ningún valor en esta skill. Estas cuentas son únicamente para desarrollo y no deben tratarse como credenciales de producción.

## Cómo diagnosticar un 403

1. Identificar la ruta y su middleware en `routes/web.php`.
2. Confirmar el rol del usuario y el slug exacto del permiso.
3. Revisar el alias `permission` de Spatie en `bootstrap/app.php` y `User::hasPermissionTo`.
4. Verificar si la acción tiene un permiso distinto al de lectura.
5. Revisar las reglas especiales de `GuidedTourSettingsController`, `EnsureActiveOperationalProfile` y controladores de alcance/asignación.
