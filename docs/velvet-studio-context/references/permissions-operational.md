# Permisos operativos de Velvet Master

El RBAC usa Spatie Laravel Permission 6.25: `Role` y `Permission` extienden sus modelos, `User` usa `HasRoles` y el guard es `web`. Los slugs históricos siguen siendo la interfaz de autorización. La migración aditiva conserva `role_permission` y `user_role`, agrega `guard_name` y copia los datos a `role_has_permissions` y `model_has_roles`.

`VelvetMasterOperationalPermissionsSeeder` se ejecuta después de `RolePermissionSeeder`. Usa `Permission::firstOrCreate()`, `givePermissionTo()` y `PermissionRegistrar::forgetCachedPermissions()` para no borrar permisos ni personalizaciones existentes.

| Grupo | Permisos incrementales | Roles con asignación |
| --- | --- | --- |
| Asistencia y salas | `attendance.manage`, `room_checklists.manage` | admin, operations_director, manager |
| Plataformas | `platforms.view`, `platforms.manage`, `studio_accounts.manage`, `platform_imports.manage`, `platform_simulations.run` | lectura: admin/operations_director/compliance; gestión y simulación: admin |
| Sesiones y productividad | `sessions.manage`, `streams.manage`, `productivity.manage`, `productivity.validate_assigned`, `reports.operational.view` | gestión: admin/operations_director/manager; validación asignada: monitor; reportes: admin/operations_director/manager/finance/compliance |
| Recursos | `inventory.manage`, `maintenance.manage`, `performance_reviews.manage` | admin, operations_director, manager; evaluaciones también hr |
| Integración | `integrations.finance_export` | admin, finance |
| Cuentas Studio asignadas | `studio_accounts.assign_assigned` | monitor, solo modelos asignadas |

Se reutilizan los permisos base para modelos y monitores, turnos, rooms, incidencias, solicitudes, capacitaciones, sedes y control financiero general. `super_admin` conserva bypass global; no se le requieren pivots adicionales. Reclutamiento, modelo, candidato, marketing y soporte no reciben permisos operativos nuevos.

Consulta `docs/VELVET_MASTER_PERMISSIONS.md` en el repositorio para la matriz completa y las rutas protegidas.

`studio_accounts.manage` sigue reservado a administración. El permiso `studio_accounts.assign_assigned` no permite crear cuentas, modificar catálogos, cambiar credenciales ni consultar secretos cifrados.
