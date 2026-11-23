<?php

return [
    'roles' => [
        'super_admin' => 'Supervisa todos los módulos. Gestiona las visitas guiadas globales, los datos de prueba y la publicación de documentación.',
        'admin' => 'Coordina reclutamiento, contratación, accesos y operación del estudio. Consulta las guías y referencias desde Documentación.',
        'recruiter' => 'Revisa leads, inicia la precalificación al convertirlos, organiza entrevistas y registra el resultado en el perfil de la persona.',
        'hr' => 'Sigue el onboarding, revisa identidad y documentos y coordina la formalización de la contratación.',
        'manager' => 'Organiza turnos, salas y reservas, atiende incidencias y gestiona los usuarios dentro de su alcance.',
        'operations_director' => 'Combina la supervisión de operación, salas y equipos con el seguimiento documental y contractual.',
        'monitor' => 'Consulta tus modelos asignadas, sus sesiones, las reservas de equipo y las estrategias que puedes gestionar.',
        'model' => 'Usa el portal para consultar tu actividad, reservar salas, revisar tu perfil y gestionar tu wishlist.',
        'candidate' => 'Completa tu aplicación y las etapas de selección desde los enlaces que recibes por correo.',
        'compliance' => 'Consulta la evidencia de identidad y revisa documentos dentro del alcance autorizado.',
        'marketing' => 'Consulta el panel y la documentación según tus permisos. El sistema define permisos de marketing sin un módulo de campañas en el menú actual.',
        'finance' => 'Consulta el panel y las guías disponibles. La exportación operativa requiere su permiso específico y el acceso al módulo desde el que se ejecuta.',
        'support' => 'Consulta la documentación y el panel según tus permisos. La gestión de solicitudes operativas está en Operación diaria para los usuarios autorizados.',
    ],

    // Each module declares its dashboard entry point. Optional step permissions
    // are evaluated separately so read access never implies write access.
    'modules' => [
        'pipeline' => ['Reclutamiento', 'Pipeline', 'admin.recruitment', 'dashboard.view', [
            ['Revisar el resumen', 'Consulta indicadores y etapas para ubicar solicitudes pendientes y expedientes que requieren seguimiento.'],
            ['Abrir el registro', 'Entra al detalle de un lead o candidato desde el módulo correspondiente si tienes acceso.'],
            ['Identificar el siguiente paso', 'Confirma la etapa en el historial y usa las acciones disponibles en el expediente.'],
        ]],
        'leads' => ['Reclutamiento', 'Leads', 'admin.leads', 'leads.view', [
            ['Localizar la solicitud', 'Busca por nombre, correo o código. Abre el lead y revisa los datos enviados y el historial. La aplicación pública entra como lead nuevo.'],
            ['Gestionar la solicitud', 'Actualiza los datos o el estado desde el detalle. Si corresponde descartar el lead, registra el motivo.', 'leads.manage'],
            ['Convertir a candidato', 'Usa la conversión disponible en el detalle. Al crear el candidato, se intenta enviar la precalificación por correo y el resultado queda en el historial.', 'candidates.convert'],
        ]],
        'candidates' => ['Reclutamiento', 'Candidatos', 'admin.candidates', 'candidates.view', [
            ['Abrir un expediente', 'Filtra por estado o tipo. Abre el perfil y revisa el estado del proceso, el historial y la recomendación.'],
            ['Gestionar la precalificación', 'Comprueba si se envió por correo y si Didit concluyó. Cuando se completa y hay franjas futuras, el sistema intenta invitar a entrevista. Si se pausa, revisa el motivo en el historial.', 'candidates.request_documents'],
            ['Registrar la entrevista', 'Sube las notas en PDF cuando corresponda para entregar el expediente a evaluación.', 'candidates.update'],
            ['Decidir el avance', 'Revisa el expediente y aplica la transición que ofrece la etapa actual, por ejemplo, marcar como admitido.', 'candidates.change_status'],
            ['Descartar con motivo', 'Usa Descartar cuando corresponda y registra el motivo solicitado.', 'candidates.reject'],
            ['Gestionar el acceso', 'En la etapa habilitada, usa la acción de credenciales o de activación. Revisa el estado del acceso desde el perfil.', 'candidates.activate_access'],
        ]],
        'interviews' => ['Reclutamiento', 'Entrevistas', 'admin.interviews.index', 'interviews.view', [
            ['Consultar la agenda', 'Revisa próximas franjas y el estado de las invitaciones. Los horarios se muestran en la zona indicada en el módulo.'],
            ['Generar horarios', 'Completa Desde, Hasta, Primera hora, Última hora y Días de atención. Genera las franjas de una hora y comprueba que estén disponibles.', 'candidates.schedule_interview'],
            ['Enviar y seguir la invitación', 'Selecciona el candidato y envía la invitación. La persona elige una cita desde su enlace. Revisa la reserva, actualiza el resultado o reprograma cuando corresponda.', 'candidates.schedule_interview'],
        ]],
        'contracting' => ['Reclutamiento', 'Contratación', 'admin.contracting.index', 'contracts.create', [
            ['Iniciar contratación', 'Desde el candidato admitido, envía los requisitos documentales. La persona recibe el enlace de carga y el expediente queda en espera.', 'candidates.view'],
            ['Preparar las franjas', 'Genera horarios por fecha, hora y día de atención. Revisa las próximas franjas y distingue las disponibles de las reservadas.'],
            ['Agendar la firma', 'Al aprobar los documentos obligatorios, el sistema intenta enviar la agenda. Si sigue pendiente, revisa franjas futuras y envía la agenda desde el expediente. También puedes programar o reprogramar la cita desde Contratación.'],
            ['Formalizar y completar', 'Carga los documentos requeridos para la firma y usa la acción de completar la cita cuando corresponda. Confirma el resultado en el expediente.'],
        ]],
        'calendar' => ['Reclutamiento', 'Calendario', 'admin.calendar', 'calendar.view', [
            ['Elegir el período', 'Cambia entre Día, Semana y Mes. Navega entre fechas y abre las entradas para consultar sus detalles.'],
            ['Gestionar eventos', 'Crea o edita un evento con el horario correcto. Marca el evento como completado o elimínalo cuando corresponda.', 'calendar.manage'],
        ]],
        'processes' => ['Onboarding', 'Procesos', 'admin.processes', 'onboarding.view', [
            ['Filtrar los expedientes', 'Busca a la persona o filtra por etapa para ubicar pendientes de verificación, entrevista o contratación.'],
            ['Dar seguimiento', 'Usa el estado y las acciones que muestra la pantalla. Si el expediente requiere una decisión, consulta el historial y coordina con la persona autorizada.'],
        ]],
        'validations' => ['Onboarding', 'Validaciones', 'admin.validations', 'documents.verify', [
            ['Encontrar la sesión', 'Busca por nombre, correo o código y filtra por estado de Didit. Revisa fecha y verificación de mayoría de edad.'],
            ['Revisar evidencia', 'Abre Ver datos y contrasta el resultado con la información disponible. Usa Actualizar cuando necesites consultar de nuevo el estado de Didit.'],
            ['Gestionar una inconsistencia', 'Si se habilita en el expediente, solicita una nueva verificación. Comparte con la persona el canal de soporte, sin reenviar su evidencia biométrica.', 'candidates.view'],
        ]],
        'documents' => ['Onboarding', 'Documentos', 'admin.documents.index', 'documents.view', [
            ['Localizar los archivos', 'Abre la ficha documental de la persona y revisa el tipo y el estado de cada archivo.'],
            ['Consultar el contenido', 'Usa vista previa o descarga solo cuando corresponda a la revisión del expediente.'],
            ['Registrar la revisión', 'Actualiza el estado del documento. Si requiere corrección, registra la observación y verifica el resultado en la ficha.', 'documents.manage'],
        ]],
        'active-models' => ['Personas', 'Modelos', 'admin.candidates', 'candidates.view', [
            ['Abrir el listado filtrado', 'Selecciona Modelos en el menú y abre el perfil de la persona que necesitas consultar.'],
            ['Revisar el perfil operativo', 'Consulta las secciones visibles de acceso, inducción, asignaciones y cuentas.'],
            ['Asignar un monitor', 'Si está disponible la acción, selecciona el monitor y confirma la asignación. Verifica el vínculo en el perfil.', 'models.assign_monitor'],
            ['Cerrar la asignación anterior', 'Cuando corresponda, finaliza el vínculo vigente antes de gestionar un cambio de acompañamiento.', 'models.change_monitor'],
        ], ['type' => 'MODEL']],
        'staff-monitors' => ['Personas', 'Monitores', 'admin.candidates', 'candidates.view', [
            ['Filtrar por monitor', 'Abre Monitores en el menú para consultar candidatos de este tipo. Revisa el estado y abre su perfil.'],
            ['Consultar el acceso y la inducción', 'Comprueba la etapa y el estado del perfil antes de coordinar el acceso operativo o un seguimiento.'],
            ['Registrar inducción', 'En el perfil operativo, registra o actualiza la formación si el permiso y la etapa habilitan esa sección.', 'onboarding.manage'],
        ], ['type' => 'MONITOR']],
        'team-planning' => ['Personas', 'Wishlist y estrategias', 'admin.model-planning', ['models.view_all', 'models.view_assigned'], [
            ['Consultar las wishlists', 'Abre la lista del equipo y verifica a qué modelo pertenece cada deseo antes de intervenir.'],
            ['Abrir el detalle', 'Usa Ver detalle y crear estrategia para revisar deseos, referencias y estrategias asociadas.'],
            ['Gestionar una estrategia', 'Completa el formulario de estrategia con la acción, objetivo y prioridad. Guarda y confirma el registro. Edita o elimina desde su tarjeta cuando corresponda.', 'planning.manage_team'],
        ]],
        'operations' => ['Operación', 'Operación diaria', 'admin.operations.index', 'shifts.view_team', [
            ['Revisar el día de trabajo', 'Consulta los turnos, incidencias, solicitudes y pendientes que aparecen dentro de tu alcance.'],
            ['Organizar turnos', 'Define la persona, sala y horario. Guarda el turno y verifica que figure en el listado.', 'shifts.manage_team'],
            ['Registrar asistencia', 'Desde el turno, actualiza la asistencia y comprueba el estado guardado.', 'attendance.manage'],
            ['Completar el checklist', 'Revisa los pendientes de la sala y registra el cumplimiento del checklist.', 'room_checklists.manage'],
            ['Gestionar incidencias y solicitudes', 'Registra los hechos o una nota operativa. Actualiza la incidencia o la solicitud con el resultado de la gestión.', 'incidents.manage'],
        ]],
        'work-sessions' => ['Operación', 'Sesiones del estudio', 'admin.work-sessions', 'dashboard.view', [
            ['Abrir el registro', 'Consulta las sesiones visibles para tu cuenta. Distingue tiempo conectado de tiempo de transmisión y revisa el estado de OBS.'],
            ['Confirmar el cierre', 'Revisa el estado y las horas del registro después de que Agent cierre la sesión. El historial de una estación y la sesión del usuario son datos distintos.'],
        ]],
        'productivity' => ['Operación', 'Productividad', 'admin.productivity.index', 'reports.operational.view', [
            ['Consultar indicadores', 'Revisa los resúmenes de sesiones, transmisiones y métricas, y contrasta el dato con su origen.'],
            ['Registrar una sesión', 'Crea el registro operativo de la sesión y ciérralo al finalizar el trabajo.', 'sessions.manage'],
            ['Registrar una transmisión', 'Asocia la transmisión a la sesión y registra su cierre.', 'streams.manage'],
            ['Registrar métricas', 'Asocia la métrica a la persona y plataforma que corresponden. Revisa unidades y origen antes de guardar.', 'productivity.manage'],
            ['Validar la métrica', 'Contrasta el dato con su fuente antes de validar si tu rol y el alcance de la persona lo permiten.', ['productivity.manage', 'productivity.validate_assigned']],
        ]],
        'rooms' => ['Operación', 'Rooms del estudio', 'admin.rooms.index', 'rooms.view', [
            ['Consultar disponibilidad', 'Revisa sede, estado de las salas y reservas existentes. Abre el detalle de la sala antes de agendar.'],
            ['Configurar una sala', 'Crea o edita la sala, asigna su sede y actualiza su estado operativo.', ['rooms.manage', 'rooms.manage_team']],
            ['Gestionar una reserva', 'Selecciona sala, persona y horario. Guarda y actualiza el estado cuando corresponda al uso real.', ['rooms.manage', 'rooms.manage_team']],
        ]],
        'platforms' => ['Operación', 'Plataformas', 'admin.platforms.index', 'platforms.view', [
            ['Consultar las cuentas', 'Revisa plataforma, estado, titularidad y modo de conexión. Distingue registros reales de manuales o simulados.'],
            ['Registrar la plataforma', 'Usa el formulario de plataforma y guarda el registro antes de crear sus cuentas.', 'platforms.manage'],
            ['Registrar y conectar una cuenta', 'En Registrar cuenta Studio, elige plataforma y cuenta autorizada. Para Chaturbate, usa Conectar Chaturbate y revisa la sincronización desde la cuenta registrada.', 'studio_accounts.manage'],
            ['Validar y vincular', 'Elige la cuenta y la modelo activa. Usa la búsqueda preventiva con Didit y la validación de nickname. Revisa el resultado y confirma el identificador con la plataforma antes de vincular.', 'studio_accounts.manage'],
            ['Simular una sincronización', 'Elige la cuenta y usa la simulación. Interpreta el resultado como datos de demostración.', 'platform_simulations.run'],
            ['Importar datos manuales', 'Selecciona la cuenta, registra los datos en el formulario de importación y revisa el resultado.', 'platform_imports.manage'],
        ]],
        'chaturbate' => ['Operación', 'Conectar y sincronizar Chaturbate', 'admin.platforms.index', ['all' => ['platforms.view', 'studio_accounts.manage']], [
            ['Identificar la cuenta pendiente', 'En Cuentas registradas, ubica la cuenta de Chaturbate. Comprueba qué cuenta indica la sección Conectar Chaturbate antes de introducir sus datos.'],
            ['Completar la conexión', 'En Conectar Chaturbate, usa la URL de Stats API o los campos de usuario y token. Agrega la URL de Events API si corresponde y guarda.'],
            ['Comprobar la sincronización', 'Revisa el estado y la última sincronización. En una cuenta en vivo, Sincronizar Chaturbate vuelve a consultar el servicio. Si falla, comprueba el error y la autorización de la cuenta.'],
        ], ['integration' => 'chaturbate']],
        'administration' => ['Operación', 'Administración del estudio', 'admin.administration.index', 'branches.manage', [
            ['Registrar sedes', 'Consulta las sedes existentes. Completa el formulario de sede y guarda para habilitar su uso en otros formularios.'],
            ['Gestionar inventario', 'Registra el activo con su ubicación y comprueba que figure en el inventario.', 'inventory.manage'],
            ['Gestionar mantenimiento', 'Selecciona el recurso y registra la intervención en el formulario de mantenimiento.', 'maintenance.manage'],
            ['Registrar revisiones', 'Completa la evaluación operativa de la persona y guarda la revisión.', 'performance_reviews.manage'],
        ]],
        'station-manager' => ['Integraciones', 'Station Manager', 'admin.station-manager.index', 'stations.assign', [
            ['Revisar estaciones', 'Comprueba sede, disponibilidad y asignación actual de la estación.'],
            ['Generar el código de enrolamiento', 'Emite el código desde Station Manager e introdúcelo en el Agent de la estación autorizada. Revisa el historial de códigos.'],
            ['Asignar o liberar la estación', 'Selecciona la estación y el destino que ofrece el formulario. Confirma la asignación o finaliza el vínculo anterior desde el panel.'],
        ]],
        'agent' => ['Integraciones', 'VELVET Agent', 'admin.agent.index', 'stations.enroll', [
            ['Consultar el estado de la integración', 'La pantalla VELVET Agent indica que el panel técnico está en construcción.'],
            ['Localizar la gestión operativa', 'Usa Station Manager para los códigos y asignaciones si tienes acceso.', 'stations.assign'],
            ['Comprobar las sesiones', 'Revisa Sesiones para verificar el registro de conexión, transmisión y OBS. Consulta las referencias técnicas para el contrato de integración.', 'dashboard.view'],
        ]],
        'workflows' => ['Configuración', 'Workflows', 'admin.workflows', 'settings.manage', [
            ['Abrir el mapa de procesos', 'Consulta los flujos de la plataforma y ubica el módulo responsable de cada etapa.'],
            ['Seguir una transición', 'Usa el mapa como referencia y abre el expediente o módulo correspondiente para ejecutar la acción y confirmar su resultado.'],
        ]],
        'users' => ['Configuración', 'Usuarios', 'admin.users.index', 'users.view', [
            ['Consultar usuarios', 'Busca por nombre o correo y comprueba el rol y estado de la cuenta.'],
            ['Crear usuario', 'Completa el formulario, elige un rol dentro de tu alcance y confirma tu contraseña si el sistema lo solicita.', 'users.create'],
            ['Editar usuario', 'Abre la ficha de edición, actualiza los datos permitidos y confirma. Revisa después los accesos efectivos.', 'users.update'],
            ['Deshabilitar el acceso', 'Selecciona la cuenta correcta y confirma la acción según el alcance de tu rol.', 'users.disable'],
        ]],
        'roles' => ['Configuración', 'Roles y permisos', 'admin.access.index', 'roles.manage', [
            ['Revisar la jerarquía', 'Consulta la estructura de roles, sus permisos y los usuarios asociados.'],
            ['Configurar un rol', 'Crea o edita un rol dentro de tu alcance. Selecciona los permisos necesarios y revisa los cambios antes de confirmar la contraseña.'],
            ['Verificar el acceso', 'Comprueba que el rol aparezca en la lista y que sus usuarios vean únicamente los módulos autorizados.'],
            ['Retirar un rol personalizado', 'Usa Eliminar cuando la acción esté disponible para ese rol. Confirma la operación y revisa la jerarquía resultante.'],
        ]],
        'training-data' => ['Configuración', 'Datos de prueba', 'admin.training-data.index', '@super_admin', [
            ['Consultar el conjunto de prueba', 'Revisa los contadores de salas, modelos y reservas de demostración.'],
            ['Restaurar los datos de prueba', 'Usa la acción de restauración cuando corresponda y verifica los contadores actualizados.'],
        ]],
        'guided-tours' => ['Configuración', 'Visitas guiadas', 'admin.guided-tours.index', '@super_admin', [
            ['Consultar el estado global', 'Abre Visitas guiadas y revisa si las guías están activas.'],
            ['Cambiar la disponibilidad', 'Usa el interruptor y confirma el mensaje. La configuración se aplica globalmente y conserva el progreso guardado.'],
        ]],
        'ai-settings' => ['Configuración', 'Asistente IA', 'admin.ai.index', 'ai.manage', [
            ['Revisar la configuración', 'Comprueba el estado de la clave en el servidor y los modelos disponibles.'],
            ['Ajustar el servicio', 'Configura habilitación, modelo principal, alternativas, selección automática, máximo de tokens y temperatura. Guarda los cambios.'],
            ['Comprobar el lanzador', 'Abre Velvet AI y envía una pregunta de prueba desde una cuenta habilitada.', 'ai.chat'],
        ]],
        'documentation' => ['Configuración', 'Documentación', 'admin.documentation.index', null, [
            ['Encontrar un flujo', 'En Manual funcional, busca por módulo o filtra por área. Las guías reflejan tus permisos actuales.'],
            ['Revisar pasos y demostración', 'Abre el módulo del manual, sigue sus pasos o inicia la demostración. Usa Abrir módulo para continuar en el dashboard.'],
            ['Consultar referencias y anexos', 'Abre el manual técnico o descarga un adjunto publicado cuando necesites más detalle.'],
            ['Publicar y mantener los anexos', 'En Crear documentación, completa título y categoría. Añade la nota o un adjunto, guarda y comprueba que aparezca en Documentación interna. Usa la acción de eliminar para retirar un anexo.', '@super_admin'],
        ]],
        'notifications' => ['Uso del dashboard', 'Notificaciones', 'admin.notifications.history', null, [
            ['Consultar avisos', 'Abre la campana para revisar los avisos recientes o entra al historial para ver más registros.'],
            ['Abrir el expediente relacionado', 'Selecciona el aviso y comprueba si tienes acceso a su módulo. Usa Marcar como leídas cuando termines la revisión.'],
        ]],
        'profile' => ['Uso del dashboard', 'Perfil y seguridad de la cuenta', 'profile.edit', null, [
            ['Abrir el perfil', 'Desde el menú de usuario, elige Visitar perfil. Revisa la información de la cuenta.'],
            ['Actualizar datos o contraseña', 'Guarda los cambios en el formulario correspondiente. Si actualizas el correo, atiende la solicitud de verificación que aparezca.'],
        ]],
        'dashboard-controls' => ['Uso del dashboard', 'Menú, tema y ayuda', 'admin.documentation.index', null, [
            ['Ubicar tus módulos', 'Usa el menú lateral. En una pantalla estrecha, ábrelo desde el icono del encabezado.'],
            ['Ajustar la apariencia', 'Usa el botón de tema del dashboard para alternar entre claro y oscuro. Los enlaces públicos de selección conservan su diseño oscuro.'],
            ['Repetir el recorrido de un módulo', 'Abre el lanzador de ayuda y elige la visita guiada cuando esté habilitada para tu cuenta y para esa pantalla.'],
        ]],
        'ai-chat' => ['Uso del dashboard', 'Velvet AI', 'admin.documentation.index', 'ai.chat', [
            ['Abrir el asistente', 'Cuando el servicio esté habilitado, abre los accesos rápidos y selecciona Velvet AI.'],
            ['Consultar', 'Elige un atajo o escribe tu pregunta sobre módulos operativos. Espera el resultado antes de enviar la siguiente consulta.'],
            ['Revisar la respuesta', 'Usa los enlaces a módulos cuando estén disponibles. Puedes copiar una respuesta, iniciar una conversación nueva o expandir el panel.'],
        ]],
        'monitor-home' => ['Portal de monitor', 'Dashboard de monitor', 'monitor.dashboard', 'rooms.view_team', [
            ['Revisar la actividad', 'Consulta el resumen de tu equipo, alertas e indicadores disponibles en el portal.'],
            ['Ir al registro de trabajo', 'Abre Sesiones o Rooms desde el menú para contrastar el resumen con los registros de tu equipo.'],
        ]],
        'monitor-models' => ['Portal de monitor', 'Modelos asignadas', 'monitor.models.index', 'models.view_assigned', [
            ['Elegir una modelo asignada', 'Abre Modelos, localiza a la persona y consulta su ficha dentro de tu asignación.'],
            ['Revisar actividad y seguimiento', 'Consulta los indicadores y registros visibles del perfil.'],
            ['Vincular una cuenta autorizada', 'Cuando se habilite la sección, revisa la cuenta Studio, ejecuta la búsqueda preventiva y valida el nickname antes de vincular. Revisa el resultado en la ficha.', 'studio_accounts.assign_assigned'],
        ]],
        'monitor-planning' => ['Portal de monitor', 'Wishlist y estrategias asignadas', 'monitor.planning', 'models.view_assigned', [
            ['Consultar los deseos del equipo', 'Selecciona una modelo asignada y abre el detalle de su wishlist.'],
            ['Acordar una estrategia', 'Crea una estrategia de acompañamiento, registra sus datos y verifica que quede asociada a la modelo correcta.', 'planning.manage_team'],
        ]],
        'monitor-rooms' => ['Portal de monitor', 'Rooms del equipo', 'monitor.rooms', 'rooms.view_team', [
            ['Consultar las salas', 'Revisa disponibilidad y reservas visibles de tu equipo antes de programar un uso.'],
            ['Programar por lotes', 'Selecciona sala, persona y agrega una o varias franjas. Las horas ocupadas de la misma room se bloquean y el lote se guarda completo o no se guarda.', 'rooms.manage_reservations'],
            ['Confirmar la agenda', 'Después de guardar, comprueba que cada franja aparezca en la agenda de la modelo. Cada reserva genera bitácora, notificación privada y un correo con evento .ics.', 'rooms.manage_reservations'],
            ['Actualizar el uso', 'Desde la reserva, registra la acción de uso disponible según su estado.', 'rooms.manage_usage'],
        ]],
        'monitor-sessions' => ['Portal de monitor', 'Sesiones del equipo asignado', 'monitor.work-sessions', ['all' => ['rooms.view_team', 'models.view_metrics']], [
            ['Consultar las sesiones', 'Revisa los registros del equipo asignado. Comprueba la persona, la estación y el horario.'],
            ['Interpretar los indicadores', 'Distingue conexión, transmisión y OBS. Contrasta un resultado con el registro de la persona antes de hacer seguimiento.'],
        ]],
        'model-home' => ['Portal de modelo', 'Dashboard de modelo', 'model.dashboard', 'rooms.view_own', [
            ['Consultar tu actividad', 'Revisa sesiones Agent, tiempo conectado, tokens y turnos. Los indicadores de inducción pueden corresponder al modo de pruebas.'],
            ['Abrir tus accesos', 'Entra a Mi tiempo, Bitácora, Agenda, Solicitudes, Plataformas, Mis rooms, Mi wishlist, Mi perfil o Sesiones desde el sidebar. Los enlaces aparecen únicamente cuando tu rol tiene el permiso correspondiente.'],
            ['Entender el control por rol', 'Si un permiso se retira, el módulo desaparece del menú y la ruta queda protegida en el servidor. Los cambios de navegación frontend no requieren volver a ejecutar los seeders.'],
        ]],
        'model-profile' => ['Portal de modelo', 'Mi perfil operativo', 'model.profile', ['all' => ['rooms.view_own', 'profile.view_own']], [
            ['Revisar tu perfil', 'Consulta el estado de tu perfil, las asignaciones y la información operativa visible.'],
            ['Consultar documentos propios', 'Abre la vista previa o descarga tus archivos cuando la acción aparezca en tu perfil.', 'documents.view_own'],
        ]],
        'model-rooms' => ['Portal de modelo', 'Mis rooms', 'model.rooms', 'rooms.view_own', [
            ['Consultar disponibilidad', 'Revisa las salas habilitadas y tus reservas vigentes.'],
            ['Reservar varias franjas', 'Selecciona una sala y agrega varias fechas u horarios. El sistema bloquea automáticamente las franjas ya ocupadas y no permite cruces para la misma modelo.', 'rooms.reserve_own'],
            ['Recibir la confirmación', 'Cada reserva aparece en tu agenda y bitácora, activa una notificación en el dashboard y llega por correo con un archivo .ics para tu calendario.', 'rooms.reserve_own'],
            ['Registrar uso', 'Desde tu reserva, usa la acción correspondiente para iniciar o cerrar el uso de la sala.', 'rooms.use_own'],
        ]],
        'model-planning' => ['Portal de modelo', 'Mi wishlist', 'model.planning', ['all' => ['rooms.view_own', 'planning.view_own']], [
            ['Consultar tu lista', 'Abre Mi wishlist y revisa tus deseos y las estrategias visibles.'],
            ['Agregar o actualizar un deseo', 'Completa título, prioridad y los detalles que correspondan. Puedes agregar una referencia o imagen, guardar y editar desde la tarjeta.', 'planning.manage_own'],
            ['Compartir la wishlist', 'Genera el enlace desde el módulo y comprueba su vista antes de enviarlo a quien deba consultarlo.'],
        ]],
        'model-sessions' => ['Portal de modelo', 'Mis sesiones', 'model.work-sessions', ['all' => ['rooms.view_own', 'metrics.view_own']], [
            ['Consultar tu historial', 'Revisa las sesiones y sus horas de inicio y cierre desde tu portal.'],
            ['Comprobar la actividad de Agent', 'Compara el tiempo de conexión con el de transmisión y revisa el estado asociado a OBS.'],
        ]],
        'model-time' => ['Portal de modelo', 'Mi tiempo', 'model.time', ['all' => ['rooms.view_own', 'metrics.view_own']], [
            ['Leer tus indicadores', 'Compara sesiones activas, tiempo conectado y tiempo transmitiendo. Son datos propios calculados a partir de Agent.'],
            ['Revisar una sesión', 'Abre el historial para confirmar inicio, cierre, estación y estado de OBS. Si falta un cierre, espera el siguiente heartbeat o solicita soporte.'],
        ]],
        'model-activity' => ['Portal de modelo', 'Mi bitácora', 'model.activity', ['all' => ['rooms.view_own', 'profile.view_own']], [
            ['Consultar el historial', 'Revisa en orden cronológico los cambios y eventos asociados a tu perfil.'],
            ['Distinguir una solicitud', 'Los eventos de solicitudes muestran su tipo, detalle y respuesta para que puedas hacer seguimiento sin perder contexto. Los nombres y estados recibidos en inglés se presentan traducidos al español.',],
            ['Confirmar una room', 'Cuando una room sea programada, aparecerá el evento Room programada con la fecha, hora y room correspondiente.',],
        ]],
        'model-agenda' => ['Portal de modelo', 'Mi agenda', 'model.agenda', ['all' => ['rooms.view_own', 'profile.view_own']], [
            ['Consultar tus actividades', 'Revisa turnos, reservas de rooms y citas de contratación ordenados por hora local de Bogotá.'],
            ['Preparar una actividad', 'Comprueba estado, sala y notas antes de asistir. Las reservas nuevas llegan en tiempo real y los cambios se realizan desde el módulo que corresponde.'],
        ]],
        'model-requests' => ['Portal de modelo', 'Mis solicitudes', 'model.requests', ['all' => ['rooms.view_own', 'requests.view_own']], [
            ['Crear una solicitud', 'Elige el tipo, describe la necesidad y agrega un rango de fecha solo cuando sea relevante. No incluyas contraseñas ni datos sensibles.'],
            ['Consultar la respuesta', 'Revisa estado, detalle y respuesta del equipo desde el historial. La solicitud no reemplaza un canal urgente de seguridad.'],
        ]],
        'model-platform-requests' => ['Portal de modelo', 'Solicitar plataformas', 'model.platform-requests', ['all' => ['rooms.view_own', 'requests.view_own']], [
            ['Proponer un nickname', 'Escribe un nickname válido y selecciona una plataforma disponible. La solicitud no crea cuentas ni activa conexiones externas.'],
            ['Esperar la validación', 'El equipo comprueba disponibilidad, identidad y autorización desde Plataformas antes de vincular la cuenta.'],
            ['Consultar el resultado', 'Revisa el historial para conocer si la solicitud está pendiente, fue aprobada o requiere una nueva propuesta.'],
        ]],
        'candidate-journey' => ['Flujos de selección', 'Aplicación y enlaces de selección', 'apply', ['@candidate', 'leads.view', 'candidates.view'], [
            ['Enviar la aplicación', 'Elige el perfil, diligencia datos personales, experiencia y disponibilidad. Revisa el resumen, acepta el tratamiento de datos y envía. Guarda el código de confirmación.'],
            ['Completar la precalificación', 'Abre el enlace personal del correo. Completa los requisitos y, cuando el formulario lo solicite, finaliza Didit y vuelve para enviar el formulario.'],
            ['Reservar la entrevista', 'Abre la invitación, elige una franja disponible y verifica en la confirmación la fecha y hora elegidas.'],
            ['Cargar documentos de contratación', 'Abre el enlace personal, guarda cada archivo para habilitar el siguiente y finaliza cuando estén completos los obligatorios. Revisa la confirmación y atiende las correcciones solicitadas.'],
            ['Confirmar la cita de firma', 'Cuando recibas la agenda de contratación, elige una franja desde el correo y verifica los datos en la pantalla de confirmación.'],
        ]],
    ],
];
