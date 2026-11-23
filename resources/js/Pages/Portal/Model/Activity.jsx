import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { FiActivity, FiCheckCircle, FiFileText } from 'react-icons/fi';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import { subscribeToRealtime } from '@/lib/ably';

const date = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const activityLabels = {
    status_changed: 'Estado cambiado',
    interview_invited: 'Invitación de entrevista',
    automation_interview_invited: 'Invitación de entrevista',
    prequalification_completed: 'Precalificación completada',
    identity_verification_updated: 'Verificación de identidad actualizada',
    identity_verification_started: 'Verificación de identidad iniciada',
    interview_status_changed: 'Estado de entrevista actualizado',
    interview_scheduled: 'Entrevista agendada',
    candidate_updated: 'Datos del candidato actualizados',
    candidate_documents_uploaded: 'Documentos recibidos',
    contract_documents_uploaded: 'Documentos de contratación recibidos',
    contracting_scheduled: 'Contratación programada',
    contracting_completed: 'Contratación finalizada',
    contracting_started: 'Contratación iniciada',
    contracting_rescheduled: 'Contratación reprogramada',
    room_reservation_scheduled: 'Room programada',
    operational_training_created: 'Inducción operativa creada',
    operational_training_updated: 'Inducción operativa actualizada',
    access_created: 'Acceso creado',
    contracting_invited: 'Invitación de contratación',
    prequalification_sent: 'Precalificación enviada',
    identity_verification_retry_requested: 'Nueva verificación de identidad solicitada',
    platform_account_linked: 'Cuenta de plataforma vinculada',
    platform_account_unlinked: 'Cuenta de plataforma desvinculada',
    monitor_unassigned: 'Monitor desvinculado',
    data_updated: 'Datos actualizados',
    first_login: 'Primer inicio de sesión',
    created: 'Perfil creado',
    discarded: 'Perfil descartado',
    conversion: 'Conversión a candidato',
    reservation: 'Reserva registrada',
    identity: 'Verificación de identidad',
    interview_notes: 'Notas de entrevista',
    interview_notes_uploaded: 'Notas de entrevista recibidas',
    interview_rescheduled: 'Entrevista reprogramada',
    automation_paused: 'Automatización pausada',
    automation_prequalification_sent: 'Precalificación enviada automáticamente',
    ALL: 'Todos',
    DEADLINE: 'Fecha límite',
    profile_checked: 'Perfil revisado',
    PLATFORM_REQUEST: 'Solicitud de plataforma',
    SUPPORT: 'Soporte',
    ROOM: 'Room o equipo',
    SCHEDULE: 'Horario',
    PROFILE: 'Perfil',
};

const label = (value = '') => activityLabels[value] || String(value).toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const translateDescription = (value = '') => String(value)
    .replaceAll('Operational Training Updated', 'Inducción operativa actualizada')
    .replaceAll('Access Created', 'Acceso creado')
    .replaceAll('Contracting Invited', 'Invitación de contratación')
    .replaceAll('Identity Verification Updated', 'Verificación de identidad actualizada')
    .replaceAll('Identity Verification Started', 'Verificación de identidad iniciada')
    .replaceAll('Prequalification Completed', 'Precalificación completada')
    .replaceAll('Status Changed', 'Estado cambiado')
    .replace(/\bTRAINING\b/g, 'INDUCCIÓN')
    .replace(/\bCOMPLETED\b/g, 'COMPLETADA')
    .replace(/\bPENDING\b/g, 'PENDIENTE')
    .replace(/\bACTIVE\b/g, 'ACTIVO')
    .replace(/\bINACTIVE\b/g, 'INACTIVO')
    .replace(/\bSCHEDULED\b/g, 'PROGRAMADA')
    .replace(/\bCANCELLED\b/g, 'CANCELADA');

export default function Activity({ candidate = {}, events = [] }) {
    useEffect(() => subscribeToRealtime((_data, event) => {
        if (!['operation.request.created', 'operation.request.updated', 'room.reservation.created', 'room.reservation.updated'].includes(event)) return;
        router.reload({ only: ['events'], preserveScroll: true, preserveState: true });
    }), []);

    return <>
        <Head title="Mi bitácora" />
        <Layout><div className="admin-page-content" data-portal-shell><header className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Portal de modelo</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Mi bitácora</h1><p className="mt-2 max-w-3xl text-sm text-[#969baa]">Una vista cronológica de los cambios, solicitudes y respuestas asociados a tu perfil.</p></div></header>
            <section data-tour="model-activity-timeline" className="model-module-card mt-8">
                <div className="model-module-card__header"><div><p className="model-module-kicker">Registro personal</p><h2>{candidate.name || 'Mi actividad'}</h2><p>{candidate.code ? `${candidate.code} · ` : ''}Los eventos son informativos y no reemplazan los canales de soporte.</p></div><FiActivity aria-hidden="true" className="model-module-icon" /></div>
                {events.length ? <ol className="model-activity-list">{events.map((event) => <li key={event.id}><span className="model-activity-list__icon" aria-hidden="true">{event.kind === 'request' ? <FiFileText /> : <FiCheckCircle />}</span><div><div className="model-activity-list__meta"><strong>{event.kind === 'request' ? `Solicitud · ${label(event.type)}` : label(event.type)}</strong><time dateTime={event.createdAt}>{date(event.createdAt)}</time></div><p>{translateDescription(event.description || 'Sin detalle adicional.')}</p>{event.response && <p className="model-module-callout">Respuesta: {translateDescription(event.response)}</p>}{event.actor && <small>{event.actor}</small>}</div></li>)}</ol> : <div className="model-module-empty"><FiActivity aria-hidden="true" /><p>Aún no hay actividad para mostrar.</p><span>Las actualizaciones del perfil y tus solicitudes aparecerán en esta bitácora.</span></div>}
            </section>
        </div></Layout>
    </>;
}
