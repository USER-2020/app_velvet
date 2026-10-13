# Velvet Master - roadmap de implementacion

Actualizado: 2026-10-12.

## Auditoria inicial

El repositorio ya tenia reclutamiento, candidatos, onboarding, contratacion, salas, usuarios, roles, permisos y layouts de Inertia/React. No existian los modulos persistentes de plataformas Studio, sesiones, productividad, sedes, inventario o mantenimiento. Las fases se construyeron reutilizando candidatos, salas, reservas, roles, permisos y el shell administrativo existente.

## Fase 1 - nucleo existente

- Implementado: expediente operativo, capacitaciones, historial de asignacion/reasignacion modelo-monitor y activacion controlada desde Candidate.
- Seguridad: politicas de alcance para monitores, permisos y trazabilidad de cambios.
- Verificacion: `OperationalProfilesTest` y pruebas de flujo existentes.

## Fase 2 - operacion interna

- Implementado: turnos, asistencia, entradas, salidas, retrasos, pausas, reservas de sala, checklists de apertura/cierre, incidencias, solicitudes y novedades.
- Verificacion: `StudioOperationsTest`.

## Fase 3 - plataformas

- Implementado: catalogo de plataformas, cuentas Studio, asociaciones con modelos, titularidad, autorizacion, importaciones CSV y estados Manual/Simulado/Sin configurar/Error.
- Seguridad: las credenciales se cifran en `studio_accounts.encrypted_credentials` y se excluyen de las respuestas del modelo.
- Limite externo: no hay conectores reales ni endpoints de proveedores. Solo el adaptador de simulacion puede ejecutarse.

## Fase 4 - sesiones y productividad

- Implementado: sesiones operativas asociables a turnos, transmisiones simultaneas, cierre, metricas con origen, validacion por monitor y resumen de productividad.
- Regla: las horas se calculan por sesion; las transmisiones paralelas no se suman como horas duplicadas.

## Fase 5 - administracion y control

- Implementado: sedes, inventario, mantenimientos preventivos/correctivos, evaluaciones de desempeno y lote de salida para Finanzas.
- Limite externo: el lote financiero queda en estado `PENDING`; no se envia informacion a Finanzas, Marketplace ni Velvet AI.

## Fase 6 - activacion Studio

- Implementado: interfaz de adaptadores, registro de titular/receptor de pagos, secretos cifrados, ejecuciones de sincronizacion simulada, manejo de errores y registro de lotes de importacion.
- Dependencias externas: cuentas oficiales, consentimiento/titularidad verificados, documentacion contractual y tecnica de cada proveedor, credenciales autorizadas, pruebas de permisos y aprobacion explicita antes de activar un adaptador real.

## Pendiente arquitectonico documentado

El repositorio actual es de un solo estudio: no contiene una entidad de tenant/Studio ni relaciones de candidatos, salas y usuarios por estudio. Por eso no se debe afirmar aislamiento multiestudio todavia. Las politicas actuales restringen por rol y por asignacion de monitor, y los nuevos datos no se comparten con servicios externos. Para aislamiento entre estudios se requiere una decision de producto sobre la entidad propietaria y una migracion transversal no destructiva; se deja fuera para no inventar estudios ni reasignar datos existentes sin autorizacion.

## Interfaces y guias

Se agregaron las pantallas responsivas de Plataformas, Sesiones y Administracion. Cada una tiene su recorrido Driver.js, asociado a su ruta, persistido por usuario y sujeto al control global de visitas guiadas.

El modal de creacion y edicion de usuarios en Administracion de accesos usa un ancho maximo `max-w-6xl`, mayor altura util y una cuadrícula de hasta tres columnas para roles en pantallas amplias. En móvil mantiene márgenes seguros y distribución adaptativa.

### Carga guiada de documentos de contratación

El enlace firmado de contratación ahora presenta un onboarding por requisito, en lugar de una carga masiva sin contexto. Solicita, uno por uno: documento de identidad, certificación bancaria, documento tributario, formato firmado y un documento adicional opcional. El progreso permite volver a un paso, reemplazar un archivo y reconoce archivos ya recibidos al reabrir el enlace.

Cada carga se guarda privada en `lead-documents/contracts` y conserva `metadata.requirement_key`; esto permite al equipo identificar el requisito sin inferirlo por el nombre del archivo. La migración aditiva `2026_10_12_060000_add_metadata_to_lead_documents_table.php` agrega únicamente la columna JSON nullable `lead_documents.metadata`; no modifica ni elimina documentos existentes.

El wizard persiste un archivo por paso antes de desbloquear el siguiente. El stepper consume los estados reales `PENDING`, `VERIFIED` y `REJECTED`; muestra por separado el porcentaje cargado y la cantidad aprobada. La migración aditiva `2026_10_12_061000_add_review_note_to_lead_documents_table.php` incorpora la observación administrativa nullable para solicitudes de corrección. Los documentos verificados no admiten reemplazo desde el enlace público.

## Permisos operativos

`VelvetMasterOperationalPermissionsSeeder` complementa, sin reemplazar, el RBAC base. Crea permisos granulares de asistencia, checklists, plataformas, cuentas Studio, sesiones, productividad, inventario, mantenimiento, evaluaciones, reportes y exportaciones locales. La matriz completa está en `docs/VELVET_MASTER_PERMISSIONS.md`.

## Pruebas requeridas

- `PlatformProductivityAdministrationTest`: cifrado, sincronizacion simulada, importacion, sesiones paralelas, metricas auditables, inventario y lote financiero local.
- Ejecutar `php artisan test` y `npm run build` antes de despliegue. Los adaptadores reales siguen explicitamente desactivados.
# Seeder de datos operativos de prueba

`TestOperationalModelsAndMonitorsSeeder` crea de forma idempotente tres modelos, dos monitores, perfiles activos, accesos de portal, capacitaciones, historial de asignaciones, turnos, asistencia, sala, reserva, checklists, incidencia, solicitud y novedad. Todos los registros usan el prefijo `TEST-` y el dominio `@velvet.test`.

No esta registrado en `DatabaseSeeder`; ejecutarlo exclusivamente en desarrollo o staging:

```bash
php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder
```

## Ajuste responsive operativo

Administracion, Plataformas y Productividad comparten `OperationsPageHeader`: el encabezado se apila en móvil y alinea título, descripción y acciones desde escritorio. Sus formularios usan tarjetas con etiquetas, grillas de una, dos o tres columnas según el espacio y listados con scroll contenido.

El modal de creacion y edicion de usuarios usa un ancho máximo de 72rem, altura limitada al viewport, header y footer fijos, y contenido central con el scrollbar delgado `velvet-scrollbar`.

