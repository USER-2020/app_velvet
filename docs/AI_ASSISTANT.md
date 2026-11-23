# Asistente IA: endpoints, permisos y contexto autorizado

Actualizado: 2026-11-13.

## Alcance

El asistente interno de Velvet se ejecuta a través de Groq, pero la API externa no recibe acceso directo a Laravel, a la base de datos ni a todos los controladores. El flujo es:

~~~text
Usuario autenticado
  -> POST /ai/chat
  -> GroqAssistantController::chat()
  -> GroqChatService::complete()
  -> GroqAssistantContextService::build()
  -> consultas de solo lectura autorizadas
  -> Groq
  -> respuesta al chat
~~~

La IA recibe únicamente el contexto seleccionado por el servidor para el usuario autenticado. No puede elegir una tabla, ejecutar una consulta SQL ni invocar una acción arbitraria.

## Endpoints

| Método | Endpoint | Controlador | Permiso | Función |
| --- | --- | --- | --- | --- |
| POST | /ai/chat | GroqAssistantController::chat | ai.chat | Recibe una pregunta y devuelve una respuesta |
| GET | /admin/ai-assistant | GroqAssistantController::index | ai.manage | Lee la configuración del asistente |
| PUT | /admin/ai-assistant | GroqAssistantController::update | ai.manage | Actualiza la configuración del asistente |

El POST /ai/chat es únicamente el transporte de la conversación. Aunque usa POST, no crea ni modifica registros operativos.

## Servicios de lectura utilizados por el asistente

La IA no llama directamente a otros endpoints HTTP. El servidor usa servicios internos:

| Servicio | Acceso actual | Datos entregados |
| --- | --- | --- |
| GroqChatService | Siempre dentro de ai.chat | Selección de modelo, prompt y llamada a Groq |
| GroqAssistantContextService | Según el rol y la pregunta | Contexto de estadísticas y documentación |
| RecruitmentDashboardService | admin, super_admin o candidates.view_all | Conteos agregados de leads, candidatos, pipeline, entrevistas, onboarding y activos |
| PlatformDocumentation y archivos permitidos de docs/ | Consulta documental | Extractos de documentación relevante |

## Estadísticas autorizadas

Cuando la pregunta contiene términos estadísticos, el servidor puede entregar:

- Métricas de los últimos 30 días.
- Variación porcentual frente al periodo anterior.
- Etapas y cantidades del pipeline.
- Distribución de modelos y monitores.
- Conteos de leads, candidatos, entrevistas, evaluaciones, onboarding y activos.

Las estadísticas se entregan como agregados. El contexto de IA no incluye nombres, correos, teléfonos, credenciales ni documentos personales.

Actualmente el resumen global de reclutamiento requiere una de estas condiciones:

- Rol admin.
- Rol super_admin.
- Permiso candidates.view_all.

Los roles sin ese alcance no reciben el contexto global aunque tengan ai.chat.

## Documentación autorizada

Cuando la pregunta solicita manuales, permisos, APIs o funcionamiento de la plataforma, el servidor busca coincidencias en:

- guia-plataforma.md
- VELVET_MASTER_PERMISSIONS.md
- AGENT_INTEGRATION.md
- AGENT_SESSIONS.md
- DESPLIEGUE_PRODUCCION_SEGURO.md
- VELVET_MASTER_ROADMAP.md
- MODULE_DOCUMENTATION_STANDARD.md
- PWA_RESPONSIVE.md
- Documentos personalizados publicados en el módulo Documentación

Solo se envían extractos limitados y relacionados con la pregunta. Los documentos no se exponen como un índice completo al modelo.

Cuando se consulta cómo crear o documentar un módulo, el asistente debe usar
`MODULE_DOCUMENTATION_STANDARD.md`: debe mencionar la guía Driver.js, la
demostración HyperFrames, el MP4, los permisos por rol y las pruebas. No debe
inventar que un módulo tiene video, ruta o acciones si esos elementos no están
registrados en el catálogo visible.

## Acciones bloqueadas

La IA no puede ejecutar ni solicitar directamente:

- Creación de candidatos, leads, usuarios o sesiones.
- Cambios de estados.
- Actualización o eliminación de registros.
- Asignación de modelos o monitores.
- Cambios de roles y permisos.
- Configuración del asistente.
- Reservas, turnos, asistencia o validaciones.
- Descarga de documentos privados.
- Lectura de API keys, tokens o credenciales.

Los endpoints de escritura de la plataforma mantienen sus propios middleware y permisos; no se incluyen en el contexto ni se registran como herramientas disponibles para la IA.

## Permisos

| Permiso | Propósito | Roles base |
| --- | --- | --- |
| ai.chat | Usar el chatbot | admin, super_admin y roles habilitados |
| ai.manage | Configurar el chatbot y sus modelos | admin, super_admin |

El checkbox Habilitar chatbot para los roles autorizados es una configuración global. Para usarlo deben cumplirse simultáneamente:

1. El asistente está habilitado en configuración.
2. Existe GROQ_API_KEY en el servidor.
3. El usuario tiene ai.chat.

## Interfaz del asistente

El asistente se integra como un elemento flotante y no ocupa espacio permanente en el layout de Velvet Master.

- Un único launcher flotante concentra las acciones de Velvet AI y las visitas guiadas.
- Al abrirlo, muestra las opciones disponibles de forma discreta y no invasiva.
- Velvet AI se abre como un popover sobre la vista actual, anclado al launcher.
- El modo rápido usa una ventana compacta de aproximadamente 380 x 500 px.
- El modo expandido usa hasta 650 x 700 px, limitado por el viewport disponible.
- La conversación, el texto escrito y el estado de generación se conservan al expandir, contraer o cerrar visualmente el panel.
- El panel se renderiza mediante un portal en `document.body` para evitar que el layout, sidebar o tarjetas del dashboard alteren su posición.
- El conector inferior pertenece al propio panel y se alinea dinámicamente con el launcher, incluso después de expandir o redimensionar la ventana.
- Las sugerencias rápidas solo se muestran si la persona tiene el permiso requerido para el módulo relacionado.
- Las referencias textuales a módulos conocidos, como “módulo de Sesiones”, se convierten en enlaces directos únicamente si la persona tiene el permiso de acceso correspondiente.
- Las respuestas del asistente permiten formato Markdown básico y copiar el contenido de una respuesta.
- `Escape` cierra el popover y `Enter` envía una pregunta; `Shift + Enter` conserva el salto de línea.

La interfaz se implementa en `resources/js/Components/GroqChatbot.jsx`, el launcher compartido en `resources/js/Components/ProductTour.jsx` y los estilos específicos en `resources/css/app.css`. Este rediseño no modifica los endpoints, servicios, permisos ni validaciones del backend.

## Seguridad y pruebas

- /ai/chat exige autenticación, ai.chat y límite de 20 solicitudes por minuto.
- La configuración exige ai.manage.
- El contexto se calcula en servidor y se limita por permisos.
- Las respuestas no deben afirmar que consultaron datos que no estén en el contexto recibido.
- Las pruebas de GroqAssistantTest cubren autorización, configuración, selección automática y fallback de modelos.

Para cambiar este alcance, primero debe agregarse una función de lectura explícita al GroqAssistantContextService, documentarse aquí y añadir una prueba de autorización. No se deben agregar consultas SQL libres ni herramientas de escritura al chat.
