# Sesiones de trabajo y métricas de VELVET Agent

Actualizado: 2026-10-22.

La estación enrolada identifica el computador y su uptime técnico. La sesión
de trabajo identifica al usuario autenticado y registra el tiempo real de uso
para monitoreo y futuras liquidaciones.

## Flujo

```text
OAuth completo
  → POST /api/agent/sessions/start
  → guardar sessionId localmente
  → enviar sessionId en cada heartbeat
  → POST /api/agent/sessions/{sessionId}/end al cerrar
```

El Agent debe iniciar la sesión después de guardar correctamente los tokens y
de consultar `GET /api/agent/me`.

Como compatibilidad con Agents que ya generan un `sessionId` local pero todavía
no llaman a `sessions/start`, Master registra la sesión automáticamente en el
primer heartbeat OAuth que incluya ese identificador. El usuario se obtiene del
token OAuth y `started_at` se genera en el servidor; los valores `userId` y
`sessionStartedAt` enviados por el Agent no autorizan ni calculan la duración.

## Iniciar sesión

```http
POST /api/agent/sessions/start
Authorization: Bearer <access_token>
Idempotency-Key: <valor-único>
Content-Type: application/json
```

```json
{
  "installationId": "uuid-de-la-estacion",
  "stationId": 45,
  "studioId": "studio-manizales",
  "userId": 12,
  "startedAt": "2026-10-22T22:40:00Z"
}
```

`startedAt` es informativo. Master calcula la duración usando sus propios
timestamps. La respuesta contiene `sessionId`, `startedAt` del servidor y
`status: active`.

Una estación no puede tener dos sesiones activas. Si se repite la solicitud
para el mismo usuario y estación, Master devuelve la sesión existente de forma
idempotente.

## Inicio y cierre de transmisión

El botón de transmisión del Agent debe informar explícitamente a Master:

```http
POST /api/agent/sessions/{sessionId}/transmission/start
Authorization: Bearer <access_token>
Idempotency-Key: <valor-único>
```

Al detenerla:

```http
POST /api/agent/sessions/{sessionId}/transmission/stop
Authorization: Bearer <access_token>
Idempotency-Key: <valor-único>
```

Master usa la hora del servidor para `transmissionStartedAt` y para acumular
`totalStreamingSeconds`. El heartbeat con `obs.streaming` continúa siendo un
respaldo: si OBS cambia de estado sin que el botón envíe el evento, Master
detecta la transición en el siguiente heartbeat.

## Heartbeat

El heartbeat puede incluir:

```json
{
  "sessionId": "uuid-de-la-sesion",
  "active": true,
  "status": {
    "userAuthenticated": true,
    "userId": "12",
    "userDisplayName": "Modelo",
    "userRole": "model",
    "configurationAvailable": false,
    "sessionId": "uuid-de-la-sesion",
    "sessionStartedAt": "2026-10-22T22:40:00Z",
    "obs": {
      "processRunning": true,
      "webSocketConnected": true,
      "streaming": true,
      "currentScene": "Escena 1"
    }
  }
}
```

Master registra el último estado de OBS, `last_heartbeat_at`, tiempo conectado
y tiempo transmitiendo. El rol enviado sirve para auditoría; la autorización
real siempre proviene del usuario asociado al token OAuth.

Los Agents antiguos que no envían `sessionId` continúan funcionando y solo
actualizan la telemetría técnica de la estación.

## Cierre y timeout

```http
POST /api/agent/sessions/{sessionId}/end
Authorization: Bearer <access_token>
Idempotency-Key: <valor-único>
Content-Type: application/json
```

```json
{"reason":"logout"}
```

Razones permitidas: `logout`, `revoked`, `timeout`, `service_stopped` y
`connection_lost`.

Master cierra automáticamente la sesión cuando se revoca OAuth, se cierra la
sesión web, se revoca la estación o se supera
`AGENT_SESSION_TIMEOUT_SECONDS`. El valor predeterminado es 90 segundos para
permitir reconexiones breves. El scheduler ejecuta `agent:maintain` cada minuto.

No se calculan pagos en este flujo. Los datos quedan disponibles para
monitoreo, auditoría y una futura liquidación basada en timestamps confiables
del servidor.
