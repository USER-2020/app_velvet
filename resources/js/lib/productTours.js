export const TOUR_VERSION = 5;

const hasPermission = (permission) => (can) => !permission || can(permission);

export const productTours = {
    'platform-welcome': {
        title: 'Conoce tu espacio de trabajo',
        available: () => true,
        steps: [
            { element: 'aside.flex.h-full', popover: { title: 'Navegación según tu acceso', description: 'Este menú muestra solo los módulos que puedes consultar o gestionar con tus permisos.' } },
            { element: 'nav.sidebar-scroll', popover: { title: 'Tus módulos', description: 'Usa estas secciones para avanzar en tus tareas. El elemento activo indica dónde estás.' } },
            { element: 'main', popover: { title: 'Zona de trabajo', description: 'Aquí aparecen las acciones y la información del módulo que seleccionaste.' } },
            { element: 'header [aria-haspopup="menu"]', popover: { title: 'Tu cuenta', description: 'Desde aquí puedes revisar tu perfil y cerrar sesión. El botón Guía permite repetir cualquier recorrido.' } },
        ],
    },
    pipeline: {
        available: hasPermission('dashboard.view'),
        steps: [
            { element: 'a[href="/admin/recruitment"]', popover: { title: 'Pipeline', description: 'Este es tu resumen operativo: muestra el avance de leads y candidatos en el proceso.' } },
            { element: 'main h1', popover: { title: 'Indicadores principales', description: 'Revisa las métricas para identificar prioridades y continuar con el módulo adecuado.' } },
            { element: 'main', popover: { title: 'Siguiente acción', description: 'Usa Leads para solicitudes nuevas y Candidatos para las personas que ya avanzaron en el proceso.' } },
        ],
    },
    leads: {
        available: hasPermission('leads.view'),
        steps: [
            { element: 'a[href="/admin/leads"]', popover: { title: 'Leads', description: 'Aquí llegan las solicitudes nuevas enviadas desde el onboarding público.' } },
            { element: 'main', popover: { title: 'Gestiona cada solicitud', description: 'Busca una persona, abre su aplicación y continúa el proceso: contacto, entrevista o descarte.' } },
        ],
    },
    'lead-flow': {
        available: hasPermission('leads.view'),
        steps: [
            { element: 'main h1', popover: { title: 'Detalle de la aplicación', description: 'Aquí ves la información completa del lead y su estado actual dentro del proceso.' } },
            { element: '[data-tour="lead-flow"]', popover: { title: 'Flujo del lead', description: 'Esta tarjeta resume la etapa actual. Cada cambio se guarda y se actualiza para el equipo.' } },
            { element: 'main .mt-4.flex', optional: true, popover: { title: 'Siguiente paso recomendado', description: 'Sigue esta recomendación para contactar, calificar, convertir o avanzar al candidato según el estado actual.' } },
            { element: 'main section', popover: { title: 'Información para decidir', description: 'Revisa los datos de la aplicación antes de actualizar el estado o convertir el lead en candidato.' } },
            { element: 'main aside', popover: { title: 'Historial y contacto', description: 'Consulta la actividad registrada y usa correo o WhatsApp para continuar el seguimiento.' } },
            { element: '[data-tour="contract-documents-upload"]', optional: true, popover: { title: 'Documentos de contratación', description: 'Cuando la persona está en contratación, carga aquí el contrato firmado y los soportes finales para mantener completo su expediente.' } },
        ],
    },
    candidates: {
        available: hasPermission('candidates.view'),
        steps: [
            { element: 'a[href="/admin/candidates"]', popover: { title: 'Candidatos', description: 'Consulta personas que ya están dentro del proceso de selección.' } },
            { element: 'main', popover: { title: 'Siguiente etapa', description: 'Desde el detalle puedes actualizar datos, estado, documentos, entrevistas y acceso según tu permiso.' } },
        ],
    },
    'candidate-flow': {
        available: hasPermission('candidates.view'),
        steps: [
            { element: 'main h1', popover: { title: 'Perfil del candidato', description: 'Aquí consultas el perfil y el estado actual de la persona dentro del proceso.' } },
            { element: '[data-tour="candidate-profile"]', popover: { title: 'Información de la persona', description: 'Revisa los datos enviados durante la aplicación antes de realizar cambios en el proceso.' } },
            { element: '[data-tour="candidate-process"]', popover: { title: 'Flujo del candidato', description: 'Selecciona una etapa para actualizar el avance. El cambio se guarda y se comparte en tiempo real con el equipo.' } },
            { element: '[data-tour="candidate-actions"]', popover: { title: 'Acciones recomendadas', description: 'Esta columna muestra las tareas disponibles según la etapa: formulario, verificación, contratación, inducción o activación.' } },
            { element: '[data-tour="contract-documents-upload"]', optional: true, popover: { title: 'Documentos de contratación', description: 'Asocia el contrato firmado y los soportes finales directamente al expediente del candidato.' } },
            { element: '[data-tour="operational-profile"]', optional: true, popover: { title: 'Expediente operativo', description: 'Consulta el onboarding y las capacitaciones. Para modelos activas, gestiona la asignacion o reasignacion de monitor y conserva su historial.' } },
        ],
    },
    interviews: {
        available: hasPermission('interviews.view'),
        steps: [
            { element: 'a[href="/admin/interviews"]', popover: { title: 'Entrevistas', description: 'Revisa las entrevistas agendadas y su estado.' } },
            { element: 'main', popover: { title: 'Agenda y seguimiento', description: 'Quien tenga permiso de programación puede crear franjas, invitar y reprogramar entrevistas.' } },
        ],
    },
    contracting: {
        available: hasPermission('contracts.create'),
        steps: [
            { element: 'a[href="/admin/contracting"]', popover: { title: 'Contratación', description: 'Centraliza las citas y los documentos requeridos para formalizar el ingreso.' } },
            { element: 'main', popover: { title: 'Formaliza el proceso', description: 'Programa la cita, solicita documentos y registra la finalización según tu rol.' } },
        ],
    },
    onboarding: {
        available: hasPermission('onboarding.view'),
        steps: [
            { element: 'a[href="/admin/processes"]', popover: { title: 'Procesos', description: 'Consulta el avance de onboarding y detecta los pendientes.' } },
            { element: 'main', popover: { title: 'Acompaña el ingreso', description: 'Este módulo concentra el seguimiento previo a la activación.' } },
        ],
    },
    validations: {
        available: hasPermission('documents.verify'),
        steps: [
            { element: 'a[href="/admin/validations"]', popover: { title: 'Validaciones', description: 'Aquí se concentran los documentos y requisitos pendientes de verificación.' } },
            { element: 'main h1', popover: { title: 'Revisa pendientes', description: 'Abre cada caso para aprobar, solicitar correcciones o continuar con el siguiente paso autorizado.' } },
            { element: 'main', popover: { title: 'Control documental', description: 'Esta revisión mantiene el proceso listo para onboarding o contratación.' } },
        ],
    },
    documents: {
        available: hasPermission('documents.view'),
        steps: [
            { element: 'a[href="/admin/documents"]', popover: { title: 'Documentos', description: 'Consulta archivos enviados por las personas en proceso.' } },
            { element: 'main', popover: { title: 'Validación documental', description: 'Los permisos de RH o compliance habilitan las acciones de revisión y aprobación.' } },
        ],
    },
    calendar: {
        available: hasPermission('calendar.view'),
        steps: [
            { element: 'a[href="/admin/calendar"]', popover: { title: 'Calendario', description: 'Consulta las actividades y fechas del proceso.' } },
            { element: 'main', popover: { title: 'Organiza las actividades', description: 'Con permiso de gestión puedes crear, completar, editar o eliminar eventos.' } },
        ],
    },
    rooms: {
        available: hasPermission('rooms.view'),
        steps: [
            { element: 'a[href="/admin/rooms"]', popover: { title: 'Rooms', description: 'Administra la disponibilidad y las reservas de las salas según tu nivel de acceso.' } },
            { element: 'main', popover: { title: 'Operación de salas', description: 'Las acciones mostradas dependen de si puedes crear, administrar o solo consultar rooms.' } },
        ],
    },
    operations: {
        available: hasPermission('shifts.view_team'),
        steps: [
            { element: 'main h1', popover: { title: 'Operacion diaria', description: 'Programa turnos sin confundir la planificacion con la asistencia real.' } },
            { element: '[data-tour="operation-shifts"]', popover: { title: 'Turnos y asistencia', description: 'Cada turno puede reservar una sala. La entrada, pausa y salida se registran por separado.' } },
            { element: '[data-tour="operation-incidents"]', optional: true, popover: { title: 'Control operativo', description: 'Registra incidencias y novedades para que queden trazables.' } },
        ],
    },
    platforms: {
        available: hasPermission('platforms.view'),
        steps: [
            { element: 'main h1', popover: { title: 'Cuentas Studio', description: 'Registra plataformas y cuentas sin habilitar conexiones externas.' } },
            { element: '[data-tour="platform-safety"]', popover: { title: 'Proteccion de datos', description: 'Las credenciales se cifran y nunca se muestran. Usa Manual o Simulado hasta contar con una integracion oficial.' } },
            { element: '[data-tour="platform-accounts"]', popover: { title: 'Cuentas y simulaciones', description: 'Vincula cuentas de modelos y prueba un flujo simulado que no contacta al proveedor.' } },
            { element: '[data-tour="platform-import"]', popover: { title: 'Reportes oficiales', description: 'Registra importaciones manuales para conservar el origen y la trazabilidad de los datos.' } },
        ],
    },
    productivity: {
        available: hasPermission('reports.operational.view'),
        steps: [
            { element: 'main h1', popover: { title: 'Sesiones y productividad', description: 'Registra la sesion operativa y sus transmisiones asociadas.' } },
            { element: '[data-tour="productivity-summary"]', popover: { title: 'Horas sin duplicados', description: 'La duracion se calcula una vez por sesion, aunque existan transmisiones simultaneas.' } },
            { element: '[data-tour="productivity-sessions"]', popover: { title: 'Seguimiento auditable', description: 'Cierra sesiones y revisa su origen antes de validar la produccion.' } },
        ],
    },
    administration: {
        available: hasPermission('branches.manage'),
        steps: [
            { element: 'main h1', popover: { title: 'Recursos del estudio', description: 'Administra sedes, activos y mantenimientos internos.' } },
            { element: '[data-tour="administration-resources"]', popover: { title: 'Control operativo', description: 'Registra cada recurso con su sede y programa mantenimiento preventivo o correctivo.' } },
        ],
    },
    workflows: {
        available: hasPermission('settings.manage'),
        steps: [
            { element: 'a[href="/admin/workflows"]', popover: { title: 'Workflows', description: 'Configura la secuencia y reglas que estructuran los procesos internos.' } },
            { element: 'main h1', popover: { title: 'Diseña el flujo', description: 'Revisa cada etapa antes de modificarla para conservar una operación consistente.' } },
            { element: 'main', popover: { title: 'Configuración sensible', description: 'Los cambios aquí afectan a los equipos que usan el proceso.' } },
        ],
    },
    'training-data': {
        available: hasPermission('settings.manage'),
        steps: [
            { element: 'a[href="/admin/training-data"]', popover: { title: 'Datos de prueba', description: 'Administra los datos utilizados por los entornos de inducción y práctica.' } },
            { element: 'main h1', popover: { title: 'Entorno de entrenamiento', description: 'Úsalo para preparar escenarios sin afectar la operación real.' } },
            { element: 'main', popover: { title: 'Uso controlado', description: 'Verifica la información antes de reiniciar o modificar datos de prueba.' } },
        ],
    },
    'guided-tours': {
        available: hasPermission('super_admin'),
        steps: [
            { element: 'a[href="/admin/guided-tours"]', popover: { title: 'Visitas guiadas', description: 'Este módulo centraliza el control global de las guías contextuales de la plataforma.' } },
            { element: 'main h1', popover: { title: 'Control global', description: 'Solo el Super Administrador puede definir si las visitas guiadas están disponibles para todas las personas.' } },
            { element: '[role="switch"]', popover: { title: 'Activar o desactivar', description: 'Al desactivar esta opción se ocultan los recorridos automáticos y el botón para repetirlos. Los avances de cada usuario se conservan.' } },
        ],
    },
    documentation: {
        available: hasPermission('super_admin'),
        steps: [
            { element: 'a[href="/admin/documentation"]', popover: { title: 'Centro de documentación', description: 'Consulta en un solo lugar las APIs, manuales y referencias técnicas de Velvet Master.' } },
            { element: '[data-tour="documentation-references"]', popover: { title: 'Documentación oficial', description: 'Estas referencias se mantienen vinculadas a los documentos versionados del proyecto, incluido el contrato OpenAPI del Agent.' } },
            { element: '[data-tour="documentation-swagger"]', popover: { title: 'Swagger / OpenAPI', description: 'Explora las APIs creadas para conectar Agent con Velvet Master: métodos, rutas, parámetros, respuestas y modelos de datos.' } },
            { element: '[data-tour="documentation-agent-sessions"]', popover: { title: 'Sesiones y métricas', description: 'Consulta cómo Master separa el uptime técnico de la estación del tiempo de trabajo de cada usuario y registra OBS.' } },
            { element: '[data-tour="documentation-modules"]', popover: { title: 'Manual de módulos', description: 'Usa este catálogo como mapa funcional de Velvet Master y entra directamente al módulo que quieras revisar.' } },
            { element: '[data-tour="documentation-upload"]', popover: { title: 'Anexos y conocimiento interno', description: 'Carga archivos o escribe una nota para conservar documentación relevante junto al manual de la plataforma.' } },
        ],
    },
    agent: {
        available: hasPermission('stations.enroll'),
        steps: [
            { element: 'a[href="/admin/agent"]', popover: { title: 'VELVET Agent', description: 'Administra las estaciones que se conectan con Velvet Master y revisa su estado de conexión.' } },
            { element: '[data-tour="agent-overview"]', popover: { title: 'Estado de la conexión', description: 'Consulta cuántas estaciones están conectadas, offline o revocadas y verifica la URL pública de Master.' } },
            { element: '[data-tour="agent-token"]', popover: { title: 'Tokens de enrolamiento', description: 'Genera o regenera un token temporal para registrar una estación nueva. El token se muestra una sola vez y caduca automáticamente.' } },
            { element: '[data-tour="agent-stations"]', popover: { title: 'Estaciones conectadas', description: 'Cada estación muestra estudio, versión, última actividad, configuración y credenciales disponibles.' } },
        ],
    },
    'work-sessions': {
        available: hasPermission('metrics.view_own') || hasPermission('models.view_metrics') || hasPermission('dashboard.view'),
        steps: [
            { element: '[data-tour="work-sessions-header"]', popover: { title: 'Sesiones de trabajo', description: 'Consulta el tiempo de trabajo autenticado separado del uptime técnico de la estación.' } },
            { element: '[data-tour="work-sessions-summary"]', popover: { title: 'Resumen de tiempos', description: 'Revisa sesiones activas, tiempo conectado y tiempo transmitiendo calculados por Master.' } },
            { element: '[data-tour="work-sessions-table"]', popover: { title: 'Detalle de sesiones', description: 'La modelo ve sus sesiones, el monitor sus modelos asignadas y administración todas.' } },
        ],
    },
    users: {
        available: hasPermission('users.view'),
        steps: [
            { element: 'a[href="/admin/users"]', popover: { title: 'Usuarios', description: 'Consulta y administra las cuentas que tu nivel de acceso permite gestionar.' } },
            { element: 'main h1', popover: { title: 'Accesos internos', description: 'Crea una cuenta o abre una existente para actualizar sus datos y roles.' } },
            { element: 'main', popover: { title: 'Principio de mínimo acceso', description: 'Asigna únicamente los roles y permisos que la persona necesita para su labor.' } },
        ],
    },
    roles: {
        available: hasPermission('roles.manage'),
        steps: [
            { element: 'a[href="/admin/access"]', popover: { title: 'Roles y permisos', description: 'Define qué puede consultar y gestionar cada perfil de la plataforma.' } },
            { element: 'main h1', popover: { title: 'Control de acceso', description: 'Edita un rol para revisar sus permisos antes de asignarlo a usuarios.' } },
            { element: 'main', popover: { title: 'Cambios con impacto', description: 'Modificar permisos afecta a todas las personas que tienen ese rol.' } },
        ],
    },
    notifications: {
        available: hasPermission('dashboard.view'),
        steps: [
            { element: 'header button.relative', popover: { title: 'Notificaciones', description: 'Aquí recibes alertas de actividad y cambios relevantes para tus procesos.' } },
            { element: 'main h1', popover: { title: 'Historial de alertas', description: 'Consulta las notificaciones para volver rápidamente a la persona o proceso relacionado.' } },
        ],
    },
    profile: {
        available: () => true,
        steps: [
            { element: 'main h1', popover: { title: 'Mi perfil', description: 'Desde aquí actualizas los datos de tu cuenta y tus preferencias de acceso.' } },
            { element: '[data-tour="profile-photo"]', popover: { title: 'Foto de perfil privada', description: 'Tu foto se guarda internamente. Para modelos, la selfie verificada de Didit se usa como valor inicial cuando está disponible y puedes reemplazarla.' } },
        ],
    },
    'monitor-portal': {
        available: hasPermission('rooms.view_team'),
        steps: [
            { element: 'a[href="/monitor/dashboard"]', popover: { title: 'Panel del equipo', description: 'Empieza aquí para consultar el pipeline y los indicadores de tus modelos asignadas.' } },
            { element: '[data-tour="monitor-statistics"]', popover: { title: 'Indicadores de tu equipo', description: 'Las cifras incluyen únicamente modelos que tienes asignadas.' } },
            { element: '[data-tour="monitor-pipeline"]', popover: { title: 'Pipeline del equipo', description: 'Compara por semana las sesiones y tokens del equipo que tienes asignado.' } },
            { element: 'a[href="/monitor/rooms"]', popover: { title: 'Gestiona rooms', description: 'La programación y el control de uso de rooms están en su módulo separado.' } },
        ],
    },
    'monitor-rooms': {
        available: hasPermission('rooms.view_team'),
        steps: [
            { element: 'a[href="/monitor/rooms"]', popover: { title: 'Rooms del equipo', description: 'Programa y controla reservas solo para las modelos que tienes asignadas.' } },
            { element: 'main', popover: { title: 'Control de uso', description: 'Puedes programar, iniciar, finalizar o cancelar reservas autorizadas. La configuración física pertenece a Administración.' } },
        ],
    },
    'monitor-models': {
        available: hasPermission('models.view_assigned'),
        steps: [
            { element: 'a[href="/monitor/models"]', popover: { title: 'Modelos asignadas', description: 'Consulta únicamente los perfiles que te fueron asignados para acompañamiento.' } },
            { element: 'main h1', popover: { title: 'Seguimiento del equipo', description: 'Abre un perfil para revisar información, notas y el seguimiento permitido.' } },
            { element: '[data-tour="monitor-platform-management"]', optional: true, popover: { title: 'Plataformas asignadas', description: 'Asocia o desvincula cuentas existentes de las modelos que tienes asignadas. Las credenciales y configuraciones sensibles permanecen protegidas.' } },
            { element: 'main', popover: { title: 'Acompañamiento', description: 'Registra novedades según tus permisos y respeta el alcance de tus asignaciones.' } },
        ],
    },
    'model-portal': {
        available: hasPermission('rooms.view_own'),
        steps: [
            { element: 'a[href="/model/dashboard"]', popover: { title: 'Tu panel', description: 'Consulta tu pipeline y los indicadores asociados solo a tu cuenta.' } },
            { element: '[data-tour="model-statistics"]', popover: { title: 'Indicadores propios', description: 'Estas estadísticas se calculan solo con tus sesiones, turnos y métricas registradas.' } },
            { element: '[data-tour="model-agent-live"]', popover: { title: 'Estado en tiempo real', description: 'Este bloque se actualiza con los heartbeats de tu VELVET Agent y muestra el estado de tu estación y OBS.' } },
            { element: '[data-tour="model-pipeline"]', popover: { title: 'Pipeline personal', description: 'Compara tu actividad y producción por semana. Este resumen nunca incluye datos de otras modelos.' } },
            { element: 'a[href="/model/rooms"]', popover: { title: 'Gestiona tus rooms', description: 'La agenda de rooms está separada del panel para que programes tu uso sin modificar las salas físicas.' } },
        ],
    },
    'model-rooms': {
        available: hasPermission('rooms.view_own'),
        steps: [
            { element: 'a[href="/model/rooms"]', popover: { title: 'Mis rooms', description: 'Consulta disponibilidad y agenda solo tus propias reservas.' } },
            { element: '[data-tour="model-rooms-availability"]', popover: { title: 'Disponibilidad', description: 'Las rooms físicas las crea y configura Administración. Desde aquí solo puedes solicitar las disponibles.' } },
            { element: '[data-tour="model-rooms-reservations"]', popover: { title: 'Tus reservas', description: 'Inicia o finaliza únicamente tus propias reservas, según tus permisos y el estado de la agenda.' } },
        ],
    },
    'model-profile': {
        available: hasPermission('profile.view_own'),
        steps: [
            { element: 'a[href="/model/profile"]', popover: { title: 'Mi perfil operativo', description: 'Aquí consultas únicamente la información vinculada a tu propia cuenta.' } },
            { element: '[data-tour="model-profile"]', popover: { title: 'Estado operativo', description: 'Revisa tu incorporación, monitor asignado y datos personales.' } },
            { element: '[data-tour="model-agent-connection"]', popover: { title: 'Conexión VELVET Agent', description: 'Consulta únicamente la estación asociada a tu cuenta, el estado de OBS y el tiempo conectado o transmitiendo.' } },
            { element: '[data-tour="model-documents"]', popover: { title: 'Documentos privados', description: 'Puedes ver y descargar solo los documentos de tu expediente.' } },
            { element: '[data-tour="model-platforms"]', popover: { title: 'Plataformas', description: 'Se muestran identificadores y estados autorizados; las credenciales nunca se exponen.' } },
        ],
    },
};

export function tourKeyForPath(url) {
    const path = url.split('?')[0];

    if (path === '/admin' || path === '/admin/recruitment') return 'pipeline';
    if (/^\/admin\/leads\/[^/]+$/.test(path)) return 'lead-flow';
    if (path.startsWith('/admin/leads')) return 'leads';
    if (/^\/admin\/candidates\/[^/]+$/.test(path)) return 'candidate-flow';
    if (path.startsWith('/admin/candidates')) return 'candidates';
    if (path.startsWith('/admin/interviews')) return 'interviews';
    if (path.startsWith('/admin/contracting')) return 'contracting';
    if (path.startsWith('/admin/processes')) return 'onboarding';
    if (path.startsWith('/admin/validations')) return 'validations';
    if (path.startsWith('/admin/documents')) return 'documents';
    if (path.startsWith('/admin/calendar')) return 'calendar';
    if (path.startsWith('/admin/rooms')) return 'rooms';
    if (path.startsWith('/admin/operations')) return 'operations';
    if (path.startsWith('/admin/platforms')) return 'platforms';
    if (path.startsWith('/admin/productivity')) return 'productivity';
    if (path.startsWith('/admin/administration')) return 'administration';
    if (path.startsWith('/admin/workflows')) return 'workflows';
    if (path.startsWith('/admin/training-data')) return 'training-data';
    if (path.startsWith('/admin/guided-tours')) return 'guided-tours';
    if (path.startsWith('/admin/documentation')) return 'documentation';
    if (path.startsWith('/admin/agent')) return 'agent';
    if (path.endsWith('/work-sessions')) return 'work-sessions';
    if (path.startsWith('/admin/users')) return 'users';
    if (path.startsWith('/admin/access')) return 'roles';
    if (path.startsWith('/admin/notifications')) return 'notifications';
    if (path === '/profile') return 'profile';
    if (path.startsWith('/monitor/models')) return 'monitor-models';
    if (path.startsWith('/monitor/rooms')) return 'monitor-rooms';
    if (path.startsWith('/monitor')) return 'monitor-portal';
    if (path.startsWith('/model/profile')) return 'model-profile';
    if (path.startsWith('/model/rooms')) return 'model-rooms';
    if (path.startsWith('/model')) return 'model-portal';

    return 'platform-welcome';
}
