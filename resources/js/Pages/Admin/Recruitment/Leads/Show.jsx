import { Head, Link, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { FaWhatsapp } from 'react-icons/fa6';
import { FiArrowLeft, FiCheckCircle, FiClock, FiEdit3, FiMail, FiTrash2, FiUser, FiX } from 'react-icons/fi';
import { formatFriendlyDate, formatFriendlyDateTime } from '../../../../lib/date';
import { candidateTypeLabels } from '../../../../lib/recruitmentLabels';
import AdminApplicationEditModal from '../../../../Components/AdminApplicationEditModal';
import InterviewNotesUpload from '../../../../Components/InterviewNotesUpload';
import ContractingDocumentsUpload from '../../../../Components/ContractingDocumentsUpload';
import Layout from '../Layout';
import { subscribeToRealtime } from '../../../../lib/ably';
import { useRealtimeFallback } from '../../../../lib/useRealtimeFallback';

const sexLabels = { WOMAN: 'Mujer', MAN: 'Hombre', TRANS_WOMAN: 'Mujer trans', TRANS_MAN: 'Hombre trans', NON_BINARY: 'No binario', GENDER_FLUID: 'Género fluido', AGENDER: 'Agénero', SELF_DESCRIBE: 'Otro / Prefiero autodescribir', PREFER_NOT_TO_SAY: 'Prefiero no decir' };

function Detail({ label, value }) {
    return <div><dt className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">{label}</dt><dd className="mt-2 text-sm text-[#f2edf5]">{value || 'No indicado'}</dd></div>;
}

export default function Show({ lead }) {
    const form = useForm();
    const statusForm = useForm({ status: lead.pipeline_status });
    const discardForm = useForm({ reason: '' });
    const [editing, setEditing] = useState(false);
    const [discarding, setDiscarding] = useState(false);
    const [pipelineStatus, setPipelineStatus] = useState(lead.pipeline_status);
    const [activities, setActivities] = useState(lead.process_activities || lead.activities || []);
    const [realtime, setRealtime] = useState(false);
    const [realtimeUnavailable, setRealtimeUnavailable] = useState(!import.meta.env.VITE_ABLY_ENABLED);
    const refresh = useCallback(() => router.reload({ only: ['lead', 'realtime'], preserveScroll: true, preserveState: false }), []);
    useEffect(() => {
        const unsubscribe = subscribeToRealtime((event, eventName) => {
            if (Number(event.lead_id) !== Number(lead.id)) return;
            if (event.status) { setPipelineStatus(event.status); statusForm.setData('status', event.status); }
            const incomingActivities = event.activities?.length ? event.activities : (event.activity ? [event.activity] : []);
            incomingActivities.forEach((item) => {
                const activity = { ...item, created_at: item.created_at || event.updated_at || new Date().toISOString(), id: `${eventName || 'realtime'}-${item.id}` };
                setActivities((current) => current.some((currentItem) => currentItem.id === activity.id) ? current : [...current, activity]);
            });
            if (['candidate.updated', 'candidate.documents_uploaded', 'candidate.status_changed'].includes(eventName)) refresh();
            setRealtime(true);
        }, null, (available) => setRealtimeUnavailable(!available));
        return unsubscribe;
    }, [lead.id]);
    useRealtimeFallback(refresh, realtimeUnavailable);
    const updateStatus = (event) => {
        const status = event.target.value;
        setPipelineStatus(status);
        statusForm.setData('status', status);
        statusForm.patch(`/admin/leads/${lead.id}/status`, { preserveScroll: true, onSuccess: refresh, onError: () => setPipelineStatus(lead.pipeline_status) });
    };
    const changeStatus = (status) => {
        setPipelineStatus(status);
        statusForm.setData('status', status);
        statusForm.patch(`/admin/leads/${lead.id}/status`, { preserveScroll: true, onSuccess: refresh, onError: () => setPipelineStatus(lead.pipeline_status) });
    };
    const convertAction = () => form.post(`/admin/leads/${lead.id}/convert`);
    const startContractingAction = (candidateId) => form.post(`/admin/candidates/${candidateId}/start-contracting`, { preserveScroll: true });
    const applicationName = [lead.first_name, lead.last_name].filter(Boolean).join(' ') || lead.full_name || 'Sin nombre';
    const applicationWhatsapp = String(lead.phone || '').replace(/\D/g, '');
    const applicationWhatsappHref = applicationWhatsapp ? `https://wa.me/${applicationWhatsapp.startsWith('57') ? applicationWhatsapp : `57${applicationWhatsapp}`}?text=${encodeURIComponent(`Hola ${applicationName}, te escribe el equipo de The Velvet Studio. Recibimos tu solicitud ${lead.code} y queremos contarte los siguientes pasos.`)}` : null;
    const recommendation = (() => {
        const candidateId = lead.candidate?.id;
        if (candidateId && ['NEW', 'CONTACTED'].includes(pipelineStatus)) return {
            title: 'Solicitar precalificación',
            description: 'La persona ya es candidata. Envíale el formulario para completar requisitos e iniciar la validación.',
            label: 'Enviar formulario de precalificación',
            action: () => statusForm.post(`/admin/candidates/${candidateId}/prequalification`, { preserveScroll: true, onSuccess: refresh }),
        };
        if (candidateId && pipelineStatus === 'PREQUALIFIED') return {
            title: 'Agendar entrevista',
            description: 'La precalificación está completa. El siguiente paso recomendado es invitarla a seleccionar un horario.',
            label: 'Enviar horarios y pasar a entrevista',
            action: () => changeStatus('INTERVIEW'),
        };
        if (candidateId && pipelineStatus === 'INTERVIEW') return {
            title: 'Revisar entrevista',
            description: 'Consulta las invitaciones y el resultado de la entrevista antes de avanzar a evaluación.',
            label: 'Revisar entrevistas',
            href: '/admin/interviews',
        };
        if (candidateId && pipelineStatus === 'EVALUATION') return {
            title: 'Admitir candidato',
            description: 'La evaluación de la entrevista está lista. Confirma la admisión para que la persona avance y reciba el correo de felicitación con las instrucciones de contacto.',
            label: 'Marcar como admitido',
            action: () => changeStatus('ADMITTED'),
        };
        if (candidateId && pipelineStatus === 'ADMITTED') return {
            title: 'Iniciar contratación',
            description: 'Contacta a la persona, envía la documentación necesaria y pásala a En espera para programar la contratación física.',
            label: 'Contactar y enviar requisitos',
            action: () => startContractingAction(candidateId),
        };
        if (candidateId && ['ONBOARDING', 'READY_TO_ACTIVATE'].includes(pipelineStatus)) return {
            title: 'Activar acceso al dashboard',
            description: 'El onboarding está listo. Abre el perfil del candidato para crear sus credenciales y finalizar el proceso con el estado Activo.',
            label: 'Abrir perfil y activar acceso',
            href: `/admin/candidates/${candidateId}`,
        };
        if (!candidateId && pipelineStatus === 'NEW') return {
            title: 'Contactar al lead',
            description: 'La solicitud es nueva. Confirma el interés y registra el primer contacto para continuar el proceso.',
            label: 'Escribir por WhatsApp',
            href: applicationWhatsappHref,
            after: () => changeStatus('CONTACTED'),
        };
        if (!candidateId && pipelineStatus === 'CONTACTED') return {
            title: 'Calificar lead',
            description: 'Ya hubo contacto. Marca la solicitud como calificada para habilitar su conversión a candidato.',
            label: 'Marcar como calificada',
            action: () => changeStatus('QUALIFIED'),
        };
        if (!candidateId && pipelineStatus === 'QUALIFIED') return {
            title: 'Convertir en candidato',
            description: 'La solicitud está calificada y lista para entrar al proceso de selección.',
            label: 'Convertir en candidato',
            action: convertAction,
        };
        return null;
    })();
    const convert = () => form.post(`/admin/leads/${lead.id}/convert`);
    const fullName = [lead.first_name, lead.last_name].filter(Boolean).join(' ') || lead.full_name || 'Sin nombre';
    const birthDate = lead.birth_date ? formatFriendlyDate(lead.birth_date) : null;
    const whatsappNumber = String(lead.phone || '').replace(/\D/g, '');
    const whatsappHref = whatsappNumber ? `https://wa.me/${whatsappNumber.startsWith('57') ? whatsappNumber : `57${whatsappNumber}`}?text=${encodeURIComponent(`Hola ${fullName}, te escribe el equipo de The Velvet Studio. Recibimos tu solicitud ${lead.code} y queremos contarte los siguientes pasos.`)}` : null;
    const emailHref = lead.email ? `mailto:${lead.email}?subject=${encodeURIComponent('Seguimiento de tu aplicación  ·  The Velvet Studio')}&body=${encodeURIComponent(`Hola ${fullName},\n\nTe escribe el equipo de The Velvet Studio sobre tu solicitud ${lead.code}.\n\nQuedamos atentos para continuar tu proceso.`)}` : null;
    const discard = (event) => { event.preventDefault(); discardForm.post(`/admin/leads/${lead.id}/discard`, { preserveScroll: true, onSuccess: () => setDiscarding(false) }); };

    return <>
        <Head title={`Aplicación  ·  ${lead.code}`} />
        <Layout>
            <div>
                <Link href="/admin/leads" className="inline-flex items-center gap-2 text-sm text-[#d56bea] transition hover:text-white"><FiArrowLeft size={15} /> Todas las aplicaciones</Link>
                <div className="mt-7 flex flex-col justify-between gap-5 border-b border-[#252936] pb-7 md:flex-row md:items-end">
                    <div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Detalle de aplicación  ·  {lead.code}</p><h1 className="mt-3 font-editorial text-4xl text-white">{fullName}</h1><p className="mt-2 text-sm text-[#969baa]">{candidateTypeLabels[lead.candidate_type] || lead.candidate_type}</p></div>
                    <div className="flex flex-wrap items-center gap-2"><span className="w-fit rounded-full border border-[#9142a7]/50 bg-[#3d164d] px-3 py-1.5 text-xs text-[#f0c1fa]">{(lead.status_options || []).find((option) => option.value === pipelineStatus)?.label || pipelineStatus}</span><button type="button" onClick={() => setEditing((value) => !value)} className="inline-flex items-center gap-2 rounded-lg border border-[#49334f] px-3 py-2 text-xs text-[#e6c4ed] transition hover:border-[#a84bc2] hover:text-white"><FiEdit3 size={14} />{editing ? 'Cerrar edición' : 'Editar datos'}</button>{!lead.candidate && lead.status !== 'DISCARDED' && <button type="button" onClick={() => setDiscarding(true)} className="inline-flex items-center gap-2 rounded-lg border border-[#673344] px-3 py-2 text-xs text-[#ffb1bd] transition hover:border-[#bb4c66] hover:bg-[#321622]"><FiTrash2 size={14} />Descartar</button>}</div>
                </div>
                <AdminApplicationEditModal open={editing} onClose={() => setEditing(false)} endpoint={`/admin/leads/${lead.id}`} person={lead} candidateType={lead.candidate_type} title="Editar información de la aplicación" />
                <div data-tour="lead-flow" className="mt-8 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 shadow-[0_18px_55px_rgba(0,0,0,.16)] sm:p-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Flujo del lead</p><p className="mt-2 text-sm text-[#c3c6d1]">Gestiona la siguiente etapa desde esta tarjeta. Los cambios aparecen en tiempo real para todo el equipo.</p></div><div className="flex items-center gap-3"><span className={`inline-flex items-center gap-2 text-[10px] ${realtime ? 'text-[#65e6ad]' : 'text-[#777d8f]'}`} title={realtime ? 'Conectado a Ably' : 'Guardado automático en el servidor'}><span className={`h-2 w-2 rounded-full ${realtime ? 'bg-[#65e6ad] shadow-[0_0_10px_#65e6ad]' : 'bg-[#777d8f]'}`} />{realtime ? 'Tiempo real activo' : 'Guardado automático'}</span></div></div>
                </div>
                {recommendation && <div className="mt-4 flex flex-col justify-between gap-4 rounded-xl border border-[#713080] bg-gradient-to-r from-[#24112d] to-[#15121d] p-5 shadow-[0_12px_35px_rgba(116,31,145,.12)] sm:flex-row sm:items-center sm:p-6"><div><div className="flex items-center gap-2"><span className="grid h-7 w-7 place-items-center rounded-full bg-[#713080] text-[#f5c8ff]">→</span><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#e5a1f2]">Siguiente paso recomendado</p></div><h2 className="mt-3 text-base font-medium text-white">{recommendation.title}</h2><p className="mt-1 max-w-2xl text-xs leading-5 text-[#bdb3c5]">{recommendation.description}</p></div>{recommendation.href ? <a href={recommendation.href} onClick={recommendation.after} className="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#a92ad8] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#c13dea]">{recommendation.label} →</a> : <button type="button" onClick={recommendation.action} disabled={statusForm.processing || form.processing} className="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#a92ad8] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#c13dea] disabled:cursor-wait disabled:opacity-60">{recommendation.label} →</button>}</div>}
                <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
                    <section className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-8">
                        <div className="flex items-center gap-3 border-b border-[#292d39] pb-5"><span className="grid h-10 w-10 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiUser size={18} /></span><div><p className="text-sm font-medium text-white">Información de la persona</p><p className="mt-1 text-xs text-[#7f8495]">Datos enviados en el onboarding público.</p></div></div>
                        <dl className="mt-7 grid gap-x-8 gap-y-7 sm:grid-cols-2">
                            <Detail label="Nombre completo" value={fullName} /><Detail label="Sexo" value={sexLabels[lead.sex] || lead.sex} /><Detail label="Email" value={lead.email} /><Detail label="WhatsApp" value={lead.phone} /><Detail label="Ciudad" value={lead.city} /><Detail label="País" value={lead.country} /><Detail label="Fecha de nacimiento" value={birthDate} /><Detail label="Habla inglés" value={lead.speaks_english ? 'Sí' : 'No'} /><Detail label="Nivel de inglés" value={lead.speaks_english ? lead.english_level : 'No aplica'} /><Detail label="Fuente" value={lead.source} /><Detail label="Fecha de solicitud" value={formatFriendlyDateTime(lead.created_at)} /><Detail label="Disponibilidad" value={lead.availability} /><Detail label="Modalidad" value={lead.work_mode} />
                        </dl>
                        {lead.candidate_type === 'MONITOR' && <div className="mt-8 border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Años de experiencia</p><p className="mt-3 text-sm text-[#c3c6d1]">{({ 1: '1 año', 2: '2 años', '3_PLUS': '3 años o más' }[lead.experience_years] || 'No indicado')}</p></div>}
                        {lead.experience && <div className="mt-8 border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Experiencia demostrable</p><p className="mt-3 whitespace-pre-line text-sm leading-7 text-[#c3c6d1]">{lead.experience}</p></div>}
                        {lead.motivation && <div className="mt-8 border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Motivación</p><p className="mt-3 whitespace-pre-line text-sm leading-7 text-[#c3c6d1]">{lead.motivation}</p></div>}
                        {lead.discard_reason && <div className="mt-8 border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Motivo del descarte</p><p className="mt-3 rounded-lg border border-[#673344] bg-[#321622]/50 px-3 py-2 text-sm leading-6 text-[#ffb1bd]">{lead.discard_reason}</p></div>}
                        <div className="mt-8 border-t border-[#292d39] pt-7">{lead.candidate ? <p className="inline-flex items-center gap-2 text-sm text-[#8ff0bd]"><FiCheckCircle /> Convertido a candidato  ·  {lead.candidate.code}</p> : lead.status === 'DISCARDED' ? <p className="inline-flex items-center gap-2 text-sm text-[#ffb1bd]"><FiTrash2 /> Lead descartado</p> : <button type="button" onClick={convert} disabled={form.processing} className="velvet-button">{form.processing ? 'Convirtiendo…' : 'Convertir en candidato →'}</button>}</div>
                    </section>
                    <aside className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-7">
                        <div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiClock size={16} /></span><div><p className="text-sm font-medium text-white">Historial de la aplicación</p><p className="mt-1 text-xs text-[#7f8495]">Actividad registrada</p></div></div>
                        <div className="mt-7 space-y-6">{activities.length ? activities.map((activity) => <div key={activity.timeline_id || activity.id} className="relative border-l border-[#8b28ad] pl-5"><span className="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#c22be8] shadow-[0_0_12px_rgba(194,43,232,.65)]" /><p className="text-sm text-[#e6e1ea]">{activity.description}</p><p className="mt-1 text-xs text-[#7f8495]">{formatFriendlyDateTime(activity.created_at)}</p></div>) : <p className="text-sm text-[#7f8495]">Aún no hay actividad registrada.</p>}</div>
                        <div className="mt-8 border-t border-[#292d39] pt-6"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Contacto directo</p><div className="mt-4 space-y-3"><a href={emailHref || undefined} className={`flex items-center gap-3 rounded-lg border px-3 py-3 transition ${emailHref ? 'border-[#302c3c] bg-[#151622] hover:border-[#88429a] hover:bg-[#21172a]' : 'pointer-events-none border-[#262934] bg-[#12141c] opacity-50'}`}><span className="grid h-8 w-8 place-items-center rounded-full bg-[#3b1948] text-[#d56bea]"><FiMail size={15} /></span><span className="min-w-0"><strong className="block text-xs font-medium text-[#e7e1eb]">Enviar correo</strong><small className="mt-1 block text-[10px] text-[#7f8495]">{lead.email || 'Correo no indicado'}</small></span></a><a href={whatsappHref || undefined} target="_blank" rel="noreferrer" className={`flex items-center gap-3 rounded-lg border px-3 py-3 transition ${whatsappHref ? 'border-[#254b49] bg-[#122321] hover:border-[#36ae97] hover:bg-[#173d37]' : 'pointer-events-none border-[#262934] bg-[#12141c] opacity-50'}`}><span className="grid h-8 w-8 place-items-center rounded-full bg-[#16483f] text-[#8ee2ca]"><FaWhatsapp size={15} /></span><span className="min-w-0"><strong className="block text-xs font-medium text-[#e7e1eb]">Escribir por WhatsApp</strong><small className="mt-1 block text-[10px] text-[#7f8495]">{lead.phone || 'WhatsApp no indicado'}</small></span></a></div></div>
                        <ContractingDocumentsUpload candidateId={lead.candidate?.id} status={pipelineStatus} documents={lead.documents || []} />
                        <InterviewNotesUpload candidateId={lead.candidate?.id} status={pipelineStatus} documents={lead.documents || []} />
                    </aside>
                </div>
            </div>
            {discarding && <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"><form onSubmit={discard} className="w-full max-w-md rounded-2xl border border-[#49334f] bg-[#151522] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-medium text-white">Descartar lead</h2><p className="mt-2 text-xs leading-5 text-[#969baa]">Indica por qué no continuará en el proceso. Este motivo quedará en el historial.</p></div><button type="button" onClick={() => setDiscarding(false)} className="text-[#9297a7] hover:text-white" aria-label="Cerrar"><FiX /></button></div><textarea required minLength="5" value={discardForm.data.reason} onChange={(event) => discardForm.setData('reason', event.target.value)} className="velvet-input mt-5" rows="4" placeholder="Ej. Datos de contacto inválidos…" />{discardForm.errors.reason && <p className="mt-2 text-xs text-[#ffb1bd]">{discardForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDiscarding(false)} className="rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf]">Cancelar</button><button disabled={discardForm.processing} className="rounded-lg bg-[#7d3144] px-4 py-2 text-xs font-medium text-white">{discardForm.processing ? 'Descartando…' : 'Confirmar descarte'}</button></div></form></div>}
        </Layout>
    </>;
}

