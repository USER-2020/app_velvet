# Permisos operativos de Velvet Master

Actualizado: 2026-10-21.

## Arquitectura y seeder responsable

El proyecto usa Spatie Laravel Permission 6.25 con el guard `web`. `App\\Models\\Role` y `App\\Models\\Permission` extienden los modelos de Spatie; `User` usa `HasRoles`. Los slugs históricos se conservan como identificadores de autorización, por lo que las rutas actuales continúan usando `hasPermissionTo('modulo.accion')`.

La migración `2026_10_12_030000_adopt_spatie_permission_without_losing_legacy_access` agrega `guard_name`, crea `role_has_permissions`, `model_has_roles` y `model_has_permissions`, y copia los datos desde `role_permission` y `user_role`. Las tablas históricas se conservan y el `down()` no borra datos de acceso.

`RolePermissionSeeder` conserva los permisos base mediante la API aditiva de Spatie. `VelvetMasterOperationalPermissionsSeeder` crea únicamente permisos granulares de Velvet Master mediante `Permission::firstOrCreate()` y asigna con `givePermissionTo()`. Ambos limpian `PermissionRegistrar` antes y después; no revocan personalizaciones administrativas.

`DatabaseSeeder` ejecuta el seeder operativo inmediatamente después del seeder base, antes de usuarios y datos de entrenamiento.

## Permisos nuevos y roles asignados

| Módulo | Permiso | Roles asignados |
| --- | --- | --- |
| Asistencia | `attendance.manage` | admin, operations_director, manager |
| Habitaciones | `room_checklists.manage` | admin, operations_director, manager |
| Plataformas webcam | `platforms.view` | admin, operations_director, compliance |
| Plataformas webcam | `platforms.manage` | admin |
| Cuentas Studio | `studio_accounts.manage` | admin |
| Plataformas webcam | `platform_imports.manage` | admin |
| Configuración e integraciones | `platform_simulations.run` | admin |
| Sesiones de transmisión | `sessions.manage` | admin, operations_director, manager |
| Sesiones de transmisión | `streams.manage` | admin, operations_director, manager |
| Productividad | `productivity.manage` | admin, operations_director, manager |
| Productividad | `productivity.validate_assigned` | monitor |
| Inventario y mantenimiento | `inventory.manage` | admin, operations_director, manager |
| Inventario y mantenimiento | `maintenance.manage` | admin, operations_director, manager |
| Modelos operativas | `performance_reviews.manage` | admin, operations_director, manager, hr |
| Reportes operativos | `reports.operational.view` | admin, operations_director, manager, finance, compliance |
| Configuración e integraciones | `integrations.finance_export` | admin, finance |
| Cuentas Studio asignadas | `studio_accounts.assign_assigned` | monitor |
| VELVET Agent | `stations.enroll` | admin |
| VELVET Agent | `stations.configure` | admin |
| VELVET Agent | `stations.revoke` | admin |
| VELVET Agent | `stations.commands` | admin |

El rol `super_admin` conserva su bypass global en `User::hasPermissionTo()` y no requiere pivots adicionales. Reclutamiento, modelo, candidato, marketing y soporte no reciben permisos nuevos automáticamente.

## Permisos base reutilizados

No se duplicaron permisos que ya existen en el seeder base:

| Módulo | Permisos reutilizados |
| --- | --- |
| Modelos operativas y monitores | `models.view_*`, `models.assign_monitor`, `models.change_monitor`, `models.add_followup`, `models.view_metrics` |
| Turnos | `shifts.view_team`, `shifts.manage_team` |
| Habitaciones | `rooms.view`, `rooms.manage_*` |
| Incidencias y solicitudes | `incidents.create`, `incidents.manage`, `requests.create` |
| Capacitaciones | `onboarding.view`, `onboarding.manage`, `training.view` |
| Estudios y sedes | `branches.manage` |
| Finanzas | `finance.manage` |

## Aplicación de autorización

- Rutas y controladores protegen acciones con el middleware `permission:*` de Spatie y comprobaciones de backend; el menú solo refleja permisos, no sustituye esas validaciones.
- Un monitor puede validar una métrica solo si posee `productivity.validate_assigned` y tiene una asignación activa con la modelo de la sesión.
- Las simulaciones de plataformas no contactan proveedores externos. Credenciales y cuentas Studio siguen reservadas a administración.
- `studio_accounts.assign_assigned` solo permite al monitor asociar o retirar cuentas Studio existentes de las modelos que tiene asignadas. No concede `studio_accounts.manage`, acceso a credenciales ni capacidad para crear o configurar cuentas.

## Turnos, reservas y sesiones operativas

La migración `2026_10_12_080000_add_room_reservation_to_operational_sessions.php` agrega el vínculo opcional entre una reserva de room y una `operational_session`.

- Al iniciar una reserva, el backend valida la asignación del monitor, cambia la reserva a `IN_USE`, abre la asistencia y crea la sesión operativa.
- Al finalizar, cambia la reserva a `COMPLETED`, cierra asistencia y sesión, y guarda la duración efectiva.
- Los eventos `room.reservation.updated` y `operation.attendance.updated` actualizan los paneles en tiempo real mediante Ably.
- Las acciones realizadas en Sandbox de inducción permanecen en `TrainingRecord` y no afectan sesiones, asistencia ni métricas reales.

## Verificación

`VelvetMasterOperationalPermissionsSeederTest` prueba idempotencia, preservación de pivots existentes, guard por defecto, mínimo privilegio y respuestas 403 para módulos nuevos. Ejecutar:

```powershell
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder
php artisan test
```

## Autorización de estaciones Agent

Los permisos `stations.*` se registran en `VelvetMasterOperationalPermissionsSeeder`. Las rutas `/admin/agent` usan `EnsurePermission`, además de sesión web, usuario activo, correo verificado, CSRF y HTTPS. El bypass existente de `super_admin` continúa aplicando. Los demás roles no reciben estos cuatro permisos automáticamente.

El login OAuth del Agent también exige `stations.enroll` para autorizar una estación desde el navegador. La sesión OAuth vincula el usuario autorizado con la estación y la sede, pero no convierte al dispositivo en un usuario ni modifica sus roles. La revocación del access token invalida también su refresh token.

Los dispositivos no heredan roles de personas. `AuthenticateAgent` comprueba la credencial SHA-256, revocación y ability en cada petición, antes de cualquier replay idempotente:

| Ruta | Ability de dispositivo |
| --- | --- |
| POST `/api/agent/heartbeat` | `agent:heartbeat` |
| GET `/api/agent/configuration` | `agent:configuration` |
| POST `/api/agent/events` | `agent:events` |
| POST `/api/agent/commands/{id}/result` | `agent:results` |

El enrolamiento usa un token temporal, no estas abilities. El estudio procede del token emitido por administración y el heartbeat debe coincidir con la instalación autenticada. Los resultados solo pueden modificar reservas de esa estación. No hay pertenencias de usuario a estudios implementadas: los permisos administrativos son globales, no una política multiestudio.

Referencia: [integración Agent](AGENT_INTEGRATION.md). `AgentIntegrationTest` comprueba permisos administrativos, aislamiento, abilities y revocación antes de replay.
