# VELVET Master ↔ VELVET Agent

Actualizado: 2026-10-22.

Implementación del contrato Agent 0.1.0 recibido, con precisiones en [agent.openapi.json](agent.openapi.json) (versión Master 0.2.0). La implementación está en Master; activar las APIs requiere completar la preparación de despliegue. El repositorio Agent debe consumir el flujo OAuth descrito abajo; el enrolamiento por token temporal permanece como compatibilidad.

## Arquitectura y autenticación

Master usa Laravel 12, sesiones web, Inertia y Spatie con permisos por `slug`. Aunque Sanctum está instalado, los usuarios no tienen API tokens configurados. Esta integración usa un mecanismo equivalente específico para dispositivos: credencial aleatoria de 256 bits por estación, hash SHA-256 único en base de datos y cuatro abilities fijas (`agent:heartbeat`, `agent:configuration`, `agent:events`, `agent:results`). Las credenciales de usuarios y de estaciones no son intercambiables. No hay secreto global compartido.

Las rutas de dispositivos son stateless bajo `/api/agent`, fuera del middleware web/CSRF. Exigen HTTPS, JSON y Bearer (excepto enrolamiento). El middleware consulta y bloquea la estación en cada petición autenticada; revocación, rotación, abilities y escrituras se serializan. La configuración del proxy de confianza de Master sigue siendo necesaria al terminar TLS fuera de PHP; no confiar en proxies arbitrarios.

Master todavía no tiene una entidad Studio ni pertenencias de usuarios a estudios. Por ello `AGENT_STUDIO_IDS` es un registro explícito de identificadores externos válidos. Solo quienes tienen permisos administrativos globales pueden enrolar/configurar/revocar/solicitar comandos. No se concede acceso a managers/monitores/modelos. La estación hereda el estudio del token, no del payload; un reenrolamiento no puede cambiarlo. Las asociaciones de modelos, salas y turnos permanecen en Master. Una futura entidad Studio deberá reemplazar este registro y añadir políticas de pertenencia; no se presupone aislamiento multiestudio para administradores globales.

## Login OAuth del Agent

El nuevo login asocia una sesión de usuario con una identidad de estación sin reemplazarla. La sesión se autoriza en el navegador oficial de Master mediante Authorization Code + PKCE; la estación conserva su `installationId` y Master guarda los tokens únicamente como hashes.

1. Agent genera `code_verifier`, su desafío S256, un `state` aleatorio y un puerto loopback local.
2. Agent abre `GET /oauth/agent/authorize` con `client_id=velvet-agent`, `redirect_uri=http://127.0.0.1:<puerto>/oauth/callback`, `installation_id`, `hostname`, `studio_id`, `code_challenge` y `state`.
3. Master exige sesión web, correo verificado y permiso `stations.enroll`. Después del login redirige al callback con un código de un solo uso y el mismo `state`.
4. Agent valida `state` y llama `POST /api/agent/oauth/token` con `grant_type=authorization_code`, `code`, `redirect_uri` y `code_verifier`.
5. Master devuelve `access_token` de 60 minutos y `refresh_token` de 30 días. El refresh rota ambos tokens y revoca los anteriores.
6. Agent usa el `access_token` como `Authorization: Bearer ...` en heartbeat, configuración y endpoints de sesión. `POST /api/agent/oauth/revoke` revoca la sesión y su refresh token.

Rutas OAuth:

| Método / ruta | Uso |
| --- | --- |
| GET `/oauth/agent/authorize` | Login/autorización web y callback loopback |
| POST `/api/agent/oauth/token` | Intercambio PKCE o renovación |
| POST `/api/agent/oauth/revoke` | Revocación de la sesión actual |
| GET `/api/agent/me` | Usuario y estación asociada |
| GET `/api/agent/station` | Identidad y permisos de estación |
| GET `/api/agent/station/configuration` | Configuración de la estación |
| POST `/api/agent/sessions/start` | Inicia la sesión de trabajo del usuario |
| POST `/api/agent/sessions/{sessionId}/end` | Finaliza la sesión de trabajo |

## Sesiones de trabajo y métricas

La estación enrolada representa el computador y su uptime técnico. La tabla
`agent_work_sessions` representa el trabajo de la persona autenticada y guarda
los timestamps del servidor, duración conectada, duración transmitiendo y el
último estado de OBS. Una estación solo puede tener una sesión activa.

Después de `oauth/token` y `me`, el Agent debe iniciar la sesión:

```http
POST /api/agent/sessions/start
Authorization: Bearer <access_token>
Idempotency-Key: <uuid>
Content-Type: application/json

{"installationId":"...","stationId":1,"studioId":"studio-manizales","userId":12}
```

Master ignora `startedAt` para medir duración. El heartbeat puede incluir
`sessionId` y `active`; el estado OBS puede enviarse en `obs` o, para Agents
existentes, dentro de `status.obs`. Los Agents antiguos sin `sessionId` siguen
siendo aceptados y solo actualizan el registro técnico de la estación.
Cuando existe una sesión OAuth, `status.userRole` puede acompañar a
`userId`/`userDisplayName` para facilitar auditoría. Master no usa ese valor
para autorizar: el usuario y sus permisos provienen del access token.

El cierre explícito usa `reason` (`logout`, `revoked`, `timeout`,
`service_stopped` o `connection_lost`). Master también finaliza sesiones al
revocar OAuth, cerrar sesión web, revocar una estación o superar el margen de
`AGENT_SESSION_TIMEOUT_SECONDS` (90 segundos por defecto). La tarea
`agent:maintain` debe ejecutarse cada minuto.

El callback loopback solo acepta `http://127.0.0.1` o `http://localhost`, puertos 1024–65535 y la ruta exacta `/oauth/callback`. No se aceptan fragmentos, credenciales embebidas ni URLs externas. El esquema `velvet-agent://` queda como alternativa futura.

Los endpoints OAuth `/api/agent/oauth/token` y `/api/agent/oauth/revoke` aceptan `application/x-www-form-urlencoded`, como define el flujo OAuth. Las demás escrituras de Agent continúan exigiendo `application/json`.

## Preparación

Configurar en el entorno de despliegue, sin incluir credenciales en la URL:

```dotenv
AGENT_MASTER_URL=https://master.example.com/
AGENT_STUDIO_IDS=studio-bogota,studio-medellin
AGENT_SESSION_TIMEOUT_SECONDS=90
```

```bash
php artisan migrate --force
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force
php artisan config:cache
```

El seeder añade permisos al rol `admin` de forma aditiva; el rol `super_admin` conserva su autorización global existente. Si faltan los roles, ejecutar previamente el seeder habitual de roles. No se ejecutaron migraciones sobre la base configurada en `.env` durante la implementación.

Activar el scheduler de Laravel (`php artisan schedule:run` cada minuto). `agent:maintain` elimina las respuestas cifradas vencidas, marca comandos pendientes como expirados y estaciones offline tras `3 × heartbeatSeconds + 30` segundos. Un heartbeat nuevo restaura online. Un replay de heartbeat no actualiza `last_seen_at`. Los timestamps de recepción, no el reloj del dispositivo, determinan disponibilidad. El cron introduce hasta un minuto adicional de tolerancia.

## Rutas de dispositivo

| Método / ruta | Autenticación | Éxito |
| --- | --- | --- |
| POST `/api/agent/enroll` | Token temporal en el cuerpo | 200 `{ "deviceToken": "…" }` |
| POST `/api/agent/heartbeat` | Bearer + `agent:heartbeat` | 204 |
| GET `/api/agent/configuration` | Bearer + `agent:configuration` | 200 `{ "masterUrl", "heartbeatSeconds", "development" }` |
| POST `/api/agent/events` | Bearer + `agent:events` | 204 |
| POST `/api/agent/commands/{id}/result` | Bearer + `agent:results` | 204 |

Enviar `Content-Type: application/json` en peticiones de escritura y `Idempotency-Key` en todos los POST. La respuesta 204 no lleva cuerpo. El token temporal va únicamente en el cuerpo de enrolamiento; la credencial de dispositivo va en `Authorization: Bearer <deviceToken>`, nunca en la URL.

Ejemplo de enrolamiento, con valores ficticios:

```http
POST /api/agent/enroll HTTP/1.1
Content-Type: application/json
Idempotency-Key: 9ab1d9e6-4b39-41b6-a68e-f620db174e09

{"installationId":"0e582bc6-243c-4fdd-90a2-b02c16d75d3f","hostname":"estacion-demo","token":"<token-temporal>"}
```

La conexión de este ejemplo debe ser HTTPS. Conservar exactamente clave y cuerpo para recuperar una respuesta perdida; no generar otra clave para el mismo intento de enrolamiento.

## Administración (sin interfaz nueva)

Rutas JSON bajo sesión web autenticada, usuario activo y correo verificado, CSRF y HTTPS. No aceptan Bearer de dispositivo. Se usa el token XSRF actual de la cookie como en el resto de Master.

| Método / ruta | Permiso | Cuerpo / respuesta |
| --- | --- | --- |
| POST `/admin/agent/enrollment-tokens` | `stations.enroll` | `{ "studioId": "studio-bogota" }` → 201 `{ "token": "…", "expiresAt": "…" }` |
| POST `/admin/agent/stations/{station}/revoke` | `stations.revoke` | 204; revoca la credencial y cancela comandos pendientes |
| PUT `/admin/agent/stations/{station}/configuration` | `stations.configure` | `{ "heartbeatSeconds": 30 }` → 204; incrementa versión |
| POST `/admin/agent/stations/{station}/commands` | `stations.commands` | `{ "operation": "RefreshStatus", "expiresAt": "RFC3339 futuro" }` → 201 `{ "id", "operation", "expiresAt" }` |

`{station}` es el ID interno de Master; los UUID de instalación son independientes. No se añadió navegación ni componentes de interfaz; no cambia el sistema de visitas guiadas. Los POST administrativos también exigen `Idempotency-Key`: emisión de tokens con replay cifrado de cinco minutos; reservas/revocación con replay de 24 h, aislados por actor/ruta/estación. Los permisos se comprueban antes de cualquier replay. Repetir el PUT con el mismo intervalo no incrementa la versión. Los comandos se reservan únicamente; esta respuesta no significa que hayan sido entregados ni ejecutados. Solo `RefreshStatus`, con vigencia máxima de cinco minutos. No hay shell remoto ni operaciones OBS de escritura.

## Enrolamiento y reintentos

1. Un administrador emite un token con estudio y caducidad de diez minutos. Solo se persiste su hash.
2. Agent envía `POST /api/agent/enroll` con `installationId`, `hostname`, `token` e `Idempotency-Key` estable. El cliente actual deriva un UUID de instalación + token y es compatible.
3. En una única transacción se reserva la clave idempotente, bloquea/consume el token, crea o actualiza la estación y emite `deviceToken`. Solo el hash de este último queda en la estación.
4. Se puede recuperar exactamente la misma respuesta durante cinco minutos con la misma clave y el mismo cuerpo. La respuesta temporal se cifra con `APP_KEY` y se entrega con `Cache-Control: no-store`. Cambiar cuerpo/token con la misma clave devuelve 409 sin consumir otro token.
5. Caducada la ventana no se vuelve a revelar la credencial: 409. Consumido el token con otra clave: 401. Una credencial revocada o sustituida no se recupera con un replay antiguo: 401.
6. Un token nuevo del mismo estudio permite reenrolar la instalación: rota su credencial y cancela comandos pendientes de la inscripción anterior. Las demás estaciones no cambian.

La respuesta se revela una vez lógicamente, con la excepción explícita de recuperación cifrada del mismo enrolamiento durante la ventana. Preservar `APP_KEY` durante esa ventana; rotarla invalida los replays cifrados pendientes. No enviar tokens a logs, telemetría, eventos o canales de broadcasting. Los errores de validación enumeran nombres de campos y no sus valores.

## Telemetría, configuración y resultados

Las cinco rutas y los JSON camelCase se conservan. Heartbeats y eventos tienen UUID globalmente único, hash del cuerpo y timestamps de envío/recepción. El UUID de instalación del heartbeat debe coincidir con la credencial; las métricas pueden contener `null` en los campos nullable, pero esos campos siguen siendo obligatorios. Se valida longitud, rangos y tamaño total (64 KiB). Se sanea texto de telemetría: HTML, controles, Bearer, tokens propios y asignaciones comunes de secretos se eliminan o redactan. El agente igualmente debe evitar secretos en cualquier texto libre; el saneamiento no puede detectar secretos arbitrarios sin etiqueta.

La configuración pertenece exclusivamente a la estación autenticada; `masterUrl` lo fija Master, `development` permanece false y el administrador solo ajusta el intervalo (5–3600 s). La versión se devuelve en `X-Agent-Configuration-Version`, sin alterar el cuerpo esperado por Agent.

Resultados: la URL y `commandId` deben coincidir, y la reserva debe existir en esa estación. Un resultado terminal exacto se acepta otra vez incluso con otra clave; otro resultado para el mismo comando devuelve 409 y no sobrescribe el primero. Una reserva desconocida devuelve 404: conciliar en Master, nunca crear/ejecutar otro comando automáticamente. Una reserva local incierta enviada por Agent como `success:false` queda como fallo terminal, con su mensaje saneado, sin reejecución automática. Comandos expirados/cancelados o de operaciones distintas se rechazan con 409. Un resultado ya guardado conserva su replay incluso si después vence el comando.

## Precisiones del contrato para coordinar con Agent

- Se conservan nombres, rutas y respuestas exitosas 200/204.
- Se añaden errores explícitos 409 (conflicto/expiración), 413 (tamaño), 404 (reserva ajena/desconocida) y 503 (configuración HTTPS de Master incompleta). Validación de dispositivos usa 400, no 422.
- `Idempotency-Key` sigue siendo obligatorio en los POST de Agent. Se admite UUID y, como ampliación compatible, identificadores ASCII de 1–100 caracteres `[A-Za-z0-9._:-]`. La clave de enrolamiento tiene alcance global; las restantes están aisladas por estación y ruta/comando.
- Las claves conservan hash/tombstone; las respuestas de telemetría/resultados pueden repetirse por 24 h y las de enrolamiento por 5 min. Reusar una clave vencida devuelve 409, no vuelve a ejecutar. Los UUID persistidos siguen deduplicando cuerpos exactos bajo una clave nueva. No borrar tombstones sin una política coordinada de retención y reconciliación.
- Fechas RFC3339 con zona obligatoria y 0–7 decimales (.NET); almacenamiento a microsegundos/segundos según columna. Máximo cinco minutos en el futuro. No se limita la antigüedad de telemetría para permitir colas offline. `lastSync` puede ser null y sigue la misma precisión/límite futuro.
- Los límites por IP son 240/min y enrolamiento 10/min; por estación 120/min. 429 incluye `Retry-After`. Agent ya reintenta 429 y 5xx con backoff; Master nunca duerme la petición. Se prueba recuperación al vencer la ventana y recuperación tras un 503 sin consumo del token.
- Se acotan longitudes/rangos en la copia OpenAPI. El código de Agent actual es compatible; deben coordinarse el tratamiento de 409/404, la ventana máxima de recuperación y el registro externo de estudios antes de habilitar producción.

Los eventos Laravel/Reverb del documento original están descritos como futuros: no se implementa entrega de comandos ni autorización de canales de dispositivo en este MVP. Master usa Ably para otros módulos; esta integración no publica secretos ni comandos allí. La autorización WSS por instalación queda para el hito de transporte de comandos de Agent.

## Pruebas

```bash
php artisan test --compact tests/Feature/AgentIntegrationTest.php
php artisan test --compact
node --test tests/js/*.test.mjs
npm run build
```

Las pruebas de integración usan SQLite aislado: enrolamiento válido/consumido/expirado, recuperación tras pérdida de respuesta, rotación, cifrado/hash, aislamiento, abilities/revocación antes de replay, métricas null y límites, precisión .NET, deduplicación por UUID y claves, conflictos, permisos administrativos, configuración, resultados terminales, comandos inválidos/expirados, 429/backoff/503 y mantenimiento offline. La serialización usa transacciones, índices únicos y `lockForUpdate`; verificar concurrencia real bajo MySQL/InnoDB y proxy TLS en staging antes de conectar estaciones reales. No se ejecutó Agent Windows ni se probó comunicación de red entre ambos procesos.
