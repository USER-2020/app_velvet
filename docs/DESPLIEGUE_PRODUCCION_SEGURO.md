# Despliegue seguro a producción

Esta guía aplica los módulos operativos Velvet Master, Spatie Permission, los portales de modelo/monitor y la foto de perfil privada sin borrar información existente.

## Reglas no negociables

- Antes de empezar, generar y verificar un respaldo restaurable de la base de datos y de `storage/app/private` usando el mecanismo de backup del proveedor. No guardar contraseñas ni archivos de respaldo dentro del repositorio.
- Desplegar el código, las migraciones y los assets compilados antes de migrar. No modificar el archivo `.env` existente ni sustituir `APP_KEY`.
- Nunca ejecutar en producción: `php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan migrate:rollback`, `php artisan db:seed`, `TestUsersSeeder`, `TestOperationalModelsAndMonitorsSeeder`, `TrainingDataSeeder` ni `DatabaseSeeder`.
- No ejecutar `php artisan storage:link`. Documentos y fotos de perfil usan el disco privado `local` y se sirven mediante rutas autorizadas.

## Orden recomendado

Ejecutar los comandos desde la carpeta raíz de la versión ya desplegada. Si el servidor usa releases con enlaces simbólicos, hacerlo dentro del release nuevo antes de apuntar el enlace `current`.

### 1. Verificar el entorno y detener workers de forma controlada

Confirmar que producción conserva `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true` y sus secretos actuales. Activar mantenimiento solo si el equipo lo requiere:

```bash
php artisan down --render="errors::503"
```

Si hay colas, no detener procesos a la fuerza: al final se usará `queue:restart` para que terminen su trabajo y tomen el código nuevo.

### 2. Instalar dependencias PHP y publicar los assets

En el servidor, instalar solamente dependencias de producción:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
```

Compilar frontend preferiblemente en CI o en una estación controlada y subir el contenido generado de `public/build` junto con el release:

```bash
npm ci
npm run build
```

No usar `npm install` ni `npm run dev` en producción. Si el servidor no dispone de Node, omitir esos dos comandos allí: los assets ya compilados deben formar parte del release.

### 3. Revisar migraciones antes de escribir

Primero comprobar el estado y revisar el SQL que Laravel planea ejecutar:

```bash
php artisan migrate:status
php artisan migrate --pretend --force
```

Verificar que el listado corresponda a las migraciones esperadas y que no haya migraciones externas o no revisadas pendientes. Las migraciones Velvet Master de `2026_10_12_000000` a `2026_10_12_070000` son aditivas: crean tablas operativas/auditoría, copian pivots de RBAC a Spatie con `insertOrIgnore`, añaden columnas anulables para la foto de perfil y crean las invitaciones seguras de agenda de contratación. No eliminan registros existentes durante `up()`.

### 4. Aplicar migraciones

Si esta es la primera publicación de los módulos operativos, aplicar todas las migraciones pendientes:

```bash
php artisan migrate --force
```

Si `migrate:status` confirma que ya se aplicaron las migraciones operativas y únicamente aparece pendiente la foto de perfil, aplicar solo esa migración:

```bash
php artisan migrate --path=database/migrations/2026_10_12_050000_add_profile_photo_to_users_table.php --force
```

La migración de foto agrega `profile_photo_path` y `profile_photo_source` como columnas anulables en `users`; no modifica ni borra usuarios existentes.

La migración `2026_10_12_070000_create_contract_invitations_table.php` crea únicamente la tabla de enlaces temporales para que el candidato seleccione una franja de contratación. No cambia candidatos, documentos ni citas existentes.

Si una ejecución anterior de `2026_10_12_020000_create_platform_productivity_and_administration_tables` falló por el nombre largo de un índice MySQL, actualizar el código de esta versión y repetir únicamente:

```bash
php artisan migrate --force
```

La migración actual conserva tablas parciales y usa el índice corto `mpa_studio_account_identifier_uq`. No borrar tablas ni hacer rollback para resolver ese caso.

### 5. Registrar solo los permisos nuevos

Después de que las migraciones finalicen correctamente, ejecutar exclusivamente el seeder operativo:

```bash
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force
php artisan permission:cache-reset
```

Este seeder es idempotente: usa `Permission::firstOrCreate()` y `givePermissionTo()`, no usa `syncPermissions()`, no elimina permisos, no elimina pivots y no revoca personalizaciones de roles existentes.

No ejecutar `RolePermissionSeeder` como paso normal de actualización. Solo se evalúa en una instalación antigua que no tenga catálogo base de roles/permisos y después de restaurar un backup en un entorno de ensayo.

### 6. Restaurar cachés y workers

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Omitir `queue:restart` únicamente si el entorno no ejecuta workers. Si se usa Horizon, reiniciarlo con el procedimiento administrado de Horizon en lugar de matar procesos manualmente.

## Verificación posterior

Ejecutar estas comprobaciones sin crear datos de prueba:

```bash
php artisan migrate:status
php artisan route:list --name=profile.photo
php artisan permission:cache-reset
```

Validar manualmente con una cuenta real autorizada:

1. El dashboard carga según el rol y no aparecen módulos no autorizados.
2. Un monitor solo consulta modelos asignadas y gestiona sus rooms permitidas.
3. Una modelo ve su pipeline, sus rooms y su perfil sin acceder a datos de otra persona.
4. Desde **Mi perfil**, una foto JPG/PNG/WebP de hasta 5 MB se muestra en el avatar; el archivo queda en almacenamiento privado y `/profile/photo` requiere sesión autenticada.
5. Revisar `storage/logs/laravel.log` y el monitor de errores por excepciones de migración, permisos, colas o almacenamiento.

## Referencia de comandos

Ejecutar estos comandos desde la raíz del release de Laravel. Los comandos marcados como **condicionales** solo se usan si ese servicio existe en el servidor.

| Comando | Qué hace | Cuándo ejecutarlo |
| --- | --- | --- |
| `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` | Instala exactamente las dependencias PHP bloqueadas en `composer.lock`, excluye herramientas de desarrollo y optimiza el autoloader. No modifica datos de la base de datos. | En cada release de producción cuando cambie `composer.lock` o el código PHP. |
| `php artisan optimize:clear` | Elimina cachés de configuración, rutas, vistas y eventos que podrían referenciar código anterior. No elimina datos del negocio. | Después de publicar código nuevo y antes de migrar/cachear de nuevo. |
| `npm ci` | Instala exactamente las dependencias JavaScript de `package-lock.json` en un entorno de compilación limpio. | Solo en CI, una estación de compilación o un servidor que tenga Node autorizado. |
| `npm run build` | Genera los assets de producción de Vite en `public/build`. | Después de `npm ci`; subir `public/build` con el release si no se compila en el servidor. |
| `php artisan migrate:status` | Lista cada migración y muestra si está ejecutada o pendiente. Es de solo lectura. | Antes y después de cualquier migración. |
| `php artisan migrate --pretend --force` | Muestra el SQL de las migraciones pendientes sin ejecutar cambios. `--force` evita la confirmación interactiva de producción. | Siempre antes de `migrate --force`; revisar el resultado antes de continuar. |
| `php artisan migrate --force` | Ejecuta solo las migraciones pendientes registradas en `migrations`. No vuelve a ejecutar las ya aplicadas. | Para instalar los módulos operativos y cualquier migración nueva revisada. |
| `php artisan migrate --path=database/migrations/2026_10_12_050000_add_profile_photo_to_users_table.php --force` | Ejecuta únicamente la migración de foto de perfil: agrega dos columnas anulables a `users`. | Solo si `migrate:status` muestra pendiente exclusivamente esa migración. |
| `php artisan migrate --path=database/migrations/2026_10_12_070000_create_contract_invitations_table.php --force` | Ejecuta únicamente la tabla de invitaciones seguras para la agenda de contratación. | Solo si `migrate:status` muestra pendiente exclusivamente esta migración y sus dependencias ya están aplicadas. |
| `php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force` | Crea permisos operativos faltantes y los concede de forma aditiva a roles existentes mediante Spatie. Es idempotente y no revoca permisos. | Siempre después de migrar este release. |
| `php artisan permission:cache-reset` | Limpia la caché de Spatie Permission para que roles y permisos nuevos se reflejen inmediatamente. | Después del seeder operativo y después de cambios manuales de permisos. |
| `php artisan config:cache` | Genera la caché de configuración de producción desde el `.env` existente. | Al final del despliegue, cuando ya se confirmó que la configuración es correcta. |
| `php artisan route:cache` | Genera la caché de rutas para acelerar el enrutamiento. | Al final del despliegue, después de publicar rutas nuevas. |
| `php artisan view:cache` | Precompila vistas Blade. | Al final del despliegue; es seguro repetirlo. |
| `php artisan queue:restart` | Indica a los workers de cola que terminen el trabajo activo y reinicien con el código nuevo. | **Condicional**: solo si hay workers/Horizon supervisados. |
| `php artisan down --render="errors::503"` | Activa mantenimiento para evitar operaciones durante una ventana de despliegue. | Opcional, antes de migrar si el despliegue no es atómico o las migraciones requieren una pausa corta. |
| `php artisan up` | Desactiva el modo mantenimiento y vuelve a aceptar solicitudes. | Solo si se ejecutó previamente `artisan down`, después de todas las verificaciones. |
| `php artisan route:list --name=profile.photo` | Comprueba que la ruta privada que entrega fotos de perfil fue registrada. Es de solo lectura. | Verificación posterior de este release. |

### Comandos que no se deben ejecutar en producción

| Comando | Riesgo |
| --- | --- |
| `php artisan migrate:fresh` | Elimina todas las tablas y vuelve a migrar. Borra datos. |
| `php artisan migrate:refresh` | Hace rollback y vuelve a migrar; puede eliminar datos y romper compatibilidad. |
| `php artisan migrate:rollback` | Revierte migraciones; algunos `down()` eliminan tablas. No es un mecanismo de recuperación automática. |
| `php artisan db:seed` | Ejecuta `DatabaseSeeder`, que incluye datos administrativos y de entrenamiento. |
| `php artisan db:seed --class=TestUsersSeeder` | Crea o actualiza usuarios de prueba. |
| `php artisan db:seed --class=TestOperationalModelsAndMonitorsSeeder` | Crea modelos, monitores, rooms, reservas y datos `TEST-*`. |
| `php artisan db:seed --class=TrainingDataSeeder` | Reconstruye datos de capacitación/entrenamiento. |
| `php artisan storage:link` | Publicaría una ruta para archivos que deben permanecer privados; no es necesario para esta aplicación. |

## Seeders permitidos y prohibidos

| Seeder | Producción | Motivo |
| --- | --- | --- |
| `VelvetMasterOperationalPermissionsSeeder` | Sí, después de migrar | Agrega permisos operativos sin revocar ni duplicar. |
| `RolePermissionSeeder` | No de forma rutinaria | Es base histórica; revisar caso excepcional antes de usarlo. |
| `DatabaseSeeder` | No | Ejecuta seeders administrativos y de entrenamiento. |
| `AdminUserSeeder` | No | Puede alterar o crear cuentas administrativas. |
| `TrainingDataSeeder` | No | Reconstruye datos de entrenamiento. |
| `TestUsersSeeder` | No | Crea/actualiza usuarios de prueba. |
| `TestOperationalModelsAndMonitorsSeeder` | No | Crea modelos, monitores, rooms y actividad `TEST-*`. |

## Recuperación

No intentar "arreglar" producción con rollback automático. Si una migración falla, mantener el sitio en mantenimiento, guardar el error exacto, restaurar el backup solo siguiendo el procedimiento probado del proveedor y corregir/reintentar en staging primero. Las migraciones de Velvet Master están diseñadas para ser aditivas; una excepción concreta debe evaluarse antes de cualquier acción que borre o revierta datos.

## Actualización del flujo de turnos y reservas

El release que incorpora el inicio y finalización de turnos incluye la migración `2026_10_12_080000_add_room_reservation_to_operational_sessions.php`. Esta migración agrega un vínculo opcional y único entre `operational_sessions` y `room_reservations`; no elimina reservas ni sesiones existentes.

Después de publicar código, ejecutar el flujo normal:

```bash
php artisan migrate:status
php artisan migrate --pretend --force
php artisan migrate --force
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder --force
php artisan permission:cache-reset
php artisan queue:restart
```

Verificar en staging antes de liberar a producción:

1. Una reserva programada pasa a `IN_USE` al iniciar el turno y abre su asistencia/sesión operativa.
2. Al finalizar pasa a `COMPLETED`, cierra la sesión y conserva los minutos efectivos.
3. El panel del monitor refleja los cambios por Inertia y Ably, incluyendo hora real de inicio, fin y duración.
4. El banner de Sandbox deja claro que sus acciones no modifican la operación real.

Pruebas de regresión recomendadas antes del despliegue:

```bash
php artisan test --filter='StudioOperationsTest|OperationalProfilesTest'
npm run build
```
