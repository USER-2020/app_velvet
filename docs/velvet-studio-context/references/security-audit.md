# Seguridad y preparación de producción

El detalle vigente está en `docs/VELVET_MASTER_SECURITY_AUDIT.md`. La aplicación usa Spatie Laravel Permission 6.25 con guard `web`, migración aditiva desde los pivots históricos y seeders idempotentes. No existe un segundo sistema RBAC activo en runtime.

Controles implementados: rate limiting de login, regeneración/invalidez de sesión, bloqueo y revocación de sesiones de cuentas deshabilitadas, confirmación reciente de contraseña para cambios de roles/usuarios, cabeceras HTTP base, cookies seguras por defecto en producción, almacenamiento privado y autorización de documentos, validación de MIME/tamaño, auditoría con contexto redactado y filtrado de monitor por asignación vigente.

`composer audit --locked` fue verificado sin avisos después de actualizar `league/commonmark` a 2.10.2. Antes de una migración real hay que crear y probar un backup cifrado; la migración de Spatie no tiene rollback destructivo, por diseño.

Para corregir solo permisos operativos en producción: `php artisan migrate`, `php artisan db:seed --class=VelvetMasterOperationalPermissionsSeeder` y `php artisan permission:cache-reset`. No ejecutar `db:seed`, `TestUsersSeeder`, `TrainingDataSeeder`, `migrate:fresh`, `migrate:refresh` ni rollback como parte de ese despliegue.

Si MySQL detuvo `2026_10_12_020000_create_platform_productivity_and_administration_tables` por el nombre de índice largo de `model_platform_accounts`, actualizar el código y reintentar `php artisan migrate`. La migración es reintentable: conserva tablas parciales y añade el índice corto `mpa_studio_account_identifier_uq`; no borrar tablas.

Pendientes que no deben simularse: MFA TOTP con recuperación, análisis antimalware real de archivos, tenancy por estudio/sede, rotación de tokens de plataformas, alertas/SIEM, retención legal, CSP/CORS final, restricciones de red, secretos y backups/restauración en infraestructura.
