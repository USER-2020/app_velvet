---
name: velvet-studio-context
description: Responde preguntas sobre Velvet Master y The Velvet Studio: arquitectura Laravel/Inertia/React, reclutamiento, operación interna, plataformas Studio simuladas, permisos, pruebas y convenciones. Úsala al consultar este software o su repositorio.
metadata:
  short-description: Contexto operativo y técnico de The Velvet Studio
---

# Velvet Master / The Velvet Studio

Esta skill convierte el contexto del proyecto en una referencia reutilizable para responder preguntas funcionales, técnicas y de QA. Velvet Master administra la operación interna de un estudio: reclutamiento, onboarding, modelos, monitores, turnos, salas, plataformas, sesiones, productividad y recursos.

## Cómo responder

- Lee primero la referencia relacionada con la pregunta; no cargues todas las referencias si no hacen falta.
- Si el repositorio está disponible, trata el código actual como fuente de verdad por encima de este snapshot. El snapshot fue preparado el 2026-10-12.
- Distingue hechos confirmados, inferencias y recomendaciones. No inventes rutas, permisos, estados, columnas, credenciales ni integraciones.
- Cuando respondas sobre implementación, menciona las rutas de archivo relevantes y explica el flujo backend → persistencia → Inertia/React.
- Cuando respondas sobre acceso, verifica siempre el permiso de la ruta y el permiso de la acción; no asumas que ver un módulo permite modificarlo.
- No expongas secretos ni valores reales del `.env`. Las cuentas de prueba y su contraseña documentada son datos de desarrollo, no credenciales de producción.
- Las plataformas externas no tienen cuentas ni credenciales oficiales todavía. Solo se permiten registros Manuales, importaciones y el adaptador `simulation`; no inventes endpoints ni afirmes que una sincronización es real.
- Marketplace, Finanzas y Velvet AI son aplicaciones independientes. Velvet Master solo conserva lotes locales de integración pendientes y nunca debe enviar información sin una integración autorizada.
- El repositorio actual es monostudio. No afirmes aislamiento multiestudio: requiere definir la entidad propietaria y una migración transversal autorizada.
- Para cambios nuevos respeta `AGENTS.md`: las funcionalidades con acciones relevantes deben tener visita Driver.js, progreso persistido y cobertura de prueba cuando afecten permisos, datos, flujos públicos o transiciones de estado.

El monitor puede asociar o retirar cuentas Studio existentes solo de modelos asignadas mediante `studio_accounts.assign_assigned`; no puede crear cuentas ni acceder a credenciales.
- El inicio y cierre de una reserva operativa sincroniza asistencia, `operational_sessions` y la tarjeta del panel. Ably acelera la actualización, pero la operación debe seguir funcionando si el servicio no está configurado.

## Referencias

- [project-overview.md](references/project-overview.md): producto, stack, estructura y vocabulario.
- [domain-and-workflows.md](references/domain-and-workflows.md): reclutamiento, onboarding, entrevistas, contratación, rooms y tiempo real.
- [architecture-and-routes.md](references/architecture-and-routes.md): backend, servicios, rutas y páginas Inertia.
- [roles-and-permissions.md](references/roles-and-permissions.md): roles, permisos y reglas de autorización.
- [frontend-and-tours.md](references/frontend-and-tours.md): React, layouts, Driver.js, overlay de carga y tours.
- [operations-and-testing.md](references/operations-and-testing.md): instalación, seeders, pruebas, CI y diagnóstico.
- [velvet-master-operations.md](references/velvet-master-operations.md): fases 1-6, plataformas, sesiones, productividad, administración e integraciones seguras.
- [permissions-operational.md](references/permissions-operational.md): RBAC operativo, mínimo privilegio y seeder incremental.
- [security-audit.md](references/security-audit.md): controles implementados, migración y dependencias externas de seguridad.

## Rutas de fuente en el repositorio

Para confirmar un detalle vigente, consulta `docs/VELVET_MASTER_ROADMAP.md`, `docs/VELVET_MASTER_PERMISSIONS.md`, `docs/VELVET_MASTER_SECURITY_AUDIT.md`, `AGENTS.md`, `routes/web.php`, `database/seeders/RolePermissionSeeder.php`, `database/seeders/VelvetMasterOperationalPermissionsSeeder.php`, `resources/js/lib/productTours.js`, los controladores y las migraciones. Las respuestas deben preferir esos archivos sobre cualquier descripción resumida aquí cuando exista una diferencia.

## Tipo de ayuda que debe ofrecer

La skill debe poder explicar el producto a una persona no técnica, ubicar la implementación de una funcionalidad, proponer cambios compatibles con los permisos y estados existentes, dar comandos Artisan/npm, analizar fallos de pruebas o rutas y diseñar regresiones. Si la pregunta depende de una versión externa de Laravel, React, Didit, Ably o ChatGPT, separa el contexto interno del proyecto de la documentación externa actual.
