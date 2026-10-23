# Guía de plataforma · The Velvet Studio

Documento de referencia para operación, QA y desarrollo. Describe el estado
actual de los flujos internos y públicos de la plataforma.

> Mantener este documento actualizado cuando cambien módulos, permisos,
> transiciones de estado, rutas públicas, pruebas o visitas guiadas.

## 1. Accesos y roles

Los roles y permisos se mantienen en
`database/seeders/RolePermissionSeeder.php`. Una persona puede tener varios
roles; sus permisos son la unión de los permisos otorgados por cada rol.

| Rol | Alcance principal |
| --- | --- |
| Super Administrador | Acceso global. Es el único que puede activar o desactivar las visitas guiadas globales. |
| Administrador | Operación y configuración autorizada. |
| Reclutamiento | Leads, candidatos, entrevistas, agenda y avance de selección. |
| Recursos Humanos | Onboarding, documentos, validaciones y contratación. |
| Manager / Coordinador | Equipo, salas, reservas y operación de personas asignadas. |
| Monitor | Portal con rooms y modelos asignadas. |
| Modelo | Portal propio, reservas y documentos propios. |
| Candidato | Aplicación y pasos públicos autorizados. |
| Director de Operaciones, Marketing, Finanzas, Compliance y Soporte | Accesos especializados según sus permisos. |

El Super Administrador no depende de una lista particular de permisos:
`User::hasPermissionTo()` le concede acceso global.

## 2. Usuarios de prueba

El seeder `TestUsersSeeder` crea o actualiza cuentas verificadas y activas para
cada rol. Se ejecuta con:

```bash
php artisan db:seed --class=TestUsersSeeder
```

La contraseña se obtiene de `VELVET_TEST_USERS_PASSWORD`; en desarrollo su
valor predeterminado es `ChangeMe123!`. Estas cuentas usan el dominio `.test`.

| Cuenta | Rol o roles |
| --- | --- |
| `superadmin@velvet.test` | Super Administrador |
| `administrador@velvet.test` | Administrador |
| `reclutamiento@velvet.test` | Reclutamiento |
| `rh@velvet.test` | Recursos Humanos |
| `reclutamiento.rh@velvet.test` | Reclutamiento + Recursos Humanos |
| `manager@velvet.test` | Manager / Coordinador |
| `monitor@velvet.test` | Monitor |
| `modelo@velvet.test` | Modelo |
| `candidato@velvet.test` | Candidato |
| `director.operaciones@velvet.test` | Director de Operaciones |
| `marketing@velvet.test` | Marketing |
| `finanzas@velvet.test` | Finanzas |
| `compliance@velvet.test` | Compliance |
| `soporte@velvet.test` | Soporte |

El seeder usa `syncRoles()`: al ejecutarlo de nuevo, cada cuenta queda con
exactamente los roles definidos en el archivo.

## 3. Módulos internos

| Área | Módulo | Propósito |
| --- | --- | --- |
| Reclutamiento | Pipeline | Indicadores y prioridades de leads y candidatos. |
| Reclutamiento | Leads | Solicitudes públicas recibidas y su seguimiento inicial. |
| Reclutamiento | Candidatos | Perfil, proceso, documentos y acciones de cada candidata. |
| Reclutamiento | Entrevistas | Franjas, invitaciones, agenda y reprogramaciones. |
| Reclutamiento | Contratación | Citas de contratación y documentación de ingreso. |
| Reclutamiento | Calendario | Eventos, vencimientos y recordatorios operativos. |
| Onboarding | Procesos | Seguimiento de ingreso y preparación para activación. |
| Onboarding | Validaciones | Revisión de identidad y requisitos/documentos. |
| Onboarding | Documentos | Consulta y gestión documental autorizada. |
| Personas | Modelos y Monitores | Directorios filtrados desde Candidatos según el tipo. |
| Operación | Rooms | Disponibilidad, reservas y uso de salas. |
| Configuración | Workflows | Mapa operativo de flujos de admisión, entrevistas, contratación, rooms, calendario, acceso y activación. |
| Configuración | Datos de prueba | Sandbox de inducción para rooms, modelos y reservas. |
| Configuración | Usuarios | Administración de cuentas internas. |
| Configuración | Roles y permisos | Administración de roles y permisos. |
| Guías | Visitas guiadas | Interruptor global exclusivo del Super Administrador. |

## 4. Flujo de reclutamiento

### 4.1 Aplicación pública y lead

1. La persona completa `/apply`.
2. Se crea un **Lead** y se registra actividad de creación y origen de campaña
   cuando existe UTM.
3. El equipo gestiona el lead desde **Leads**.
4. Un lead puede estar en: `NEW`, `CONTACTED`, `QUALIFIED`, `UNRESPONSIVE`,
   `DISCARDED` o `CONVERTED`.
5. Al convertirlo se crea un **Candidate** sin duplicar la conversión si la
   solicitud se repite.

### 4.2 Proceso del candidato

Estados disponibles:

```text
NEW → CONTACTED → PREQUALIFIED → INTERVIEW → EVALUATION → ADMITTED
                                                    ↓
             DISCARDED / WITHDRAWN       WAITING → CONTRACTING → ONBOARDING
                                                                → INDUCTION
                                                                → READY_TO_ACTIVATE
                                                                → ACTIVE
```

Las transiciones reales se controlan por las acciones, estado actual y
permisos disponibles. Cada cambio relevante crea actividad para el historial y
puede notificar al equipo en tiempo real.

### 4.3 Precalificación

1. Reclutamiento solicita el formulario desde el perfil del candidato.
2. La persona recibe un enlace temporal y firmado:
   `/prequalification/{candidate}/{token}`.
3. Completa disponibilidad, modalidad, experiencia, motivación y requisitos.
4. Si Didit está configurado, se exige la verificación de identidad aprobada;
   en caso contrario se solicita el documento de identidad.
5. Al enviar, se guardan los datos, documentos y actividad, y el candidato
   pasa a `PREQUALIFIED`.
6. El token se invalida para impedir reutilización y la respuesta redirige a
   `/prequalification/completada`, una pantalla pública de confirmación.

No debe redirigirse de nuevo a la URL firmada después del envío: al invalidarse
el token, esa URL debe responder 403 de forma intencional.

### 4.4 Entrevistas

1. El equipo genera franjas horarias de una hora en **Entrevistas**.
2. Envía una invitación a una candidata precalificada.
3. La candidata recibe un enlace temporal y firmado:
   `/entrevista/{interview}/{token}`.
4. Elige una franja disponible; la reserva se bloquea mediante transacción para
   evitar que dos personas tomen la misma hora.
5. La entrevista queda `SCHEDULED`, la franja queda `BOOKED` y se registra la
   actividad y el correo de confirmación con evento de calendario.
6. El token de invitación se invalida y la respuesta redirige a
   `/entrevista/completada`.
7. El equipo puede reprogramar una entrevista: se libera la franja anterior y
   se genera una nueva invitación cuando existen horarios alternativos.

El módulo muestra tarjetas responsivas en pantallas menores a escritorio y
tabla en pantallas amplias.

### 4.5 Evaluación, admisión y activación

1. Después de la entrevista se cargan las notas y el candidato pasa a
   `EVALUATION` cuando corresponde.
2. Tras la evaluación puede admitirse (`ADMITTED`) o descartarse.
3. La admisión habilita los pasos operativos de espera, contratación,
   onboarding, inducción, revisión final y activación.
4. `ACTIVE` representa el talento habilitado para la operación.

## 5. Contratación, documentos y validaciones

- **Contratación** permite gestionar franjas y citas de formalización.
- La persona recibe un enlace firmado para cargar documentación de contratación
  cuando el flujo lo requiere.
- **Validaciones** concentra resultados de identidad y documentos pendientes.
- El modal de resultado Didit está preparado para galería de imágenes,
  desplazamiento interno y pantallas pequeñas.
- **Documentos** permite consultar, previsualizar, descargar y actualizar el
  estado de los archivos según permisos.

## 6. Calendario y Rooms

### Calendario

El calendario reúne eventos operativos, recordatorios y entrevistas. Las
acciones de crear, actualizar, completar o eliminar requieren
`calendar.manage`; la consulta requiere `calendar.view`.

### Rooms

Rooms maneja disponibilidad y reservas. Los permisos distinguen consulta,
gestión de salas, reserva propia, uso propio, consulta de equipo y gestión de
reservas/uso del equipo. Modelos y monitores cuentan con rutas de portal
separadas según su alcance.

## 7. Visitas guiadas con Driver.js

Las guías están definidas en `resources/js/lib/productTours.js` y se presentan
según ruta y permisos.

Principios actuales:

- Cada módulo tiene una clave de tour; por ejemplo `candidates` para el listado
  y `candidate-flow` para el perfil. Completar una no completa la otra.
- `ProductTour` espera a que los elementos requeridos estén montados antes de
  iniciar la guía.
- Al finalizar se marca como `completed`; al cerrar se marca como `skipped`.
  Ambos estados evitan que el tour se abra automáticamente otra vez para esa
  versión.
- El botón flotante `?` permite repetir la guía del módulo actual.
- El progreso se guarda por usuario en `user_tour_progresses`.
- El Super Administrador puede activar o desactivar todas las guías desde
  **Visitas guiadas** (`/admin/guided-tours`). El ajuste se guarda en
  `system_settings` y no borra los progresos individuales.
- Si las guías están desactivadas, no inicia ningún tour ni aparece el botón
  para repetirlo.

Al crear una funcionalidad nueva se debe actualizar:

1. `productTours` con los pasos y selectores del nuevo flujo.
2. `tourKeyForPath` con su ruta.
3. `TourProgressController::TOUR_KEYS` con la nueva clave.
4. Pruebas de flujo si el comportamiento es crítico.

La convención completa para agentes y desarrollo también vive en `AGENTS.md`.

## 8. Notificaciones y tiempo real

Los cambios relevantes de leads, candidatas, entrevistas y operación se
publican mediante `RealtimePublisher`. Cuando existe configuración de Ably se
emiten eventos en los canales configurados; sin esa configuración la operación
funciona y no se bloquea por la ausencia del servicio externo.

Las notificaciones internas se calculan y muestran en el encabezado y pueden
enlazar a la persona o módulo relacionado.

## 9. Pruebas automatizadas

Las pruebas Laravel usan SQLite en memoria por configuración de `phpunit.xml`:
esto es deliberado y no usa ni modifica la base MySQL de desarrollo o
producción.

La suite cubre, entre otros:

- autenticación, verificación y perfil;
- roles, permisos y acceso;
- activación/desactivación global de visitas guiadas;
- aplicación pública, leads y conversión de candidatos;
- preclasificación y su confirmación pública;
- franjas, invitación, reserva y confirmación de entrevista;
- workflows y datos del dashboard;
- integración Agent: tokens de un uso, aislamiento, revocación, idempotencia, telemetría, configuración y resultados de comandos.

Ejecutar localmente:

```bash
php artisan test
node --test tests/js/*.test.mjs
npm run build
```

## 10. Integración continua con GitHub Actions

El workflow está en `.github/workflows/ci.yml` y corre en cada `push` y
`pull_request`.

| Job | Validaciones |
| --- | --- |
| Backend · PHP 8.2 | Instalación de Composer, validación estricta de `composer.json` y `php artisan test`. |
| Frontend · Node 22 | `npm ci`, `npm run build` y `node --test tests/js/*.test.mjs`. |

Para impedir merges con fallos se deben requerir ambos checks en:

```text
GitHub → Settings → Branches → Branch protection rules
```

La verificación estricta de Pint está pendiente de una normalización global de
formato: no debe activarse como requisito hasta que el código existente cumpla
el estándar sin introducir un cambio masivo no funcional.

## 11. Checklist para un cambio nuevo

1. Definir el permiso y el rol que puede consultar o gestionar la función.
2. Implementar backend, validación, rutas y persistencia necesaria.
3. Construir una interfaz responsive, sin solapamientos ni desplazamiento
   horizontal innecesario.
4. Añadir la guía Driver.js del módulo o flujo.
5. Crear o actualizar pruebas de feature/regresión.
6. Ejecutar `php artisan test` y `npm run build`.
7. Actualizar este documento si cambia la operación del equipo.

## 12. Integración con VELVET Agent

Actualización: 2026-10-21. Las APIs de Master están implementadas; su activación requiere configurar HTTPS, estudios válidos, migraciones, permisos y scheduler. Consulte [la guía de integración](AGENT_INTEGRATION.md) y [OpenAPI 0.1.1](agent.openapi.json).

- Agent se enrola con un token temporal de un uso; recibe una credencial independiente por estación. No inicia sesión como usuario de Master.
- Las cinco rutas de dispositivo están bajo `/api/agent`: enrolamiento, heartbeat, configuración, eventos y resultados de comandos. Exigen HTTPS y los POST requieren `Idempotency-Key`; las peticiones posteriores al enrolamiento usan Bearer.
- Las acciones administrativas son rutas JSON bajo `/admin/agent`, con sesión web, CSRF y permisos `stations.*`. No existe aún pantalla de administración de estaciones.
- La estación hereda el estudio del token. `AGENT_STUDIO_IDS` es el registro externo autorizado mientras no exista una entidad Studio propia en Master.
- `agent:maintain` se ejecuta cada minuto desde el scheduler para caducidad, limpieza de respuestas cifradas y detección offline.
- Solo se reservan comandos `RefreshStatus`; su entrega a Agent y los canales Reverb/WSS están pendientes. No hay shell remoto ni escritura OBS.

`AgentIntegrationTest` cubre estos flujos. La prueba frontend `tests/js/auth-csrf.test.mjs` verifica el token XSRF actualizado tras cambios de sesión; cerrar sesión redirige a `/login`.
