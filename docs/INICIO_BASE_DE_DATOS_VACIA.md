# Inicio desde una base de datos vacía

Esta guía prepara Velvet Master cuando la base de datos **ya existe pero no tiene tablas**. No uses `migrate:fresh`: en una base nueva no es necesario y ese comando es peligroso si se apunta por error a otra base.

Hay dos recorridos:

- **Producción nueva:** catálogo de roles, permisos y una única cuenta administradora; no crea datos de práctica.
- **Desarrollo o staging:** añade cuentas, modelos, monitores, rooms y registros ficticios identificados con `@velvet.test` o `TEST-`.

## 1. Preparar el entorno

Desde la raíz del proyecto, instala las dependencias PHP sin dependencias de desarrollo en producción:

```powershell
composer install --no-dev --optimize-autoloader
```

En desarrollo se pueden instalar todas:

```powershell
composer install
```

Crea el archivo de entorno solo si todavía no existe y genera una clave propia para esta instalación:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configura en `.env` la conexión a la base nueva antes de continuar. La base debe haber sido creada por el proveedor o por el administrador de MySQL; Laravel crea sus tablas, no la base de datos.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=velvet_master
DB_USERNAME=usuario_limitado
DB_PASSWORD=una-clave-segura

# Cambiar antes de ejecutar AdminUserSeeder.
VELVET_ADMIN_NAME="Administrador Velvet"
VELVET_ADMIN_EMAIL=admin@tu-dominio.example
VELVET_ADMIN_PASSWORD="Una-clave-larga-y-unica"
```

Para desarrollo, usa `APP_ENV=local` y credenciales de una base local aislada. Nunca copies secretos de producción al entorno de desarrollo.

Verifica que Laravel pueda leer la configuración:

```powershell
php artisan config:clear
php artisan about --only=environment
```

`config:clear` descarta una caché de configuración anterior. `about --only=environment` muestra el entorno que Laravel está usando; confirma aquí que la conexión y el entorno sean los esperados.

## 2. Producción nueva y sin datos ficticios

Ejecuta estos comandos **en este orden**:

```powershell
php artisan migrate:status
php artisan migrate --pretend --force
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan permission:cache-reset
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

| Comando | Qué hace |
| --- | --- |
| `migrate:status` | Lista las migraciones y debe mostrar todas como pendientes antes de la primera ejecución. |
| `migrate --pretend --force` | Enseña el SQL que se ejecutaría sin cambiar datos. `--force` permite hacerlo fuera de `local`. |
| `migrate --force` | Crea las tablas y estructuras de Velvet Master. |
| `RolePermissionSeeder` | Crea los roles y permisos base de Spatie. En una base vacía también establece sus asignaciones iniciales. |
| `VelvetMasterOperationalPermissionsSeeder` | Agrega los permisos granulares de la operación (turnos, plataformas, productividad, etc.) sin duplicarlos ni revocar permisos. Debe ir después del catálogo base. |
| `AdminUserSeeder` | Crea o actualiza solo la cuenta indicada en `VELVET_ADMIN_*` y le asigna `super_admin`. |
| `permission:cache-reset` | Elimina la caché de Spatie para que las asignaciones recién creadas se apliquen al iniciar sesión. |
| `config:cache`, `route:cache`, `view:cache` | Generan las cachés de Laravel para producción después de validar la configuración. |

Compila y publica los recursos de frontend antes de abrir la aplicación:

```powershell
npm ci
npm run build
php artisan queue:restart
```

`npm ci` instala exactamente las versiones registradas en `package-lock.json`; `npm run build` genera `public/build`; `queue:restart` reinicia workers persistentes para que carguen el código nuevo. Arranca o supervisa los workers de cola con el mecanismo de tu servidor (por ejemplo Supervisor), no con una terminal de desarrollo.

Comprobación mínima:

```powershell
php artisan migrate:status
php artisan route:list --name=login
```

Inicia sesión con el correo configurado en `VELVET_ADMIN_EMAIL`, cambia la contraseña inicial si fue compartida durante el despliegue y crea los usuarios reales desde Administración.

## 3. Desarrollo o staging con escenario demostrativo

Parte de una base vacía aislada. Después de configurar un `.env` local, ejecuta:

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=TrainingDataSeeder
php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder
php artisan permission:cache-reset
npm install
npm run build
```

`TrainingDataSeeder` borra y reconstruye únicamente la tabla de registros de capacitación. `TestOperationalModelsAndMonitorsSeeder` crea o actualiza tres modelos y dos monitores de prueba, sus perfiles operativos, asignaciones, turnos, asistencia, rooms, reservas y otros datos simulados. Este último llama internamente a `TestUsersSeeder`, por lo que no debes ejecutarlo aparte.

La contraseña de las cuentas de prueba es `ChangeMe123!` por defecto. Antes de ejecutar los seeders se puede cambiar solo para ese entorno:

```powershell
$env:VELVET_TEST_USERS_PASSWORD = 'UnaClaveSoloParaStaging123!'
php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder
```

## 4. Seeders que no deben ir a producción existente

En una instalación que ya contiene información real, sigue [DESPLIEGUE_PRODUCCION_SEGURO.md](DESPLIEGUE_PRODUCCION_SEGURO.md), no esta guía de inicio.

No ejecutes estos comandos sobre producción con datos:

```powershell
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:rollback
php artisan db:seed
php artisan db:seed --class=TrainingDataSeeder
php artisan db:seed --class=TestUsersSeeder
php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder
```

`db:seed` sin `--class` ejecuta `DatabaseSeeder`, que incluye `TrainingDataSeeder`. Los últimos tres seeders crean, actualizan o reconstruyen datos destinados a entrenamiento; no son parte de una operación real.

## 5. Resumen de elección

| Situación | Secuencia correcta |
| --- | --- |
| Producción totalmente nueva | Migraciones → roles base → permisos operativos → administrador → caché de permisos → cachés de Laravel. |
| Desarrollo/staging vacío | Migraciones → roles/permisos → administrador → capacitación → datos operativos de prueba → caché de permisos. |
| Producción ya existente | Solo migraciones pendientes y `VelvetMasterOperationalPermissionsSeeder`, siguiendo la guía de despliegue seguro. |

## 6. Cambios operativos posteriores al despliegue

Cuando se actualice una instalación existente, siempre se debe publicar primero el código y ejecutar las migraciones pendientes. El flujo de turnos utiliza la migración `2026_10_12_080000_add_room_reservation_to_operational_sessions.php` para vincular una reserva de room con su sesión operativa.

En una base existente, ejecutar:

```powershell
php artisan migrate --force
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force
php artisan permission:cache-reset
```

No ejecutes `migrate:fresh`, `migrate:refresh` ni `db:seed` sin `--class` sobre una base con información real.

### Flujo de turnos, reservas y métricas

1. Administración crea o programa el turno y, si corresponde, lo vincula a una room.
2. El monitor inicia la reserva desde su panel. La reserva pasa a `IN_USE`, se abre la asistencia y se registra una `operational_session`.
3. Al finalizar, la reserva pasa a `COMPLETED`, se cierran la asistencia y la sesión, y se calculan los minutos efectivos.
4. Las tarjetas del panel se actualizan con Inertia y los eventos Ably `room.reservation.updated` y `operation.attendance.updated`; no dependen de un temporizador de 20 segundos.
5. Si el turno está en Sandbox de inducción, solo se modifica el registro de práctica (`TrainingRecord`). No se crean sesiones ni métricas de operación real.

### Plataformas asignadas a monitores

El catálogo de plataformas y las cuentas Studio se configuran desde Administración. Un monitor puede asociar o retirar una cuenta existente únicamente de las modelos que tiene asignadas, mediante `studio_accounts.assign_assigned`; no puede crear cuentas, cambiar configuraciones ni consultar credenciales.

## 7. Configurar estaciones VELVET Agent

`php artisan migrate` incorpora las tablas de integración Agent. `DatabaseSeeder` ya llama a `VelvetMasterOperationalPermissionsSeeder`, que registra los permisos `stations.enroll`, `stations.configure`, `stations.revoke` y `stations.commands` para administración. Ningún seeder crea estaciones ni credenciales de dispositivos.

Para habilitar el enrolamiento, configurar `AGENT_MASTER_URL` (HTTPS público) y `AGENT_STUDIO_IDS` (registro explícito de estudios), reconstruir la caché de configuración y activar el scheduler de Laravel. Cada estación debe enrolarse mediante su propio token temporal emitido por la ruta administrativa protegida. No reutilizar contraseñas de usuarios de prueba como tokens de Agent.

Los pasos, rutas, tiempos de caducidad y precisiones del contrato están en [AGENT_INTEGRATION.md](AGENT_INTEGRATION.md).
