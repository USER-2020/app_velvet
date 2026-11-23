# PWA y responsive móvil

Velvet Master se compila como una Progressive Web App mediante
`vite-plugin-pwa`.

## Comportamiento

- La aplicación se puede instalar desde navegadores compatibles.
- El modo instalado usa `standalone` y respeta las áreas seguras de iPhone.
- El modo oscuro continúa siendo el valor predeterminado.
- La preferencia claro/oscuro actualiza también el color de la barra del
  navegador o del sistema.
- Las páginas y respuestas autenticadas no se guardan como HTML offline.
- El service worker precarga únicamente assets públicos compilados.
- Las nuevas versiones solicitan confirmación antes de actualizar para no
  interrumpir una sesión activa.

## iPhone y pantallas pequeñas

El viewport usa `viewport-fit=cover`, el contenido evita desbordamiento
horizontal y los controles táctiles tienen como mínimo 16 px. Esto evita el
autozoom de Safari al enfocar inputs y el zoom accidental por doble toque,
manteniendo el zoom manual accesible mediante gesto de pellizco.

## Verificación

1. Ejecuta `npm run build`.
2. Sirve la carpeta pública mediante HTTPS o `localhost`.
3. Comprueba el manifiesto y el service worker desde DevTools → Application.
4. Prueba instalación, cambio de tema, actualización pendiente y navegación
   con sesión autenticada.
