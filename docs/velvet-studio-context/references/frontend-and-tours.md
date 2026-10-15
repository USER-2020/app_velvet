# Frontend, layouts y visitas guiadas

## Entrada React

`resources/js/app.jsx` configura Inertia, resuelve páginas con `import.meta.glob('./Pages/**/*.jsx')`, aplica layouts declarados por cada página y monta `ProductTour` junto a la página. También contiene:

- `PublicRouteOverlay`, overlay editorial de carga para rutas públicas;
- `DashboardRouteSkeleton`, skeleton durante navegación entre rutas administrativas/dashboard;
- toasts globales para mutaciones CRUD;
- consentimiento y preferencias de cookies.

El layout administrativo principal es `resources/js/Pages/Admin/Recruitment/Layout.jsx`. Filtra el menú por `can(permission)`, monta sidebar, header, notificaciones, perfil y contenido. Los layouts `PortalLayout`, `GuestLayout` y `AuthenticatedLayout` cubren otras superficies.

## Páginas importantes

- Públicas: `Public/Landing`, `Public/Apply`, `Public/Prequalification`, `Public/InterviewBooking`, `Public/ContractingDocuments`, páginas de éxito y legales.
- Administración: `Admin/Recruitment/Dashboard`, Leads, Candidates, Interviews, Contracting, Processes, Validations, Documents, Calendar, Rooms, Workflows, TrainingData, Access, Users, Notifications y GuidedTours.
- Portal: `Portal/Model/Dashboard`, `Portal/Monitor/Dashboard`, `Portal/Monitor/Overview` y modelos asignadas.

## Driver.js

Las definiciones están en `resources/js/lib/productTours.js` y el lanzador en `resources/js/Components/ProductTour.jsx`.

Claves actuales: `platform-welcome`, `pipeline`, `leads`, `lead-flow`, `candidates`, `candidate-flow`, `interviews`, `contracting`, `onboarding`, `validations`, `documents`, `calendar`, `rooms`, `operations`, `platforms`, `productivity`, `administration`, `workflows`, `training-data`, `guided-tours`, `users`, `roles`, `notifications`, `monitor-portal`, `monitor-models`, `model-portal`.

`tourKeyForPath()` asocia rutas a claves, por ejemplo:

- `/admin` y `/admin/recruitment` → `pipeline`;
- `/admin/leads/{id}` → `lead-flow`;
- `/admin/candidates/{id}` → `candidate-flow`;
- `/admin/operations`, `/admin/platforms`, `/admin/productivity` y `/admin/administration` → sus tours de módulo;
- `/monitor/*` → `monitor-portal` o `monitor-models`;
- `/model/*` → `model-portal`.

El tour filtra por permisos, espera los elementos requeridos y marca pasos condicionales como opcionales. El progreso se guarda con `PUT /tour-progress/{tourKey}` en `user_tour_progresses`; cerrar marca `skipped`, terminar marca `completed`. El botón flotante `?` permite repetir el tour actual.

`TOUR_VERSION` permite invalidar progreso antiguo cuando cambia una guía. Las claves aceptadas por backend están en `TourProgressController::TOUR_KEYS`; una nueva guía debe actualizar ambos lados.

## Configuración global

`SystemSetting` guarda `guided_tours` como JSON `{ "enabled": true|false }`. `GuidedTourSettingsController` permite leer/cambiarlo solo a `super_admin`/`super-admin`. `HandleInertiaRequests` comparte `guidedTours.enabled`; cuando es falso, React no inicia ni muestra recorridos.

## Regla de montaje y overlay

Un tour nunca debe iniciarse solo porque exista el selector: debe esperar a que el componente esté montado y a que no estén visibles `.apply-intro-overlay`, `.dashboard-route-skeleton` o un skeleton de módulo. El evento `velvet-intro-overlay-complete` y el estado del DOM ayudan a coordinar el inicio. Al agregar un selector, confirmar que no apunte a un elemento de otro módulo y que los pasos condicionales sean `optional`.

## Estilo de cambios frontend

`OperationsPageHeader` es el encabezado compartido de Administracion, Plataformas y Productividad. En móvil apila contenido; desde `lg` alinea título, descripción y acciones. Los formularios operativos se organizan en tarjetas y pasan de una a dos o tres columnas según el ancho.

`UserFormModal` admite hasta 72rem y altura limitada al viewport; su header y footer son fijos y solo el contenido central usa `velvet-scrollbar`.

`UserFormModal` (Administración de accesos) prioriza la lectura de roles: admite hasta `max-w-6xl`, `max-h-[94vh]` y usa tres columnas desde pantallas `xl`; en móvil conserva márgenes y columnas adaptativas.

- Mantener el lenguaje visual oscuro editorial Velvet: negro/morado, bordes discretos, tipografía editorial para títulos.
- Preferir componentes existentes y clases del proyecto antes de crear variantes duplicadas.
- Usar Inertia `router` para navegación/mutaciones y respetar toasts/flash existentes.
- Comprobar responsive en tablas, modales, paneles y tours.

## Navegacion lateral administrativa

La aplicacion usa Inertia (`@inertiajs/react`) como router de la superficie React; no se debe incorporar React Router DOM en paralelo porque duplicaria el enrutamiento y el ciclo de navegacion de Laravel/Inertia. Los enlaces del sidebar deben seguir usando `Link` de Inertia para navegar sin recarga completa de pagina.

`Admin/Recruitment/Layout.jsx` conserva el `scrollTop` de cada `.sidebar-scroll` por usuario en `sessionStorage`. Despues de una navegacion, verifica el enlace activo y mueve solamente el contenedor lateral, con el minimo desplazamiento necesario, para que el modulo seleccionado quede visible. No debe hacer scroll de la pagina principal ni devolver el menu al inicio.

`PublicRouteOverlay` solo debe ejecutarse para visitas `GET` a rutas publicas. Los `POST` de login y logout pueden conservar `/login` o `/logout` como URL de la visita de Inertia incluso cuando Laravel redirige al dashboard; tratarlos como rutas publicas deja una capa de carga sobre el portal del nuevo usuario y aparenta que el primer ingreso fallo.

## Foto de perfil privada

La foto de perfil se guarda en el disco privado `local` bajo `profile-photos/{user_id}`; nunca se entrega una ruta de almacenamiento ni se publica mediante `storage:link`. `GET /profile/photo` exige autenticacion y solo entrega la foto de la cuenta actual.

Para una modelo con identidad Didit aprobada, `ProfilePhotoService` copia la selfie/retrato temporal disponible al almacenamiento privado como valor inicial cuando se crea el acceso o al abrir el perfil si antes no pudo copiarse. Una foto cargada desde `/profile` reemplaza la predeterminada y se marca como `manual`, por lo que una nueva sincronizacion Didit no la sobrescribe. Se aceptan JPG, PNG y WebP de hasta 5 MB. La guia `profile` explica esta accion.

## Portales operativos de modelo y monitor

`/model/dashboard` muestra estadisticas calculadas solo para la candidata vinculada a la cuenta: sesiones, tiempo, tokens registrados, turnos y reservas. La modelo puede agendar y usar sus propias rooms; no puede crear rooms fisicas.

`/model/profile` es el perfil privado de la modelo. Incluye datos operativos, documentos con vista/descarga autorizada y cuentas de plataforma sin credenciales. La guia `model-profile` esta registrada en `productTours.js`, `tourKeyForPath` y `TourProgressController`.

`/monitor/dashboard` calcula indicadores solo para modelos con una asignación vigente al monitor. `/monitor/models/{candidate}` conserva esa misma autorización y muestra datos operativos y estado de plataformas, pero no entrega documentos privados ni credenciales. Al crear una reserva, el backend vuelve a verificar que la modelo esté asignada al monitor. La gestión visual de plataformas usa el selector `[data-tour="monitor-platform-management"]` dentro del tour `monitor-models`.

### Separacion de dashboard y rooms de modelo

`/model/dashboard` es el pipeline personal. Muestra las metricas y la grafica de actividad de seis semanas, siempre calculadas solo desde la candidata vinculada a la cuenta autenticada. No contiene acciones de administracion de salas.

`/model/rooms` es el modulo independiente para consultar disponibilidad, agendar, iniciar y finalizar reservas propias. Crear o configurar las rooms fisicas permanece en Administracion. La guia `model-rooms` se registra en `productTours.js`, `tourKeyForPath` y `TourProgressController`.

### Separacion de dashboard y rooms de monitor

`/monitor/dashboard` es el pipeline del equipo: presenta metricas y evolucion semanal agregadas exclusivamente de las modelos con asignacion vigente al monitor. No expone la agenda ni controles de rooms en este dashboard.

`/monitor/rooms` concentra la programacion y el control de uso de rooms para esas modelos asignadas. El monitor no puede crear ni configurar rooms fisicas. La guia `monitor-rooms` se registra en `productTours.js`, `tourKeyForPath` y `TourProgressController`.
