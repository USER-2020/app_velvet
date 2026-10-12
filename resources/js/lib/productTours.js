export const TOUR_VERSION = 2;

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
    'monitor-portal': {
        available: hasPermission('rooms.view_team'),
        steps: [
            { element: 'a[href="/monitor/dashboard"]', popover: { title: 'Panel operativo', description: 'Empieza aquí para consultar reservas y modelos disponibles.' } },
            { element: 'nav.sidebar-scroll', popover: { title: 'Rooms y modelos', description: 'Desde este menú gestionas reservas, uso de rooms y modelos asignadas.' } },
            { element: 'main', popover: { title: 'Tu operación', description: 'Las acciones se aplican a tu equipo y respetan tus permisos de monitor.' } },
        ],
    },
    'monitor-models': {
        available: hasPermission('models.view_assigned'),
        steps: [
            { element: 'a[href="/monitor/models"]', popover: { title: 'Modelos asignadas', description: 'Consulta únicamente los perfiles que te fueron asignados para acompañamiento.' } },
            { element: 'main h1', popover: { title: 'Seguimiento del equipo', description: 'Abre un perfil para revisar información, notas y el seguimiento permitido.' } },
            { element: 'main', popover: { title: 'Acompañamiento', description: 'Registra novedades según tus permisos y respeta el alcance de tus asignaciones.' } },
        ],
    },
    'model-portal': {
        available: hasPermission('rooms.view_own'),
        steps: [
            { element: 'a[href="/model/dashboard"]', popover: { title: 'Tu panel', description: 'Consulta la disponibilidad y tus reservas personales.' } },
            { element: 'main', popover: { title: 'Reserva y registra tu uso', description: 'Desde esta pantalla puedes programar una room e indicar el inicio o cierre de uso.' } },
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
    if (path.startsWith('/admin/workflows')) return 'workflows';
    if (path.startsWith('/admin/training-data')) return 'training-data';
    if (path.startsWith('/admin/guided-tours')) return 'guided-tours';
    if (path.startsWith('/admin/users')) return 'users';
    if (path.startsWith('/admin/access')) return 'roles';
    if (path.startsWith('/admin/notifications')) return 'notifications';
    if (path.startsWith('/monitor/models')) return 'monitor-models';
    if (path.startsWith('/monitor')) return 'monitor-portal';
    if (path.startsWith('/model')) return 'model-portal';

    return 'platform-welcome';
}
