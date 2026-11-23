# Operación de colas y scheduler en producción

Guía para desplegar, observar, reintentar y limpiar las colas de Velvet Master
sin borrar datos accidentalmente.

## Qué está configurado

La aplicación usa la conexión `database` cuando `QUEUE_CONNECTION=database`.
Los trabajos pendientes viven en `jobs` y los fallidos en `failed_jobs`.

Actualmente hay un correo asíncrono:

- `CandidateActivatedMail`: confirma que un perfil quedó activo. Se encola al
  cambiar el candidato a `ACTIVE`, para que SMTP no bloquee la activación.
- `RoomReservationScheduledMail`: confirma una room programada para una
  modelo. Se encola una vez por cada franja creada y adjunta un evento `.ics`
  con la hora UTC correcta para que el calendario del destinatario lo importe.

También existe un scheduler:

- `agent:maintain`: se ejecuta cada minuto, caduca comandos de Agent, limpia
  respuestas de enrolamiento vencidas y marca estaciones desconectadas.

No hay Horizon configurado. En producción debe existir un `queue:work`
supervisado por Supervisor, systemd o el administrador de procesos del
proveedor.

## Qué hace cada job y tarea

| Nombre | Tipo | Cuándo se ejecuta | Qué hace | Qué no hace |
| --- | --- | --- | --- | --- |
| `CandidateActivatedMail` | Mailable encolado | Cuando un candidato pasa de `READY_TO_ACTIVATE` a `ACTIVE` | Renderiza el correo de perfil activo y lo entrega mediante el mailer configurado. Si falla, queda en `failed_jobs` para reintento. | No cambia el estado del candidato ni crea cuentas; eso ocurre antes, en la petición web. |
| `RoomReservationScheduledMail` | Mailable encolado | Cuando se crea una reserva de room asociada a una modelo | Envía la room, la franja en Bogotá y un `.ics` individual para el calendario. | No crea ni modifica la reserva; la reserva ya fue validada y persistida antes de encolar el correo. |
| `agent:maintain` | Comando programado | Cada minuto | Limpia respuestas de enrolamiento vencidas, caduca comandos pendientes, expira sesiones Agent sin heartbeat y marca estaciones offline. | No envía correos ni ejecuta comandos en los computadores Agent. |

No hay otros jobs de aplicación registrados con `ShouldQueue` ni despachos
automáticos adicionales relacionados con rooms. `inspire` es solo un comando
de ejemplo y no debe desplegarse como proceso operativo.

## Ejecutarlo localmente

Sí. La forma segura de probar correos localmente es usar el mailer `log`, no el
SMTP real de producción. En el `.env` local usa temporalmente:

~~~dotenv
APP_ENV=local
QUEUE_CONNECTION=database
MAIL_MAILER=log
MAIL_TIMEOUT=10
~~~

No ejecutes el worker local mientras `MAIL_MAILER=smtp` apunte a una cuenta real,
porque una activación de prueba podría enviar un correo verdadero.

Después de cambiar el `.env`:

~~~bash
php artisan optimize:clear
php artisan migrate
~~~

Abre tres terminales desde la raíz del proyecto:

~~~bash
# Terminal 1: aplicación
php artisan serve

# Terminal 2: procesa un job y se queda escuchando
php artisan queue:work database --queue=default --sleep=1 --tries=1 --timeout=20

# Terminal 3: ejecuta el scheduler local cada minuto
php artisan schedule:work
~~~

Para procesar solo el siguiente trabajo y terminar:

~~~bash
php artisan queue:work database --queue=default --once --tries=1 --timeout=20
~~~

Para probar únicamente el scheduler una vez:

~~~bash
php artisan schedule:run
~~~

Luego activa un perfil de prueba desde el dashboard. El cambio a `ACTIVE` se
guarda en la base de datos, `CandidateActivatedMail` aparece en `jobs`, el
worker lo procesa y el correo renderizado queda en `storage/logs/laravel.log`.
Con `MAIL_MAILER=log` no sale ningún correo de la máquina.

Para comprobar el estado local:

~~~bash
php artisan queue:failed
tail -f storage/logs/laravel.log
~~~

También puedes ejecutar el mantenimiento Agent manualmente sobre una base de
datos local de pruebas:

~~~bash
php artisan agent:maintain
~~~

Ese comando sí modifica sesiones, comandos y estaciones locales; no lo uses
contra una base de producción como prueba.

## Requisitos de producción

Comprobar antes de liberar:

- Existen `jobs`, `job_batches` y `failed_jobs`.
- `QUEUE_CONNECTION=database` está definido en el `.env` de producción.
- `DB_QUEUE=default` y `DB_QUEUE_RETRY_AFTER=90` son compatibles con el worker.
- `MAIL_TIMEOUT=10` está configurado o se usa el valor predeterminado de
  `config/mail.php`.
- Hay un worker permanente para la cola `default`.
- Hay un cron que ejecuta `schedule:run` cada minuto.
- El proceso de despliegue puede leer `storage/logs/laravel.log`.

No copiar credenciales SMTP, tokens ni el `.env` al repositorio.

## Desplegar una versión nueva

Ejecutar desde la raíz del release activo:

~~~bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --pretend --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
~~~

`queue:restart` permite que los workers terminen el trabajo actual y carguen el
código nuevo. No mata procesos a la fuerza.

No ejecutar `migrate:fresh`, `migrate:refresh` ni `migrate:rollback` en una
actualización normal.

## Worker de colas

~~~bash
php artisan queue:work database \
  --queue=default \
  --sleep=3 \
  --tries=3 \
  --backoff=10 \
  --timeout=60 \
  --max-time=3600
~~~

El timeout del worker debe ser menor que `DB_QUEUE_RETRY_AFTER`. El timeout
SMTP de 10 segundos evita bloquear indefinidamente el worker.

### Ejemplo de Supervisor

Adaptar rutas y usuario al proveedor. No incluir secretos:

~~~ini
[program:velvet-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/velvet/current/artisan queue:work database --queue=default --sleep=3 --tries=3 --backoff=10 --timeout=60 --max-time=3600
directory=/var/www/velvet/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/velvet/shared/storage/logs/worker.log
stopwaitsecs=70
~~~

Después de cambiar Supervisor:

~~~bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart velvet-worker:*
~~~

No iniciar workers duplicados fuera del supervisor.

## Scheduler de Laravel

`agent:maintain` está registrado en `routes/console.php` cada minuto:

~~~cron
* * * * * cd /var/www/velvet/current && php artisan schedule:run --quiet >> /dev/null 2>&1
~~~

Comprobar tareas:

~~~bash
php artisan schedule:list
~~~

El scheduler depende de la base de datos y del cache usado por
`withoutOverlapping`. Si falla, revisar `DB_*` y la conectividad.

## Revisar colas y errores

~~~bash
# Trabajos fallidos y sus IDs
php artisan queue:failed

# Log de Laravel en tiempo real
tail -f storage/logs/laravel.log

# Errores recientes
grep -E "ERROR|Exception|Failed" storage/logs/laravel.log | tail -100
~~~

Si Pail está instalado:

~~~bash
php artisan pail --timeout=0
~~~

Con `composer install --no-dev`, usar `tail` si Pail no está disponible.

## Reintentar trabajos fallidos

Después de corregir SMTP o conectividad:

~~~bash
php artisan queue:failed
php artisan queue:retry 42
php artisan queue:retry all
~~~

Revisar el error antes de reintentar. El proveedor podría haber aceptado un
correo aunque la conexión se cerrara antes de confirmar la respuesta.

## Limpiar colas

### Trabajos pendientes

Este comando descarta trabajos que aún no se han ejecutado. Es destructivo para
la entrega de correos:

~~~bash
php artisan queue:clear database --queue=default --force
~~~

Usarlo solo después de confirmar que esos trabajos ya no son válidos. No usar
`TRUNCATE jobs` ni borrar filas manualmente.

### Historial de fallidos

`queue:flush` elimina `failed_jobs`; no procesa ni reintenta trabajos:

~~~bash
# Todo el historial
php artisan queue:flush

# Conservar fallos de las últimas 168 horas
php artisan queue:flush --hours=168

# Eliminar solo un registro
php artisan queue:forget 42
~~~

Antes de limpiar, guardar el diagnóstico en el sistema de incidencias.

## Diagnóstico del correo de activación

1. Confirmar que el candidato está en `ACTIVE`.
2. Ejecutar `php artisan queue:failed`.
3. Revisar `storage/logs/laravel.log` y el log del worker.
4. Verificar host, puerto, cifrado y usuario SMTP sin imprimir secretos.
5. Confirmar `MAIL_TIMEOUT=10` y que el worker está `RUNNING`.
6. Ejecutar `php artisan config:cache` después de corregir el `.env`.
7. Reintentar con `php artisan queue:retry <id>`.

La activación se guarda aunque SMTP esté caído; el correo se entrega de forma
asíncrona y puede reintentarse.

## Comandos prohibidos para limpiar producción

- `php artisan migrate:fresh`.
- `php artisan migrate:refresh`.
- `php artisan migrate:rollback`.
- `php artisan db:seed` sin una clase explícita aprobada.
- `TRUNCATE jobs` o `TRUNCATE failed_jobs` directamente en MySQL.
- Borrar el log antes de guardar el diagnóstico.
- Iniciar workers duplicados fuera del supervisor.

La guía general está en
[DESPLIEGUE_PRODUCCION_SEGURO.md](DESPLIEGUE_PRODUCCION_SEGURO.md).
