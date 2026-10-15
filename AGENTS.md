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
