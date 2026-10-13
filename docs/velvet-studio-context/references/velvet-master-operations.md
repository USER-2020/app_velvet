# Velvet Master: fases operativas e integraciones

## Diseño responsive

Administracion, Plataformas y Productividad reutilizan `OperationsPageHeader` y tarjetas de formulario. La composición baja a una columna en móvil, usa dos columnas en pantallas intermedias y hasta tres en escritorio.

Los listados de registros limitan su alto y emplean `velvet-scrollbar`, de modo que la página mantiene una lectura estable con muchos elementos.

Snapshot: 2026-10-12. Para estado vigente, revisar `docs/VELVET_MASTER_ROADMAP.md`, migraciones y rutas.

## Estado por fase

1. Núcleo: perfiles operativos, capacitaciones, activación y asignación/reasignación modelo-monitor con historial.
2. Operación: turnos, asistencia, pausas, salas, checklists, incidencias, solicitudes y novedades.
3. Plataformas: catálogo, cuentas Studio, asociaciones de cuentas de modelo, importación CSV y trazabilidad.
4. Productividad: sesiones, transmisiones simultáneas, métricas con origen y validación.
5. Administración: sedes, inventario, mantenimiento, evaluaciones y lote local para Finanzas.
6. Activación Studio: contrato de adaptadores, secretos cifrados, simulación y errores registrados.

## Restricciones de integración

- No existen cuentas Studio oficiales de Chaturbate, Stripchat, BongaCams u otros proveedores.
- `SimulationPlatformAdapter` es el único adaptador operativo. Devuelve datos claramente simulados y no usa red.
- Una cuenta Studio se crea en modo `MANUAL` o `SIMULATED`; una ejecución simulada crea `platform_sync_runs` y una importación crea `platform_import_batches`.
- `encrypted_credentials` está oculto en el modelo y se guarda con `Crypt`. Nunca devolverlo a Inertia, logs, respuestas ni documentación.
- `finance_export_batches` prepara información local en `PENDING`; no integra ni envía datos a Finanzas, Marketplace o Velvet AI.

## Límites de arquitectura

El repositorio actual no tiene entidad `Studio` o tenant que relacione usuarios, candidatos y salas. Hay control por permisos y asignaciones de monitor, pero no aislamiento multiestudio. Implementarlo requiere una decisión de propiedad de datos y una migración transversal no destructiva; no crear estudios ficticios ni aplicar esa migración sin autorización.

## Verificación

`PlatformProductivityAdministrationTest` prueba cifrado, simulación, importación, sesiones simultáneas, validación de métricas, inventario y lote financiero local. La suite completa al snapshot pasa con `php artisan test`; el frontend se verifica con `npm run build`.

## Reservas, turnos y sesión operativa

Un turno define la planificación; la asistencia registra el tiempo real; la reserva controla la room; y `operational_sessions` conserva la sesión efectivamente iniciada. La reserva y la sesión se vinculan mediante `room_reservation_id`.

El inicio y finalización se centralizan en `app/Services/OperationalSessionService.php`. Los cambios se publican por `RealtimePublisher` en los canales de rooms y operaciones para que el panel del monitor actualice sus tarjetas sin polling agresivo. Sandbox permanece aislado en `TrainingRecord`.

## Permisos operativos

`VelvetMasterOperationalPermissionsSeeder` es incremental y se ejecuta después del seeder base. Usa Spatie Laravel Permission con `firstOrCreate()`, `givePermissionTo()` y vaciado del `PermissionRegistrar`, por lo que no revoca personalizaciones. La matriz por módulo y rol está en `docs/VELVET_MASTER_PERMISSIONS.md`.
