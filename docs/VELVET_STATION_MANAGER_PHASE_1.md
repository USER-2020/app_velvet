# VELVET Station Manager — Fase 1

## Alcance implementado

- Se añadió room_id nullable a agent_stations, para asociar una instalación con una room sin romper estaciones ya enroladas.
- Se creó station_assignments para conservar el historial de estación, room, modelo, monitor, fechas y persona que asignó.
- Se añadieron relaciones Eloquent entre AgentStation, Room, Candidate y StationAssignment.
- Se creó el permiso stations.assign; el rol admin lo recibe junto con sus permisos Agent existentes.
- Se añadieron pruebas de esquema, relaciones y permiso.

## Reglas actuales

- Una estación puede existir sin room durante la transición.
- Una asignación conserva starts_at y ends_at; no se debe sobrescribir el historial.
- model_candidate_id es obligatorio y monitor_candidate_id es opcional.
- La asignación no activa todavía comandos, control remoto, OBS ni MeshCentral.
- El módulo web permanece en construcción; esta fase prepara la fuente de verdad para el backend.

## Siguiente fase

Crear el servicio y endpoints protegidos de Station Manager para listar estaciones autorizadas y asignar/desasignar una estación validando:

- room activa;
- modelo activa;
- monitor válido y asignado a la modelo;
- estación no revocada;
- ausencia de solapamientos;
- permiso stations.assign.
