# Estándar de módulos, guías y demostraciones

Este contrato aplica a cualquier módulo nuevo de Velvet Master. Su objetivo es
que una funcionalidad pueda descubrirse, entenderse y repetirse sin depender
de una explicación externa.

## Entrega mínima

1. Ruta y permiso nuevos, sin modificar ni romper las rutas existentes.
2. Estado de carga, vacío, error, éxito y responsive.
3. Entrada en `resources/documentation/dashboard.php` con grupo, nombre,
   ruta, permiso de lectura, permisos opcionales y pasos reales.
4. Visita contextual Driver.js con clave propia, persistencia por usuario y
   repetición desde el launcher `?`.
5. Demostración HyperFrames visible en Documentación. Debe simular el flujo
   con cursor, clics, navegación y datos ficticios; nunca debe ejecutar cambios
   sobre producción.
6. Video MP4 en `resources/documentation/videos/{clave}.mp4`, enlazado por el
   catálogo. El HTML interactivo se mantiene como fallback accesible.
7. Pruebas de autorización, render de documentación, guía y video. Si el
   módulo cambia estados o datos persistidos, incluir prueba de regresión.

## Integración con el dashboard

Las funciones nuevas se conectan desde las tarjetas o acciones recomendadas
del dashboard, pero no sustituyen el menú ni las pantallas existentes. Una
acción debe llevar a la persona al módulo, completar el paso y reflejar el
resultado en el estado, la bitácora y el resumen correspondiente.

## Responsabilidad del asistente

El asistente IA solo puede explicar módulos que estén en este catálogo o en la
documentación publicada. Puede enlazar a la pantalla si el permiso lo permite,
pero no puede afirmar que existe un flujo, video o acción que no esté registrado
en el catálogo. Para ampliar su contexto se debe actualizar
`GroqAssistantContextService`, documentar el alcance y agregar pruebas de
autorización.

## Lista de verificación

- [ ] La ruta existente sigue funcionando.
- [ ] El módulo tiene permisos y estados de UI completos.
- [ ] El catálogo visible incluye pasos y roles correctos.
- [ ] La visita Driver.js espera a que sus elementos estén montados.
- [ ] Existe demostración HyperFrames con datos ficticios.
- [ ] Existe MP4 reproducible y alternativa HTML.
- [ ] El asistente conoce la documentación autorizada.
- [ ] Las pruebas pasan en CI.
