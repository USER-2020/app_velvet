# Velvet Master — auditoria y cierre de Fase 1

Fecha de auditoria: 2026-10-12.

## Diagnostico tecnico inicial

La aplicacion parte de una base Laravel 12, Inertia 2 y React 18 madura para reclutamiento: leads, candidatas, entrevistas, contratacion, onboarding publico, documentos, roles/permisos y salas ya existen. Tambien existen permisos previstos para operacion, pero varios no tenian aun entidades, flujos ni control de alcance efectivo.

La separacion que se conserva es:

- Reclutamiento: `Lead` y `Candidate` representan la captacion y el proceso de seleccion.
- Operacion: el expediente operativo queda asociado a la candidata sin destruir su historial de reclutamiento.
- Asignacion: una asignacion activa por modelo, con cierre y motivo al reasignar o finalizar.
- Activacion: `ONBOARDING -> INDUCTION -> READY_TO_ACTIVATE -> ACTIVE`; los requisitos obligatorios de induccion bloquean la activacion.

## Fase 1 — completada

| Requisito | Estado previo | Resultado |
| --- | --- | --- |
| Auditar rutas, controladores, migraciones y pantallas | Implementado parcialmente | Auditados y documentados. |
| Expediente operativo de modelos activas | Parcial | Implementado con `operational_profiles`. |
| Perfil operativo de monitores | Parcial | Implementado con el mismo expediente tipado para `MONITOR`. |
| Asignar/reasignar monitor con historial | Ausente | Implementado con `model_monitor_assignments`, motivo y cierre inmutable. |
| Activacion desde Candidate | Parcial | Completado con transiciones controladas y registros iniciales. |
| Seguimiento de onboarding y capacitaciones | Parcial | Implementado con `operational_training_records`. |
| Permisos y politicas para modelos asignadas | Parcial | Directorio y detalle del monitor limitados a asignaciones activas. |

### Entregables

- Migracion `2026_10_12_000000_create_operational_profile_tables.php`.
- Modelos de perfil, capacitacion y asignacion historica.
- Politica `CandidatePolicy` para proteger el detalle de una modelo asignada.
- Panel de expediente operativo en el detalle administrativo de Candidate.
- Rutas para capacitaciones, asignacion, reasignacion y finalizacion de asignacion.
- Visita guiada Driver.js actualizada para el expediente operativo; la version global de recorridos pasa a 3.
- Pruebas de feature para activacion, induccion, reasignacion y alcance del monitor.

## Estado de las fases posteriores

| Fase | Estado | Hallazgo de auditoria |
| --- | --- | --- |
| 2. Operacion interna | Parcial | Salas, reservas y bitacora de uso existen; faltan turnos, asistencia, descansos, checklists, incidencias, solicitudes y novedades operativas. |
| 3. Plataformas | Ausente | No hay catalogo, cuentas Studio, vinculaciones, adaptadores, importador ni simulador operativo. La conexion real depende de API y autorizacion oficial de cada proveedor. |
| 4. Sesiones y productividad | Ausente | No hay entidades para turno, sesion, transmision, metricas ni correccion auditada. |
| 5. Administracion y control | Parcial | Hay dashboards y notificaciones; faltan sedes, inventario, mantenimiento, desempeno, centro de operaciones, reportes operativos e intercambio con Finanzas. |
| 6. Activacion de cuentas Studio | Bloqueado externo para integracion real | Faltan el flujo administrativo y la verificacion; tokens, permisos y sincronizacion real requieren autorizacion y documentacion oficial del proveedor. |

## Criterios verificados para aceptar Fase 1

- Una candidata en onboarding recibe acceso y pasa a induccion con expediente y registros iniciales.
- No puede avanzar a lista para activar mientras exista una capacitacion obligatoria pendiente.
- Una reasignacion exige motivo, termina la asignacion anterior y conserva su historial.
- Un monitor solo ve y abre modelos con una asignacion activa a su perfil.
- La interfaz muestra el expediente y tiene recorrido contextual cuando esta disponible.

## Siguiente fase propuesta (no iniciada)

Fase 2 debe comenzar por el modelo de datos de turnos, asistencia y reservas de sala. Un turno programado, la asistencia real, la sesion operativa y la transmision deben seguir siendo entidades separadas para evitar duplicar horas cuando haya transmisiones simultaneas.
