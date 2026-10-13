# The Velvet Studio: visión general

## Producto

Velvet Master es la plataforma operativa interna de The Velvet Studio. Recibe aplicaciones públicas, gestiona leads y candidatos, coordina onboarding y habilita la operación posterior de modelos y monitores: turnos, asistencia, salas, plataformas, sesiones, productividad, inventario y mantenimiento.

El producto tiene dos superficies principales:

1. **Superficie pública**: landing/login, aplicación, precalificación, reserva de entrevista, carga de documentos de contratación y páginas legales.
2. **Superficie autenticada**: panel administrativo de reclutamiento/configuración y portales operativos separados para modelos y monitores.
3. **Operación Master**: pantallas administrativas de Operación diaria, Plataformas, Sesiones y Administración.

## Stack confirmado

- Backend: PHP 8.2+, Laravel 12, Eloquent, sesiones y validación Laravel.
- UI: Inertia Laravel 2, React 18, Vite 7, Tailwind CSS 3, CSS propio en `resources/css/app.css`.
- Componentes/UI: Radix UI, Headless UI, React Icons, React Hot Toast, React Select, React Day Picker, Framer Motion y `driver.js`.
- Tiempo real: Ably opcional mediante llamadas REST; si no hay API key, las operaciones normales continúan sin publicación externa.
- Verificación de identidad: Didit opcional mediante workflow, webhook y sesiones firmadas.
- Correo: Laravel Mail; el entorno local usa `MAIL_MAILER=log` por defecto.
- Persistencia local por defecto: SQLite según `.env.example`; el proyecto también puede configurarse con MySQL u otro driver compatible.
- Colas, cache y sesiones usan drivers configurables; el ejemplo utiliza database para queue, cache y session.

## Estructura importante

| Ubicación | Responsabilidad |
| --- | --- |
| `app/Actions` | Operaciones de dominio reutilizables, como crear leads y convertir leads en candidatos. |
| `app/Enums` | Estados y tipos de dominio. |
| `app/Http/Controllers` | Entrada HTTP, autorización, validación y composición de respuestas Inertia. |
| `app/Http/Middleware` | Permisos, perfil operativo activo y props compartidas de Inertia. |
| `app/Models` | Modelos Eloquent y relaciones. |
| `app/Services` | Dashboard, notificaciones, tiempo real y servicios de correo. |
| `app/Services/Platforms` | Contrato de adaptadores y simulador seguro; no contiene conectores reales. |
| `app/Mail` | Correos transaccionales de aplicación, precalificación, entrevistas y contratación. |
| `database/migrations` | Esquema incremental. |
| `database/seeders` | Roles/permisos, usuarios, administrador y dataset de entrenamiento. |
| `resources/js/Pages` | Páginas Inertia públicas, administrativas y de portal. |
| `resources/js/Components` | Componentes React reutilizables, incluido `ProductTour`. |
| `resources/js/lib` | Autorización del frontend, fechas, Ably, etiquetas y tours. |
| `resources/css/app.css` | Diseño Velvet, overlay de carga, skeletons y estilos globales. |
| `routes/web.php` y `routes/auth.php` | Rutas funcionales y autenticación. |
| `tests/Feature` | Flujos HTTP y regresiones de negocio. |
| `.github/workflows/ci.yml` | CI de PHP y frontend. |

## Vocabulario

- **Lead**: solicitud recibida desde el onboarding público antes de convertirse en candidato.
- **Candidate**: entidad operativa derivada de un lead; tiene estado, tipo, actividad y eventualmente usuario de portal.
- **Modelo**: tipo de candidato `MODEL`; cuenta con portal y rooms propias cuando tiene acceso.
- **Monitor**: tipo de candidato `MONITOR`; acompaña modelos asignadas y usa el portal de monitor.
- **Room**: sala/recurso operativo que puede estar disponible, reservado, en uso o en mantenimiento.
- **Training mode / sandbox**: dataset aislado para inducción de perfiles operativos no activos.
- **Visita guiada**: recorrido contextual Driver.js cuyo progreso se guarda por usuario.
- **Super Administrador**: rol con bypass de permisos y control exclusivo de la activación global de tours.
- **Cuenta Studio**: cuenta administrativa de una plataforma; sus secretos se guardan cifrados y solo puede operar en modo `MANUAL` o `SIMULATED` mientras no exista un conector oficial.
- **Sesión operativa**: intervalo de trabajo de una modelo; es la fuente de duración para evitar duplicar horas de transmisiones paralelas.

## Reglas transversales

- El acceso debe validarse en backend con middleware/permisos; ocultar un enlace en React no es seguridad.
- Los cambios relevantes de leads, candidatos, entrevistas y operación registran actividad y pueden producir notificaciones.
- Los enlaces públicos de precalificación, entrevistas y contratación se protegen con URLs firmadas y/o tokens; no deben reutilizarse después de consumirse.
- Los cambios críticos deben ser transaccionales o idempotentes cuando un reintento del navegador pueda repetirse.
- La interfaz debe ser responsive y evitar desplazamiento horizontal innecesario fuera de tablas controladas.
