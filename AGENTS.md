# Convenciones del proyecto

## Visitas guiadas

Cada módulo, flujo o componente nuevo que tenga una acción relevante para una
persona usuaria debe incorporar una guía contextual con Driver.js.

- Define la guía en `resources/js/lib/productTours.js` con una clave propia.
- Registra la clave en `TourProgressController` para persistir su progreso.
- Asocia la guía a su ruta en `tourKeyForPath`, evitando que se ejecute en un
  módulo diferente.
- Espera a que los elementos requeridos estén montados antes de iniciar el
  recorrido y marca como opcionales los elementos condicionales.
- El recorrido debe guardarse por usuario al finalizar o cerrarse, y poder
  repetirse desde el botón flotante `?`.
- Respeta siempre la configuración global de **Visitas guiadas** administrada
  exclusivamente por el rol Super Administrador.
- Incluye o actualiza pruebas de flujo cuando el comportamiento sea crítico.

## Pruebas y CI

Las nuevas funcionalidades deben incluir pruebas de feature o regresión cuando
afecten permisos, flujos públicos, datos persistidos o transiciones de estado.
El workflow de GitHub Actions en `.github/workflows/ci.yml` debe seguir pasando
en cada push y pull request.

## Consistencia visual y Light Mode

Las secciones existentes y nuevas dentro de un portal deben verse completas y
legibles en ambos temas, sin heredar superficies oscuras ilegibles al activar
Light Mode.

- Usa tokens visuales del módulo, como --model-surface, --model-border,
  --model-text y --model-muted, en lugar de fijar colores oscuros en tarjetas,
  listas, estados o formularios.
- Añade los overrides bajo html[data-theme='light'] para tarjetas,
  encabezados, separadores, campos, calendarios, popovers, badges y estados de
  éxito o error.
- Mantén contraste accesible, foco visible, estados hover y una jerarquía
  clara en desktop y responsive.
- Verifica visualmente cada módulo nuevo en Dark Mode y Light Mode antes de
  considerarlo terminado; el cambio de tema no debe modificar el layout ni
  ocultar acciones.

## Módulos nuevos, documentación y demostraciones

Una funcionalidad nueva no se considera terminada solo porque la pantalla
funcione. Cada módulo o flujo nuevo debe integrarse de forma aditiva, conservar
las rutas existentes y entregar su documentación visible dentro de
**Administración → Documentación**.

- Añade el módulo al catálogo de `resources/documentation/dashboard.php` con su
  rol, permisos, ruta y pasos reales.
- Define la guía Driver.js en `resources/js/lib/productTours.js`, registra su
  clave en `TourProgressController` y asóciala exclusivamente a su ruta.
- Crea una demostración interactiva tipo HyperFrames en la ruta de guía, con
  pasos, cursor/acciones simuladas, datos ficticios y controles para avanzar,
  retroceder y reproducir.
- Genera y versiona el MP4 explicativo en
  `resources/documentation/videos/{clave}.mp4`. La documentación debe mostrar
  el video cuando exista y conservar la demostración HTML como alternativa.
- Filtra documentación, enlaces y acciones según el rol; nunca expongas en la
  guía datos personales, credenciales o acciones que el usuario no pueda usar.
- Actualiza las referencias visibles del asistente IA y su documentación cuando
  el nuevo módulo cambie el alcance de consultas autorizadas.
- Añade pruebas de permisos, ruta, guía, demostración y video cuando el flujo
  sea crítico. No se deben crear módulos sin estado vacío, error, carga y
  responsive definidos.

La referencia completa está en `docs/MODULE_DOCUMENTATION_STANDARD.md`.
