# Operación, comandos y pruebas

## Preparación local

Requisitos: PHP 8.2+, Composer, Node.js compatible con el workflow (Node 22 en CI) y una base configurada. El flujo de setup de `composer.json` hace, en orden conceptual:

```bash
composer install
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

Para desarrollo completo:

```bash
composer run dev
```

Esto arranca servidor Laravel, listener de cola, Pail y Vite mediante `concurrently`.

## Comandos frecuentes

```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder
php artisan db:seed --class=TestUsersSeeder
php artisan db:seed --class=TrainingDataSeeder
php artisan route:list
php artisan test
npm run build
npm run dev
```

`TestUsersSeeder` llama internamente a `RolePermissionSeeder`. `TrainingDataSeeder` elimina y reconstruye el dataset de entrenamiento, por lo que debe usarse con intención.

`TestOperationalModelsAndMonitorsSeeder` es un dataset integral e idempotente para desarrollo o staging. Crea o actualiza tres modelos y dos monitores con correos `@velvet.test` y códigos `TEST-*`, además de perfiles operativos, usuarios de portal, capacitaciones, historial de asignaciones, turnos, asistencia, sala, reserva, checklists, incidencia, solicitud y novedad. Llama a `TestUsersSeeder` para disponer de las cuentas de prueba y no forma parte de `DatabaseSeeder`:

```bash
php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder
```

Nunca ejecutar este seeder en producción.

## Producción: corrección de flujos operativos

La secuencia completa, los comandos y los seeders prohibidos están en `docs/DESPLIEGUE_PRODUCCION_SEGURO.md`. Esa guía prevalece para un despliegue de producción porque incluye las migraciones de Spatie, módulos operativos y foto de perfil privada.

Para desplegar las migraciones y permisos nuevos sin cargar cuentas ni datos de entrenamiento, primero crear y comprobar un backup cifrado y luego ejecutar:

```bash
php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder
php artisan permission:cache-reset
```

La migración `2026_10_12_080000_add_room_reservation_to_operational_sessions.php` vincula una reserva con su sesión operativa. En una actualización existente se ejecuta con `php artisan migrate --force` antes del seeder operativo.

No usar `migrate:fresh`, `migrate:refresh`, `migrate:rollback` ni `php artisan db:seed` en ese despliegue. El `DatabaseSeeder` invoca `AdminUserSeeder` y `TrainingDataSeeder`; `TestUsersSeeder` es exclusivo de desarrollo. Ejecutar `RolePermissionSeeder` únicamente si el catálogo base no existe y tras revisar el backup, pues aunque es aditivo incluye la migración de compatibilidad `reviewer` → `hr`.

## Pruebas

La suite usa PHPUnit/Laravel (`php artisan test`) y `RefreshDatabase`; `phpunit.xml` configura SQLite en memoria. No debe depender de la base local ni de servicios externos.

La cobertura relevante incluye autenticación, autorización, perfil, aplicación pública, leads, conversión idempotente, dashboard, precalificación, identidad/documentos, entrevistas, workflows, rooms, portales, perfiles operativos, operación diaria, plataformas simuladas, productividad, administración y visitas guiadas.

Para una funcionalidad nueva, agrega una prueba de feature si cambia:

- permisos o roles;
- rutas públicas o enlaces firmados;
- datos persistidos;
- transiciones de lead/candidato/entrevista/contratación;
- configuración global o progreso de tours.

Para permisos operativos, añade además una regresión de idempotencia del seeder, mínimo privilegio y 403 para acciones protegidas. `VelvetMasterOperationalPermissionsSeederTest` cubre esas reglas.

La prueba `RecruitmentTest::test_public_contracting_uploads_are_guided_and_saved_by_requirement` cubre el enlace firmado de contratación, los requisitos obligatorios y la persistencia privada de cada requisito.

La misma prueba cubre la persistencia secuencial por requisito y que el estado `REJECTED` junto con su `review_note` vuelva al flujo público para pedir una corrección.

`StudioOperationsTest` cubre el inicio y cierre de una reserva: verifica `IN_USE`/`COMPLETED`, apertura y cierre de asistencia, sincronización de `OperationalShift` y persistencia de `operational_sessions`. El build frontend se valida con `npm run build`.

`RecruitmentTest::test_admin_can_preview_a_stored_document` cubre la preview
administrativa de un archivo privado. El controlador usa
`Gate::authorize('view', $document)` porque el controlador base de la
aplicacion no expone `$this->authorize()`.

En el hosting, los documentos permanecen en
`storage/app/private/lead-documents`. Esta ruta debe persistir entre releases,
tener permisos de lectura/escritura para PHP y estar incluida en los backups.
La preview no requiere `php artisan storage:link`; se entrega mediante la ruta
autorizada. Despues de publicar cambios PHP, ejecutar `php artisan optimize:clear`.

## CI

`.github/workflows/ci.yml` corre en `push` y `pull_request` con dos jobs:

1. **Backend · PHP 8.2**: Composer, `composer validate --strict`, `.env`, key y `php artisan test`.
2. **Frontend · Node 22**: `npm ci`, `npm run build` y `node --test tests/js/*.test.mjs`.

Pint estricto no está activado como gate porque el código existente requiere normalización global; no activar un cambio masivo de formato como parte de una funcionalidad.

## Integraciones y configuración

Variables relevantes del `.env.example`: `DIDIT_BASE_URL`, `DIDIT_API_KEY`, `DIDIT_WORKFLOW_ID`, `DIDIT_WEBHOOK_SECRET`, `VELVET_WHATSAPP_NUMBER`, `ABLY_API_KEY`, `ABLY_CHANNEL`, `VELVET_TEST_USERS_PASSWORD`, `VELVET_ADMIN_EMAIL` y `VELVET_ADMIN_PASSWORD`.

Los valores secretos nunca deben copiarse a una skill, respuesta o commit. La configuración faltante de Didit o Ably debe degradar de forma controlada según el flujo; no asumir que una integración está activa solo porque existe su código.

Las cuentas Studio de plataformas no se configuran desde `.env`: sus secretos autorizados se cifran por cuenta en la base de datos. Hasta que existan proveedor, contrato, documentación y credenciales oficiales, el único adaptador permitido es `simulation`; no usar HTTP ni inventar endpoints.

## Diagnóstico rápido

| Síntoma | Revisar |
| --- | --- |
| 403 | ruta, middleware `permission:*`, rol, slug exacto y reglas especiales. |
| Tour no aparece | `guidedTours.enabled`, progreso/version, `tourKeyForPath`, permisos y selectores montados. |
| Tour aparece sobre carga | `ProductTour`, `.apply-intro-overlay`, `.dashboard-route-skeleton` y evento `velvet-intro-overlay-complete`. |
| No llega correo | `MAIL_MAILER`, mailable, cola/log local y prueba con `Mail::fake()`. |
| No hay realtime | `ABLY_API_KEY`, canal, `RealtimePublisher`; la operación base debe seguir funcionando. |
| Portal muestra datos inesperados | `EnsureActiveOperationalProfile`, estado `ACTIVE`, `training_mode` y `TrainingRecord`. |
| Doble conversión/reserva | transacción, bloqueo, índice único y prueba de reintento. |
| Build falla | `npm ci`, versión Node, imports, Vite y rutas de assets; no modificar `.env` para ocultar el error. |

## Operación y pruebas de Agent

Variables: `AGENT_MASTER_URL` debe ser HTTPS sin credenciales y `AGENT_STUDIO_IDS` contiene IDs externos autorizados separados por comas. `VelvetMasterOperationalPermissionsSeeder` añade `stations.*` a administración; los seeders no crean estaciones. Aplicar las migraciones por el procedimiento de despliegue del proyecto y reconstruir la caché de configuración.

Activar el scheduler cada minuto. `agent:maintain` caduca comandos pendientes, elimina respuestas cifradas de replay vencidas y calcula offline con tolerancia al intervalo. Conservar `APP_KEY` y los registros de claves/hash; una respuesta perdida de enrolamiento puede recuperarse durante cinco minutos mediante la misma clave y cuerpo. Nunca incluir tokens reales en documentación, logs o ejemplos.

```bash
php artisan test --compact tests/Feature/AgentIntegrationTest.php
node --test tests/js/*.test.mjs
```

`AgentIntegrationTest` cubre tokens, aislamiento, revocación, configuración, idempotencia, telemetría nullable, precisión de fechas .NET, comandos, permisos, rate limiting con caché de base de datos, recuperación y offline. La regresión frontend de CSRF verifica que no se conserve el token fijo del HTML tras cambiar de sesión.

Diagnóstico: 400 indica payload/transporte/clave inválidos; 401/403 exige revisar credencial, revocación y ability; 409 requiere conciliar cuerpo, UUID, reserva o ventana vencida; 429 incluye `Retry-After`; 503 puede indicar `AGENT_MASTER_URL` incompleta. Agent reintenta 429/5xx con backoff; no crear comandos nuevos automáticamente para resolver resultados desconocidos (404).

Referencia completa: [AGENT_INTEGRATION.md](../../AGENT_INTEGRATION.md). Las APIs están implementadas; su activación y prueba con Agent Windows son pasos de despliegue. La entrega de comandos/Reverb continúa pendiente.
