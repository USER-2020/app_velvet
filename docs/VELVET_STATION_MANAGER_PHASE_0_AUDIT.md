# VELVET Station Manager — Fase 0

## Estado

Fase 0 iniciada. El módulo administrativo `VELVET Agent` queda visible para los perfiles que ya tenían permiso, pero en modo **En construcción**. No se habilitan nuevos enrolamientos ni acciones operativas desde la interfaz mientras se completa el diseño.

## Arquitectura confirmada

- **Backend:** Laravel con rutas web y API, middleware de sesión, permisos y transporte Agent.
- **Frontend:** React + Inertia, con el layout administrativo de Recruitment y visitas guiadas en `resources/js/lib/productTours.js`.
- **Autenticación web:** sesión Laravel, usuarios verificados y permisos Spatie/propios.
- **Autenticación de estación:** credencial permanente almacenada como hash en `agent_stations`; es independiente de OAuth.
- **OAuth del Agent:** authorization code + PKCE en `oauth/agent/authorize` y `api/agent/oauth/*`.
- **Persistencia Agent existente:** `agent_enrollment_tokens`, `agent_stations`, `agent_heartbeats`, `agent_events`, `agent_commands` e idempotencia.
- **Observabilidad existente:** heartbeat, estado de estación, métricas del sistema y estado de OBS reportados por la estación.
- **Tiempo real:** existe infraestructura de broadcasting para módulos administrativos, pero no se considera parte del login OAuth del Agent.

## Entidades reutilizables

- `rooms` y reservas de room.
- `operational_shifts`, asistencias, incidencias y checklist operativo.
- `operational_profiles` y `model_monitor_assignments`, con historial mediante fechas de inicio y finalización.
- `agent_stations` y sesiones de trabajo del Agent.
- permisos operativos `stations.enroll`, `stations.connect`, `stations.revoke`, `stations.configure` y `stations.commands`.
- auditoría y visitas guiadas existentes.

## Incompatibilidades y vacíos detectados

1. `agent_stations` todavía no relaciona una estación con `room_id`, un dispositivo MeshCentral o una asignación histórica.
2. No existen entidades específicas para `station_assignments`, `station_devices`, sesiones remotas, chat interno ni destinos de salida OBS.
3. El módulo actual mezcla enrolamiento, diagnóstico de OBS y administración de estaciones; no representa todavía el flujo de Station Manager.
4. No hay un adaptador MeshCentral confirmado ni una política de sesiones remotas de corta duración.
5. El contrato actual de comandos del Agent es limitado y no cubre cambio de turno ni operaciones OBS completas.
6. La asociación entre modelo, monitor, room y estación debe definirse antes de agregar endpoints para evitar autorizar por ocultamiento de botones.

## Plan incremental posterior

1. Definir el modelo de asignaciones y la relación estación-room sin romper `agent_stations`.
2. Separar vista de supervisión, administración de estación y enrolamiento.
3. Crear servicios backend con autorización por recurso y pruebas de permisos.
4. Diseñar el contrato Launcher/OBS y sus estados verificables.
5. Investigar y validar la integración oficial de MeshCentral antes de construir la vista remota.
6. Añadir cambio de turno, chat y auditoría como flujos independientes.
7. Probar primero con Room Paris y una estación Windows controlada.

## Cambios realizados en esta fase

- La página `/admin/agent` ahora muestra únicamente el estado **En construcción**.
- Se retiraron del frontend los formularios de token, tablas de estaciones y acciones operativas para impedir flujos incompletos.
- Se actualizó la visita guiada del módulo para explicar el estado de construcción.
- No se crearon migraciones, no se ejecutaron migraciones y no se modificaron los endpoints existentes del Agent.
