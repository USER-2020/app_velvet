# Arquitectura backend, rutas y páginas

## Flujo de una petición

1. La ruta de `routes/web.php` aplica `auth`, `verified`, `permission:*` y, para portales, `active.profile`.
2. El controlador valida y ejecuta una acción/servicio o actualiza modelos Eloquent.
3. Se registran actividades, correos, notificaciones o publicación en tiempo real cuando corresponde.
4. La respuesta es redirect con flash o `Inertia::render(...)` con props.
5. React resuelve `resources/js/Pages/{component}.jsx` mediante `resources/js/app.jsx`.

## Controladores y responsabilidades

| Controlador | Responsabilidad |
| --- | --- |
| `RecruitmentController` | aplicación pública, dashboard, leads, candidatos, procesos, validaciones, conversión y acciones del proceso. |
| `InterviewController` | slots, invitaciones, reserva pública, estados y reprogramación. |
| `ContractingController` | slots/citas de contratación y documentos públicos. |
| `CalendarController` | eventos y recordatorios. |
| `RoomController` | rooms administrativas, reservas y uso. |
| `PortalRoomController` | dashboards y acciones de modelo/monitor, token realtime y documentos propios. |
| `DocumentsController` | repositorio documental y estados. |
| `AccessControlController` | roles y permisos. |
| `UserAccessController` | usuarios internos. |
| `GuidedTourSettingsController` | interruptor global exclusivo de superadmin. |
| `TourProgressController` | progreso individual de tours, claves permitidas y versión. |
| `NotificationController` | bandeja e historial de notificaciones. |
| `TrainingDataController` | dataset de inducción. |
| `OperationalProfileController` | perfiles operativos, capacitaciones y asignaciones de monitor con historial. |
| `StudioOperationsController` | turnos, asistencia, checklists, incidencias, solicitudes y novedades. |
| `MasterOperationsController` | plataformas Studio, importaciones/simulaciones, sesiones, métricas, inventario, mantenimiento, evaluaciones y lote local para Finanzas. |
| `OnboardingDraftController` | borradores del formulario público. |
| `CookieConsentController` | consentimiento de cookies. |

## Rutas públicas principales

| Método | Ruta | Propósito |
| --- | --- | --- |
| GET | `/` | login. |
| GET/POST | `/apply` | formulario y creación de lead. |
| GET/POST/DELETE | `/apply/draft` | guardar, consultar o eliminar borrador. |
| GET | `/prequalification/{candidate}/{token}` | formulario firmado. |
| POST | `/prequalification/{candidate}/{token}` | completar precalificación. |
| POST | `/prequalification/{candidate}/{token}/identity-session` | iniciar identidad Didit. |
| POST | `/webhooks/didit` | webhook Didit. |
| GET/POST | `/entrevista/{interview}/{token}` | reserva de entrevista firmada. |
| GET/POST | `/contratacion/{candidate}/{token}/documentos` | carga pública de documentos. |
| GET | `/politica-de-privacidad`, `/terminos-y-condiciones` | páginas legales. |

## Rutas autenticadas de portal

- `/dashboard` redirige por rol a `/admin/recruitment`, `/monitor/dashboard` o `/model/dashboard`.
- Modelo: `/model/dashboard` y reservas propias.
- Monitor: `/monitor/dashboard`, `/monitor/rooms`, `/monitor/models/{candidate}` y reservas/uso de equipo.
- `/portal/documents/{document}/preview|download` para documentos propios.
- `/realtime/token` entrega token de realtime cuando aplica.

Acciones de monitor sobre reservas y plataformas:

- `PATCH /monitor/rooms/reservations/{reservation}/status` inicia, finaliza o cancela una reserva según la asignación y permisos del monitor.
- `POST /monitor/models/{candidate}/platform-accounts` asocia una cuenta Studio existente a una modelo asignada.
- `DELETE /monitor/models/{candidate}/platform-accounts/{modelPlatformAccount}` retira esa asociación.

`OperationalSessionService` mantiene sincronizados la reserva, `ShiftAttendance`, `OperationalShift` y `operational_sessions`. La migración `2026_10_12_080000_add_room_reservation_to_operational_sessions.php` conserva el vínculo opcional y único con la reserva.

## Rutas administrativas

- Dashboard: `/admin`, `/admin/recruitment` (`dashboard.view`).
- Reclutamiento: `/admin/leads`, `/admin/leads/{lead}`, `/admin/candidates`, `/admin/candidates/{candidate}`, `/admin/interviews`.
- Proceso: `/admin/processes`, `/admin/validations`, `/admin/documents`, `/admin/contracting`.
- Operación: `/admin/calendar`, `/admin/rooms`, `/admin/operations`, `/admin/platforms`, `/admin/productivity`, `/admin/administration`.
- Configuración: `/admin/workflows`, `/admin/training-data`, `/admin/access`, `/admin/users`.
- Visitas: `/admin/guided-tours`, restringido en código a `super_admin`/`super-admin`.
- Notificaciones: `/admin/notifications`, `/admin/notifications/history`, `/admin/notifications/read-all`.

Las acciones mutables usan `PUT`, `PATCH`, `POST` o `DELETE` y suelen tener un permiso más específico que la consulta del módulo. La matriz completa y vigente está en `routes/web.php` y `RolePermissionSeeder.php`.

## Datos principales

| Modelo | Relaciones/uso |
| --- | --- |
| `User` | roles, notificaciones y candidato; `hasPermissionTo` da bypass a superadmin. |
| `Role` / `Permission` | Spatie Laravel Permission: pivots activos `model_has_roles`, `role_has_permissions` y `model_has_permissions`; conserva los pivots históricos `user_role` y `role_permission` por compatibilidad. Los roles pueden tener padre. |
| `Lead` | solicitud pública, actividades, documentos y candidato. |
| `Candidate` | proceso, tipo, usuario, actividades, entrevistas y citas. |
| `LeadActivity` / `CandidateActivity` | historial operativo con descripción y metadata JSON. |
| `LeadDocument` | archivos de identidad/requisitos asociados al lead. |
| `Interview` / `InterviewSlot` | invitación, reserva, estado y agenda. |
| `ContractSlot` / `ContractAppointment` | contratación. |
| `CalendarEvent` | eventos/recordatorios. |
| `Room` / `RoomReservation` / `RoomUsageLog` | catálogo, reservas y uso. |
| `TrainingRecord` | dataset aislado de sandbox. |
| `Notification` | notificaciones dirigidas por usuario/rol. |
| `OnboardingDraft` / `CookieConsent` | continuidad del formulario público y preferencias. |
| `UserTourProgress` / `SystemSetting` | progreso individual y configuración global de visitas. |
| `OperationalProfile` / `ModelMonitorAssignment` / `OperationalTrainingRecord` | expediente operativo, historial de monitor y capacitaciones. |
| `OperationalShift` / `ShiftAttendance` / `RoomOperationChecklist` | planificación, asistencia y control de sala. |
| `PlatformCatalog` / `StudioAccount` / `ModelPlatformAccount` | catálogo, cuentas Studio cifradas y vínculos con modelos. |
| `PlatformImportBatch` / `PlatformSyncRun` | trazabilidad de importaciones y simulaciones, sin llamadas externas. |
| `operational_sessions` / `stream_sessions` / `production_metrics` | producción con origen y validación auditada. |
| `branches` / `inventory_assets` / `maintenance_records` / `performance_reviews` | recursos y control interno. |

## Fuente de verdad para cambios

Antes de modificar una funcionalidad, revisar el controlador, middleware de la ruta, modelo/migración, página React y pruebas relacionadas. No usar únicamente el menú lateral como mapa de permisos: el menú es una vista filtrada del frontend.
