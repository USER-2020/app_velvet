# Dominio y flujos de negocio

## Lead y aplicación pública

La persona envía `POST /apply` desde `resources/js/Pages/Public/Apply.jsx`. `RecruitmentController` valida y delega en `App\Actions\CreateLead`.

`CreateLead`:

- genera un código `VEL-LEAD-000001` a partir del siguiente id;
- guarda el tipo `MODEL` o `MONITOR`, datos de contacto, ciudad, disponibilidad, modalidad, experiencia e idioma;
- registra UTM, referer y landing page;
- marca consentimiento de privacidad;
- crea `LeadActivity` de tipo `created`;
- guarda el documento de identidad opcional en `lead-documents`.

Estados de lead (`App\Enums\LeadStatus`):

`NEW` Nueva → `CONTACTED` Contactada → `QUALIFIED` Calificada; también puede quedar `UNRESPONSIVE`, `DISCARDED` o `CONVERTED`.

## Conversión a candidato

`POST /admin/leads/{lead}/convert` usa `ConvertLeadToCandidate` dentro de una transacción y bloqueo de fila. Crea un candidato con código `VEL-CAN-000001`, copia el tipo del lead, lo deja en `NEW`, marca el lead como `CONVERTED` y registra actividades.

La operación es idempotente: si el lead ya tiene candidato, devuelve el candidato existente. Si aparece inconsistencia entre lead convertido y candidato ausente, responde con conflicto 409.

## Estados de candidato

`App\Enums\CandidateStatus` define:

`NEW`, `CONTACTED`, `PREQUALIFIED`, `INTERVIEW`, `EVALUATION`, `ADMITTED`, `WAITING`, `DISCARDED`, `ONBOARDING`, `CONTRACTING`, `INDUCTION`, `READY_TO_ACTIVATE`, `ACTIVE`, `WITHDRAWN`.

Flujo conceptual:

```text
NEW → CONTACTED → PREQUALIFIED → INTERVIEW → EVALUATION → ADMITTED
                                      └──────────────→ DISCARDED / WITHDRAWN
ADMITTED → WAITING → CONTRACTING → ONBOARDING → INDUCTION
                                             → READY_TO_ACTIVATE → ACTIVE
```

Las transiciones concretas están en `RecruitmentController`, `InterviewController`, `ContractingController` y las acciones de las páginas administrativas; siempre hay que revisar el estado actual y el permiso antes de agregar una transición.

## Precalificación e identidad

1. Reclutamiento usa `POST /admin/candidates/{candidate}/prequalification`.
2. Se envía `PrequalificationFormMail` con enlace firmado `/prequalification/{candidate}/{token}`.
3. La persona completa disponibilidad, modalidad, experiencia, motivación, portafolio/equipo y términos.
4. Si Didit está configurado, se crea sesión de identidad y se procesa el webhook `/webhooks/didit`; si no, se solicita documento.
5. Se guardan JSON de precalificación, documentos y actividad; el candidato pasa a `PREQUALIFIED`.
6. El token se invalida y se redirige a `/prequalification/completada`. La URL anterior debe dejar de ser reutilizable.

## Entrevistas

- El equipo genera franjas en `POST /admin/interviews/slots`.
- Se invita desde `POST /admin/candidates/{candidate}/interview-invitation`.
- La persona abre `/entrevista/{interview}/{token}` y reserva con `POST` en la misma URL.
- La reserva de `InterviewSlot` debe impedir dobles reservas mediante transacción/bloqueo.
- La entrevista queda `SCHEDULED`, el slot `BOOKED`, se registran actividades y se envía `InterviewScheduledMail` con evento de calendario.
- Se puede actualizar estado, reprogramar o cancelar slots según permisos.

## Evaluación, admisión, contratación y activación

- Las notas de entrevista se cargan desde el perfil de candidato y pueden moverlo a evaluación.
- La evaluación puede terminar en admisión o descarte.
- Contratación (`/admin/contracting`) genera franjas, agenda citas y permite reprogramar/completar.
- La persona puede cargar documentos de contratación mediante `/contratacion/{candidate}/{token}/documentos`.
- `Validaciones` reúne identidad y documentos pendientes; `Documentos` permite ver, previsualizar, descargar y actualizar estados.
- `ACTIVE` significa que el talento está habilitado para operación.

### Carga de documentos para contratación

La persona carga documentos mediante `/contratacion/{candidate}/{token}/documentos` con un onboarding de cinco pasos: identidad, certificación bancaria, documento tributario, formato firmado y soporte adicional opcional. Los cuatro primeros son obligatorios. Cada registro conserva `metadata.requirement_key`, puede reemplazarse sin borrar una versión anterior y el enlace reconoce requisitos ya recibidos.

El wizard es secuencial: guarda el archivo privado del paso actual y solo entonces desbloquea el siguiente. El estado real del documento se muestra como pendiente de revisión (`PENDING`), aprobado (`VERIFIED`) o requiere corrección (`REJECTED`). Un rechazo puede incluir `review_note`; un documento aprobado no puede reemplazarse desde el enlace público.

Cuando existe un archivo, el enlace público expone vista y descarga únicamente mediante URLs temporales firmadas. Ambas verifican además que el documento pertenece al candidato incluido en la URL; no existe un endpoint público sin firma para documentos privados.

## Rooms y portales

- Modelo: `/model/dashboard`, consulta rooms propias, crea reservas y actualiza su uso.
- Monitor: `/monitor/dashboard`, `/monitor/rooms` y `/monitor/models`; ve rooms/modelos según asignación y gestiona reservas/uso con permisos de equipo.
- Si el perfil de modelo/monitor no está activo, `EnsureActiveOperationalProfile` marca `training_mode` para usar el dataset aislado de `TrainingRecord`; si el usuario está desactivado, cierra la sesión.
- Administración: `/admin/rooms` gestiona catálogo, estado, reservas y uso.

### Inicio y finalización de turnos

Cuando una reserva pertenece a un turno operativo, el monitor puede iniciarla desde `/monitor/rooms`. El backend valida que la modelo esté asignada al monitor, cambia la reserva a `IN_USE`, abre `ShiftAttendance` y crea la `operational_session` vinculada. Al finalizar, cambia a `COMPLETED`, cierra asistencia/sesión y calcula los minutos efectivos.

El panel escucha `room.reservation.updated` y `operation.attendance.updated` por Ably para reflejar hora real de inicio, fin y duración. Si el perfil está en Sandbox, la acción solo actualiza `TrainingRecord` y no crea métricas reales.

## Operación interna y Velvet Master

- Un perfil operativo de modelo o monitor conserva onboarding, capacitaciones y asignaciones históricas modelo-monitor. Una modelo solo se activa al completar los requisitos de inducción.
- `/admin/operations` programa turnos, asocia una reserva de sala cuando aplica y mantiene asistencia, pausas, checklists, incidencias, solicitudes y novedades como registros distintos.
- `/admin/platforms` administra catálogos, cuentas Studio y cuentas de modelo. Las credenciales se cifran; los modos admitidos son `MANUAL` y `SIMULATED`.
- Un monitor puede asociar o retirar cuentas Studio de sus modelos asignadas desde `/monitor/models/{candidate}` con `studio_accounts.assign_assigned`; la gestión del catálogo, cuentas y credenciales permanece en Administración.
- `/admin/productivity` crea sesiones, transmisiones y métricas con origen `MANUAL`, `SIMULATED` o `IMPORT`. Las transmisiones simultáneas no se suman como horas: la sesión es la única fuente de duración. Un monitor solo puede validar métricas de una modelo actualmente asignada.
- `/admin/administration` registra sedes, activos, mantenimiento y evaluaciones. Los lotes para Finanzas quedan locales en `PENDING` y no se transmiten.

## Dashboard y métricas

`RecruitmentDashboardService` construye métricas para `Admin/Recruitment/Dashboard`:

- periodos: `today`, `7d`, `30d` por defecto, `month` y `custom` con `from`/`to` válidos;
- filtro de tipo: `ALL`, `MODEL` o `MONITOR`;
- métricas: leads nuevos, candidatos, entrevistas, evaluaciones, onboarding y activos;
- pipeline: leads, contactados, precalificados, entrevistas, evaluación, admitidos, onboarding y activos;
- distribución por tipo, gráfico diario, últimos candidatos, próximas entrevistas, actividad y atención pendiente.

## Notificaciones y tiempo real

`RealtimePublisher` crea notificaciones persistidas por roles mediante `NotificationService` y publica en Ably si `services.ably.api_key` está configurada. Canales derivados: leads, rooms, interviews y onboarding. La ausencia de Ably no debe bloquear la operación.

La barra superior obtiene contadores y notificaciones mediante props compartidas por `HandleInertiaRequests`; el usuario puede marcar todas como leídas en `/admin/notifications/read-all`.
