# Velvet Master — Fase 0: auditoría y fortalecimiento de seguridad

Actualizado: 2026-10-12.

## Alcance y criterio

Esta auditoría cubre el código versionado de Velvet Master. No certifica la configuración de servidores, proveedores, redes, cuentas Studio ni copias de producción: esos controles deben comprobarse en cada entorno antes de publicar.

## 01 · Migración segura a Spatie

- [x] `spatie/laravel-permission` 6.25 está instalado y es compatible con PHP 8.2 y Laravel 12.
- [x] Los modelos `Role` y `Permission` extienden los modelos Spatie y `User` usa `HasRoles`.
- [x] El middleware de rutas `permission:*`, `role` y `role_or_permission` es el oficial de Spatie.
- [x] La migración `2026_10_12_030000_adopt_spatie_permission_without_losing_legacy_access` añade los pivots Spatie y copia roles/permisos desde las tablas anteriores sin borrar datos.
- [x] El runtime ya no consulta `user_role` ni `role_permission`; se conservan únicamente como respaldo histórico. Los slugs existentes se resuelven de forma compatible sobre Spatie.
- [x] El guard único es `web`; los seeders y pruebas lo verifican.
- [ ] Antes de ejecutar en una base existente, crear una copia cifrada y probada. La reversión de la migración es deliberadamente no destructiva: restaurar el backup es el procedimiento de retorno.

Procedimiento controlado:

```powershell
# 1. Crear y verificar backup fuera del repositorio.
php artisan migrate --pretend
php artisan migrate
# 2. Solo corrige permisos operativos nuevos; no carga datos de prueba.
php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder
php artisan permission:cache-reset
```

No ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:rollback` ni `db:seed` en producción para este despliegue. `DatabaseSeeder` también llama a `AdminUserSeeder` y `TrainingDataSeeder`; este último reconstruye su dataset. `TestUsersSeeder` crea/actualiza cuentas de prueba.

`RolePermissionSeeder` solo se ejecuta si falta el catálogo base de roles/permisos y tras revisar el backup: es aditivo, pero conserva una compatibilidad que puede renombrar el rol histórico `reviewer` a `hr` cuando no exista este último.

### Recuperación de una migración detenida por MySQL

La migración `2026_10_12_020000_create_platform_productivity_and_administration_tables` usa el índice corto `mpa_studio_account_identifier_uq`, compatible con el límite de 64 caracteres de MySQL. Si una versión anterior se detuvo por el error de identificador largo, actualizar el código y ejecutar de nuevo `php artisan migrate`. La migración ahora detecta las tablas parciales, las conserva y agrega únicamente el índice faltante; no usar `migrate:fresh`, rollback ni borrar tablas para resolverlo. Ejecutar el seeder operativo solamente después de que `migrate` finalice correctamente.

## 02 · Roles, permisos y seeders

- [x] `RolePermissionSeeder` conserva el catálogo base y usa API aditiva de Spatie.
- [x] `VelvetMasterOperationalPermissionsSeeder` es independiente, idempotente, de mínimo privilegio y no revoca permisos existentes.
- [x] La caché de Spatie se limpia antes y después de sembrar.
- [x] Superadministrador mantiene su bypass explícito; los demás roles se validan por permiso y policy.
- [x] Cambios de roles, permisos y usuarios se registran en `audit_logs` sin secretos.

## 03 · Autenticación

- [x] Login usa límite de cinco intentos por correo/IP, filtra `is_active`, regenera sesión y el logout invalida sesión y token CSRF.
- [x] Usuarios deshabilitados son expulsados en la siguiente petición; con driver `database` se eliminan además sus sesiones persistidas.
- [x] Cambiar roles, crear/editar/eliminar usuarios requiere `password.confirm` reciente.
- [x] Cookies son `HttpOnly`, `SameSite=lax` y se activan cifrado/`Secure` por defecto si `APP_ENV=production` y no se sobrescriben por entorno.
- [ ] MFA no está implementado todavía. Requiere definir TOTP corporativo, recuperación, enrolamiento obligatorio de roles privilegiados y soporte operativo; no se habilita una simulación insegura.

## 04 · Autorización y aislamiento

- [x] Las rutas nuevas utilizan middleware Spatie y los controladores comprueban acciones sensibles.
- [x] `CandidatePolicy` limita a monitores a sus modelos con asignación vigente; el dashboard de monitor también filtra su listado por esa relación.
- [x] `LeadDocumentPolicy` protege vistas/descargas administrativas por permiso; el portal conserva además la comprobación estricta de propiedad por candidato.
- [x] Hay regresiones para 403, permisos mínimos y manipulación de alcance de modelos asignadas.
- [ ] El repositorio es monostudio: no existe entidad `studio_id`/tenancy. No añadir filtros ficticios; el aislamiento por estudio y sede requiere primero definir propietario de cada registro, migración transversal y plan de datos aprobado.
- [ ] Jobs, APIs y exportaciones futuras deben recibir el actor/ámbito explícito y autorizarlo antes de ejecutar. No hay una API pública de operación en el alcance actual.

## 05 · Datos y documentos

- [x] Documentos se almacenan en el disco `local` privado (`storage/app/private`), no en el disco público.
- [x] Preview/download requieren autorización, validan existencia y dejan auditoría de acceso sin registrar contenido, ruta ni nombre del archivo.
- [x] Cargas de identidad y contratación limitan tipo (`pdf`, `jpg`, `jpeg`, `png`), MIME detectado y tamaño máximo.
- [ ] El análisis antimalware requiere un servicio configurado (por ejemplo ClamAV) y una cola de cuarentena. No se declara un archivo seguro sin un escáner real.
- [ ] La retención, anonimización y eliminación segura requieren una política legal aprobada y un calendario de conservación.

## 06 · Plataformas Studio e integraciones

- [x] `StudioAccount.encrypted_credentials` permanece oculto y las credenciales se cifran antes de persistirse; solo existe el adaptador `simulation`.
- [x] No se inventaron endpoints ni credenciales reales; las simulaciones y lotes importados están identificados por origen.
- [ ] Rotación/revocación de tokens, firmas de webhooks, límites de proveedor y reintentos solo pueden activarse con documentación, contratos y credenciales oficiales de cada plataforma.
- [ ] Importadores CSV/Excel requieren revisión adicional de fórmulas, límites de filas y análisis antimalware al seleccionar el proveedor de escaneo.

## 07 · Auditoría y monitoreo

- [x] `audit_logs` registra actor, evento, sujeto, IP, agente y contexto redactado para roles, usuarios y accesos a documentos.
- [x] Asignaciones de modelo/monitor, sesiones y métricas ya conservan historial operativo/auditado en sus registros de dominio.
- [ ] Alertas de accesos indebidos repetidos requieren un destino operativo (correo, SIEM o pager) y umbrales aprobados.
- [ ] El acceso al almacenamiento de logs y su retención deben configurarse en infraestructura; no se almacenan tokens, contraseñas ni credenciales mediante `AuditLogger`.

## 08 · Infraestructura y despliegue

- [x] La aplicación rechaza arrancar en producción si `APP_DEBUG=true`.
- [x] Cabeceras base: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS cuando la petición ya es HTTPS.
- [x] Los proxies confiables ya no usan `*`; se configuran mediante `TRUSTED_PROXIES`.
- [ ] Producción debe usar `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, HTTPS terminado en proxy confiable y valores reales de `TRUSTED_PROXIES`.
- [ ] Base de datos, Redis, secretos, backups cifrados, CORS, CSP y restauraciones son controles de infraestructura que deben verificarse en el proveedor de despliegue.
- [x] `composer audit --locked` se ejecutó tras actualizar `league/commonmark` de 2.10.1 a 2.10.2; no quedan avisos de dependencias.
- [ ] Ejecutar `composer audit`, pruebas, build y revisión manual de configuración antes de cada lanzamiento.

## Pruebas de esta fase

`SecurityHardeningTest` cubre sesión deshabilitada, cabeceras y reautenticación/auditoría de roles. `AccessControlTest`, `VelvetMasterOperationalPermissionsSeederTest` y las pruebas operativas cubren Spatie, mínimo privilegio y accesos denegados.
