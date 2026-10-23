# Login OAuth de VELVET Agent

Actualizado: 2026-10-22.

## Flujo

El Agent conserva su `installationId` como identidad de estación. La sesión del usuario se obtiene por navegador y se asocia temporalmente a esa estación mediante OAuth Authorization Code con PKCE.

```text
Agent genera verifier/challenge/state
        ↓
Abre /oauth/agent/authorize en Master
        ↓
Usuario inicia sesión y autoriza
        ↓
Master redirige a http://127.0.0.1:<puerto>/oauth/callback
        ↓
Agent valida state e intercambia code + code_verifier
        ↓
Master entrega access_token + refresh_token
```

## Autorización

El Agent debe generar un puerto loopback disponible y construir una URL con:

```text
client_id=velvet-agent
response_type=code
redirect_uri=http://127.0.0.1:<puerto>/oauth/callback
installation_id=<UUID persistente de la estación>
hostname=<nombre del equipo>
studio_id=<sede configurada>
code_challenge=<Base64URL(SHA256(code_verifier))>
code_challenge_method=S256
state=<valor aleatorio>
```

Master exige sesión web, correo verificado y permiso `stations.enroll`. El código expira en dos minutos, solo puede utilizarse una vez y queda vinculado a instalación, sede, callback y desafío PKCE. El Agent debe rechazar cualquier callback cuyo `state` no coincida.

## Intercambio

```http
POST /api/agent/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&client_id=velvet-agent&code=vac_...&redirect_uri=http%3A%2F%2F127.0.0.1%3A43127%2Foauth%2Fcallback&code_verifier=...
```

Respuesta:

```json
{
  "token_type": "Bearer",
  "access_token": "vea_access_...",
  "refresh_token": "vea_refresh_...",
  "expires_in": 3600,
  "refresh_expires_in": 2592000,
  "station": {
    "id": 1,
    "installationId": "...",
    "hostname": "...",
    "studioId": "...",
    "abilities": ["agent:heartbeat", "agent:configuration", "agent:events", "agent:results"]
  }
}
```

Los valores anteriores son ejemplos; nunca deben copiarse a logs. Master solo almacena hashes de códigos y tokens.

## Renovación y uso

Cuando el access token esté próximo a vencer, el Agent debe enviar:

```json
{
  "grant_type": "refresh_token",
  "client_id": "velvet-agent",
  "refresh_token": "vea_refresh_..."
}
```

El refresh rota ambos tokens y revoca la pareja anterior. El access token se envía como `Authorization: Bearer <access_token>` en heartbeat, configuración y eventos. La estación continúa siendo la identidad técnica; el usuario asociado se consulta con `GET /api/agent/me`.

Para cerrar la sesión:

```http
POST /api/agent/oauth/revoke
Authorization: Bearer <access_token>
```

El callback profundo `velvet-agent://oauth/callback` no está habilitado todavía. El loopback es obligatorio en esta primera versión.

## Prueba local detrás de Cloudflare Tunnel

La URL pública configurada en Master debe ser exactamente la misma que usa el
Agent, por ejemplo:

```ini
APP_URL=https://annual-available-truly-beginner.trycloudflare.com
AGENT_MASTER_URL=https://annual-available-truly-beginner.trycloudflare.com
AGENT_STUDIO_IDS=studio-manizales
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1
```

No se debe configurar `SESSION_DOMAIN` para un dominio `trycloudflare.com`; se
deja en `null` para que el navegador conserve la cookie en el host exacto.
Después de cambiar estas variables, ejecutar `php artisan optimize:clear` y
reiniciar el servidor de Master, el túnel y el Agent.

La secuencia esperada es:

```text
GET /oauth/agent/authorize → 302 /login
POST /login                → 302 /oauth/agent/authorize
GET /oauth/agent/authorize → 302 http://127.0.0.1:<puerto>/oauth/callback
POST /api/agent/oauth/token → 200
GET /api/agent/me           → 200
```

La respuesta de `me` vincula la sesión de usuario con la estación y entrega el
alcance de interfaz que el Agent debe aplicar:

```json
{
  "user": {
    "id": 12,
    "name": "Modelo de prueba",
    "email": "modelo@velvet.test",
    "roles": ["model"],
    "permissions": ["stations.connect"]
  },
  "modules": {
    "home": true,
    "monitoring": true,
    "obs": true,
    "diagnostics": true,
    "configuration": false
  }
}
```

La estación continúa enrolada permanentemente. Cada inicio de sesión OAuth
reemplaza únicamente la sesión del usuario. `configuration` solo es `true`
cuando el usuario tiene el permiso `stations.configure` (por ejemplo,
administradores). El Agent debe ocultar y bloquear el módulo cuando sea
`false`; las APIs de Master siguen validando sus propios permisos.
Si el navegador llega al callback local pero el Agent no se desbloquea, el
problema ya no es la redirección de Master: deben revisarse las respuestas de
`oauth/token` y `me`, además de que no exista una instancia antigua del Agent.
