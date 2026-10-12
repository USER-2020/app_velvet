import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FaWhatsapp } from 'react-icons/fa6';
import { FiArrowLeft, FiArrowRight, FiCheckCircle, FiClock, FiEdit3, FiFileText, FiMail, FiShield, FiTrash2, FiUser, FiX, FiCalendar } from 'react-icons/fi';
import { formatFriendlyDate, formatFriendlyDateTime } from '../../../../lib/date';
import { candidateStatusLabels, candidateTypeLabels } from '../../../../lib/recruitmentLabels';
import AdminApplicationEditModal from '../../../../Components/AdminApplicationEditModal';
import InterviewNotesUpload from '../../../../Components/InterviewNotesUpload';
import Layout from '../Layout';
import { subscribeToRealtime } from '../../../../lib/ably';

const statusOrder = ['NEW', 'CONTACTED', 'PREQUALIFIED', 'INTERVIEW', 'EVALUATION', 'ADMITTED', 'WAITING', 'CONTRACTING', 'ONBOARDING', 'INDUCTION', 'READY_TO_ACTIVATE', 'ACTIVE', 'WITHDRAWN'];
const statusOptions = statusOrder.map((value) => [value, candidateStatusLabels[value]]);
const completedStatuses = new Set(['PREQUALIFIED', 'ADMITTED', 'WAITING', 'DISCARDED', 'ONBOARDING', 'CONTRACTING', 'INDUCTION', 'READY_TO_ACTIVATE', 'ACTIVE', 'WITHDRAWN']);
const sexLabels = { WOMAN: 'Mujer', MAN: 'Hombre' };
const identityStatusLabels = { NOT_STARTED: 'Sin iniciar', IN_PROGRESS: 'En progreso', IN_REVIEW: 'En revisión manual', APPROVED: 'Identidad verificada', DECLINED: 'No aprobada', EXPIRED: 'Sesión vencida', ABANDONED: 'No completada' };

const englishLevelBadges = {
    A1: { label: 'A1 · Inicial', className: 'border-[#7d3144] bg-[#321622] text-[#ffb1bd]' },
    A2: { label: 'A2 · Básico', className: 'border-[#8a6229] bg-[#342515] text-[#f2cf91]' },
    B1: { label: 'B1 · Intermedio', className: 'border-[#8a7b2e] bg-[#352f16] text-[#f5e59b]' },
    B2: { label: 'B2 · Intermedio alto', className: 'border-[#2c6d95] bg-[#102e42] text-[#9edcff]' },
    C1: { label: 'C1 · Avanzado', className: 'border-[#713080] bg-[#32133d] text-[#edb4f8]' },
    C2: { label: 'C2 · Dominio avanzado', className: 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' },
    NATIVE: { label: 'Nativo', className: 'border-[#20817c] bg-[#103936] text-[#92eee2]' },
};

function EnglishLevelBadge({ level }) {
    const badge = englishLevelBadges[level] || { label: level, className: 'border-[#51445a] bg-[#201b29] text-[#c8bfd0]' };

    return <span className={`inline-flex w-fit rounded-full border px-2.5 py-1 text-[11px] font-medium ${badge.className}`}>{badge.label}</span>;
}

function Detail({ label, value }) {
    const isEnglishLevel = label === 'Nivel de inglés' && value && value !== 'No aplica';

    return <div className="min-w-0"><dt className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">{label}</dt><dd className="mt-2 break-words text-sm text-[#f2edf5] [overflow-wrap:anywhere]">{isEnglishLevel ? <EnglishLevelBadge level={value} /> : value || 'No indicado'}</dd></div>;
}

function ActivationModal({ open, form, onClose, onSubmit }) {
    if (!open) return null;

    return <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"><form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-[#49334f] bg-[#151522] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] uppercase tracking-[.2em] text-[#d56bea]">Activar acceso</p><h2 className="mt-2 text-xl font-medium text-white">Crear acceso al dashboard</h2><p className="mt-2 text-xs leading-5 text-[#969baa]">Se creará el usuario con rol de modelo o monitor y el perfil pasará a Activo.</p></div><button type="button" onClick={onClose} className="text-[#9297a7] hover:text-white" aria-label="Cerrar"><FiX /></button></div><label className="mt-5 block text-xs text-[#c9c2cf]">Contraseña inicial<input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className="velvet-input mt-2" minLength="8" autoComplete="new-password" required />{form.errors.password && <span className="mt-1 block text-xs text-[#ffb1bd]">{form.errors.password}</span>}</label><label className="mt-4 block text-xs text-[#c9c2cf]">Confirmar contraseña<input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} className="velvet-input mt-2" minLength="8" autoComplete="new-password" required />{form.errors.password_confirmation && <span className="mt-1 block text-xs text-[#ffb1bd]">{form.errors.password_confirmation}</span>}</label>{form.errors.access && <p className="mt-4 rounded-md border border-[#7d3144] bg-[#321622] px-3 py-2 text-xs text-[#ffb1bd]">{form.errors.access}</p>}<div className="mt-6 flex justify-end gap-3"><button type="button" onClick={onClose} className="rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf]">Cancelar</button><button type="submit" disabled={form.processing} className="velvet-button">{form.processing ? 'Activando…' : 'Crear y activar acceso'}</button></div></form></div>;
}

function CredentialModal({ open, form, onClose, onSubmit }) {
    if (!open) return null;
    return <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"><form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-[#49334f] bg-[#151522] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] uppercase tracking-[.2em] text-[#d56bea]">Crear acceso</p><h2 className="mt-2 text-xl font-medium text-white">Enviar credenciales</h2><p className="mt-2 text-xs leading-5 text-[#969baa]">Se generará una contraseña temporal aleatoria y se enviará al correo registrado. Al ingresar por primera vez, el perfil pasará a inducción.</p></div><button type="button" onClick={onClose} className="text-[#9297a7] hover:text-white" aria-label="Cerrar"><FiX /></button></div>{form.errors.access && <p className="mt-5 rounded-md border border-[#7d3144] bg-[#321622] px-3 py-2 text-xs text-[#ffb1bd]">{form.errors.access}</p>}<div className="mt-6 flex justify-end gap-3"><button type="button" onClick={onClose} className="rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf]">Cancelar</button><button type="submit" disabled={form.processing} className="velvet-button">{form.processing ? 'Enviando…' : 'Crear y enviar credenciales'}</button></div></form></div>;
}

function AccessStatusModal({ open, active, form, name, onClose, onSubmit }) {
    if (!open) return null;
    const nextLabel = active ? 'desactivar' : 'reactivar';
    return <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"><form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-[#49334f] bg-[#151522] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] uppercase tracking-[.2em] text-[#d56bea]">Estado del acceso</p><h2 className="mt-2 text-xl font-medium text-white">¿{active ? 'Desactivar' : 'Reactivar'} perfil?</h2><p className="mt-2 text-xs leading-5 text-[#969baa]">El acceso de {name} se va a {nextLabel}. No se eliminarán sus datos ni su historial.</p></div><button type="button" onClick={onClose} className="text-[#9297a7] hover:text-white" aria-label="Cerrar"><FiX /></button></div>{form.errors.access && <p className="mt-4 rounded-md border border-[#7d3144] bg-[#321622] px-3 py-2 text-xs text-[#ffb1bd]">{form.errors.access}</p>}<div className="mt-6 flex justify-end gap-3"><button type="button" onClick={onClose} className="rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf]">Cancelar</button><button type="submit" disabled={form.processing} className={active ? 'rounded-lg bg-[#8e394c] px-4 py-2 text-xs font-medium text-white hover:bg-[#aa485d]' : 'velvet-button'}>{form.processing ? 'Guardando…' : active ? 'Desactivar perfil' : 'Reactivar perfil'}</button></div></form></div>;
}

const processStageDescriptions = {
    NEW: 'Aplicaci\u00f3n recibida y pendiente de primer contacto.',
    CONTACTED: 'El equipo ya contact\u00f3 a la persona y confirm\u00f3 su inter\u00e9s.',
    QUALIFIED: 'El perfil cumple los criterios iniciales del proceso.',
    PREQUALIFIED: 'La precalificaci\u00f3n fue completada y aprobada.',
    INTERVIEW: 'La persona est\u00e1 lista para seleccionar o realizar una entrevista.',
    EVALUATION: 'El equipo est\u00e1 evaluando el resultado de la entrevista.',
    ADMITTED: 'La persona fue admitida para continuar con la vinculaci\u00f3n.',
    WAITING: 'El proceso est\u00e1 en espera de una definici\u00f3n o acci\u00f3n.',
    ONBOARDING: 'La persona est\u00e1 completando el proceso de incorporaci\u00f3n.',
    CONTRACTING: 'Se est\u00e1n gestionando los documentos de contrataci\u00f3n.',
    INDUCTION: 'La persona est\u00e1 realizando la inducci\u00f3n inicial.',
    READY_TO_ACTIVATE: 'El perfil est\u00e1 listo para ser activado.',
    ACTIVE: 'La persona est\u00e1 activa en el sistema.',
    WITHDRAWN: 'La persona se retir\u00f3 del proceso.',
};

const cleanStatusLabel = (value) => String(value || '')
    .replace(/\u00c3\u00a1/g, '\u00e1').replace(/\u00c3\u00a9/g, '\u00e9').replace(/\u00c3\u00ad/g, '\u00ed')
    .replace(/\u00c3\u00b3/g, '\u00f3').replace(/\u00c3\u00ba/g, '\u00fa').replace(/\u00c3\u00b1/g, '\u00f1')
    .replace(/\u00c2/g, '').replace(/\u00e2\u0080\u00a6/g, '...');

function ProcessStatusCarousel({ currentStatus, onChange }) {
    const stages = statusOptions;
    const activeIndex = Math.max(0, stages.findIndex(([value]) => value === currentStatus));
    const [selectedIndex, setSelectedIndex] = useState(activeIndex);
    const selectedStage = stages[selectedIndex] || stages[0];

    useEffect(() => setSelectedIndex(activeIndex), [activeIndex]);

    return <div data-tour="candidate-process" className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 shadow-[0_18px_55px_rgba(0,0,0,.16)] sm:p-6">
        <div className="flex items-center justify-between gap-4">
            <div><p className="text-[10px] uppercase tracking-[.18em] text-[#d56bea]">Estado del proceso</p><p className="mt-2 text-xs text-[#969baa]">La etapa activa se actualiza para todo el equipo en tiempo real.</p></div>
            <span className="rounded-full border border-[#9142a7]/60 bg-[#3d164d] px-3 py-1.5 text-xs text-[#f0c1fa]">{cleanStatusLabel(selectedStage?.[1])}</span>
        </div>
        <div className="mt-5 min-w-0 overflow-hidden">
            <div className="process-carousel-scroll flex gap-3 overflow-x-auto pb-3" aria-label="Etapas del proceso">
                {stages.map(([value, label], index) => <button type="button" key={value} onClick={() => { setSelectedIndex(index); if (value !== currentStatus) onChange(value); }} className={`min-w-[220px] flex-1 rounded-xl border p-4 text-left transition ${value === currentStatus ? 'border-[#c22be8] bg-[#291336] shadow-[0_0_18px_rgba(194,43,232,.18)]' : index === selectedIndex ? 'border-[#8c3c9e] bg-[#1b1625]' : 'border-[#343044] bg-[#151522] hover:border-[#694574]'}`}><div className="flex items-center justify-between gap-3"><span className="text-xs font-semibold text-white">{cleanStatusLabel(label)}</span><span className={`h-2.5 w-2.5 rounded-full ${value === currentStatus ? 'bg-[#c22be8] shadow-[0_0_10px_#c22be8]' : 'bg-[#555064]'}`} /></div><p className="mt-3 text-xs leading-5 text-[#969baa]">{processStageDescriptions[value] || 'Etapa del proceso de selecci\u00f3n.'}</p>{value === currentStatus && <p className="mt-3 text-[10px] font-medium uppercase tracking-[.14em] text-[#e4a3f1]">Etapa activa</p>}</button>)}
            </div>
        </div>
    </div>;
}

export default function Show({ candidate }) {
    const sendForm = useForm();
    const discardForm = useForm({ reason: '' });
    const [editing, setEditing] = useState(false);
    const [discarding, setDiscarding] = useState(false);
    const [activating, setActivating] = useState(false);
    const [accessStatusOpen, setAccessStatusOpen] = useState(false);
    const activationForm = useForm({ password: '', password_confirmation: '' });
    const accessStatusForm = useForm({ active: false });
    const contractingForm = useForm();
    const fullName = [candidate.lead.first_name, candidate.lead.last_name].filter(Boolean).join(' ');
    const whatsappNumber = String(candidate.lead.phone || '').replace(/\D/g, '');
    const whatsappHref = whatsappNumber ? `https://wa.me/${whatsappNumber.startsWith('57') ? whatsappNumber : `57${whatsappNumber}`}?text=${encodeURIComponent(`Hola ${fullName}, te escribe el equipo de The Velvet Studio. Revisa tu correo para completar los requisitos iniciales de tu proceso.`)}` : null;
    const emailHref = candidate.lead.email ? `mailto:${candidate.lead.email}?subject=${encodeURIComponent('Requisitos iniciales  ·  The Velvet Studio')}` : null;
    const formCompleted = Boolean(candidate.prequalification_completed_at);
    const canSendForm = !formCompleted && !completedStatuses.has(candidate.status);
    const formSent = Boolean(candidate.prequalification_sent_at);
    const interview = candidate.interviews?.find((item) => ['INVITED', 'SCHEDULED'].includes(item.status));
    const identityStatus = candidate.identity_verification_status || 'NOT_STARTED';
    const identityData = candidate.identity_verification_data || {};
    const admissionRecommendation = candidate.status === 'EVALUATION';
    const activationRecommendation = candidate.status === 'ONBOARDING' && !candidate.user_id;
    const contractingRecommendation = candidate.status === 'ADMITTED';
    const inductionRecommendation = candidate.status === 'INDUCTION';
    const readyToActivateRecommendation = candidate.status === 'READY_TO_ACTIVATE';
    const interviewRecommendation = candidate.status === 'PREQUALIFIED';

    useEffect(() => subscribeToRealtime((event, eventName) => {
        if (Number(event.candidate_id) !== Number(candidate.id)) return;
        if (eventName === 'candidate.prequalification_completed' || eventName === 'candidate.status_changed') {
            router.reload({ only: ['candidate', 'realtime'], preserveScroll: true, preserveState: true });
        }
    }), [candidate.id]);

    const discard = (event) => {
        event.preventDefault();
        discardForm.post(`/admin/candidates/${candidate.id}/discard`, { preserveScroll: true, onSuccess: () => setDiscarding(false) });
    };

    const changeStatus = (event) => {
        router.patch(`/admin/candidates/${candidate.id}/status`, { status: event.target.value }, { preserveScroll: true });
    };

    const sendPrequalification = () => {
        sendForm.post(`/admin/candidates/${candidate.id}/prequalification`, { preserveScroll: true });
    };

    const activateAccess = (event) => {
        event.preventDefault();
        activationForm.post(`/admin/candidates/${candidate.id}/activate-access`, { preserveScroll: true, onSuccess: () => { setActivating(false); activationForm.reset(); } });
    };

    const updateAccessStatus = (event) => {
        event.preventDefault();
        accessStatusForm.patch(`/admin/candidates/${candidate.id}/access-status`, { preserveScroll: true, onSuccess: () => setAccessStatusOpen(false) });
    };

    const startContracting = () => contractingForm.post(`/admin/candidates/${candidate.id}/start-contracting`, { preserveScroll: true });

    return <>
        <Head title={candidate.code} />
        <Layout title="Candidate 360">
            <CredentialModal open={activating} form={activationForm} onClose={() => setActivating(false)} onSubmit={activateAccess} />
            <AccessStatusModal open={accessStatusOpen} active={Boolean(candidate.user?.is_active)} form={accessStatusForm} name={fullName} onClose={() => setAccessStatusOpen(false)} onSubmit={updateAccessStatus} />
            {candidate.status === 'ACTIVE' && candidate.user && <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#2d8669]/60 bg-[#12372e]/50 p-4"><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#9af2cb]">Acceso operativo</p><p className="mt-1 text-xs text-[#b9d8cb]">{candidate.user.is_active ? 'El perfil está activo y trabaja con datos reales.' : 'El perfil está desactivado y no puede ingresar a la operación.'}</p></div><button type="button" onClick={() => { accessStatusForm.setData('active', !candidate.user.is_active); setAccessStatusOpen(true); }} className={candidate.user.is_active ? 'rounded-lg border border-[#9e4258] px-4 py-2 text-xs font-medium text-[#ffb1bd] hover:bg-[#321622]' : 'rounded-lg bg-[#2d8669] px-4 py-2 text-xs font-medium text-white hover:bg-[#3ba47f]'}>{candidate.user.is_active ? 'Desactivar perfil' : 'Reactivar perfil'}</button></div>}
            <div>
                <Link href="/admin/candidates" className="inline-flex items-center gap-2 text-sm text-[#d56bea] transition hover:text-white"><FiArrowLeft size={15} /> Candidatos</Link>
                <div className="mt-7 flex flex-col justify-between gap-5 border-b border-[#252936] pb-7 md:flex-row md:items-end">
                    <div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Perfil de candidata  ·  {candidate.code}</p><h1 className="mt-3 font-editorial text-4xl text-white">{fullName}</h1><p className="mt-2 text-sm text-[#969baa]">{candidateTypeLabels[candidate.candidate_type] || candidate.candidate_type}</p></div>
                    <div className="flex flex-wrap items-center gap-2"><span className="w-fit rounded-full border border-[#9142a7]/50 bg-[#3d164d] px-3 py-1.5 text-xs text-[#f0c1fa]">{candidateStatusLabels[candidate.status] || candidate.status}</span><button type="button" onClick={() => setEditing((value) => !value)} className="inline-flex items-center gap-2 rounded-lg border border-[#49334f] px-3 py-2 text-xs text-[#e6c4ed] transition hover:border-[#a84bc2] hover:text-white"><FiEdit3 size={14} />{editing ? 'Cerrar edición' : 'Editar datos'}</button>{candidate.status !== 'DISCARDED' && <button type="button" onClick={() => setDiscarding(true)} className="inline-flex items-center gap-2 rounded-lg border border-[#673344] px-3 py-2 text-xs text-[#ffb1bd] transition hover:border-[#bb4c66] hover:bg-[#321622]"><FiTrash2 size={14} />Descartar</button>}</div>
                </div>
                <AdminApplicationEditModal open={editing} onClose={() => setEditing(false)} endpoint={`/admin/candidates/${candidate.id}`} person={candidate.lead} candidateType={candidate.candidate_type} title="Editar información del candidato" />
                <div className="mt-8 grid items-start gap-6 min-[1100px]:grid-cols-[minmax(0,1fr)_minmax(280px,.7fr)]">
                    <section data-tour="candidate-profile" className="min-w-0 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-8">
                        <div className="flex items-center gap-3 border-b border-[#292d39] pb-5"><span className="grid h-10 w-10 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiUser size={18} /></span><div><p className="text-sm font-medium text-white">Información de la persona</p><p className="mt-1 text-xs text-[#7f8495]">Datos enviados en el onboarding público.</p></div></div>
                        <dl className="mt-7 grid gap-x-8 gap-y-7 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"><Detail label="Nombre completo" value={fullName} /><Detail label="Sexo" value={sexLabels[candidate.lead.sex] || candidate.lead.sex} /><Detail label="Email" value={candidate.lead.email} /><Detail label="WhatsApp" value={candidate.lead.phone} /><Detail label="Ciudad" value={candidate.lead.city} /><Detail label="País" value={candidate.lead.country} /><Detail label="Fecha de nacimiento" value={candidate.lead.birth_date ? formatFriendlyDate(candidate.lead.birth_date) : null} /><Detail label="Habla inglés" value={candidate.lead.speaks_english ? 'Sí' : 'No'} /><Detail label="Nivel de inglés" value={candidate.lead.speaks_english ? candidate.lead.english_level : 'No aplica'} /><Detail label="Disponibilidad" value={candidate.lead.availability} /><Detail label="Modalidad" value={candidate.lead.work_mode} /></dl>
                        {candidate.lead.candidate_type === 'MONITOR' && <Detail label="Años de experiencia" value={({ 1: '1 año', 2: '2 años', '3_PLUS': '3 años o más' }[candidate.lead.experience_years] || candidate.lead.experience_years)} />}
                        {candidate.lead.experience && <div className="mt-8 border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Experiencia demostrable</p><p className="mt-3 whitespace-pre-line text-sm leading-7 text-[#c3c6d1]">{candidate.lead.experience}</p></div>}
                        <div className="mt-8 border-t border-[#292d39] pt-7"><ProcessStatusCarousel currentStatus={candidate.status} onChange={(status) => changeStatus({ target: { value: status } })} />{candidate.discard_reason && <p className="mt-4 rounded-lg border border-[#673344] bg-[#321622]/50 px-3 py-2 text-xs leading-5 text-[#ffb1bd]"><strong>Motivo del descarte:</strong> {candidate.discard_reason}</p>}</div>
                    </section>
                    <aside data-tour="candidate-actions" className="min-w-0 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-7">
                        <div className={`space-y-8 ${interviewRecommendation || admissionRecommendation || activationRecommendation || contractingRecommendation || inductionRecommendation || readyToActivateRecommendation ? 'admission-active' : ''}`}>
                            {interviewRecommendation && <div className="rounded-xl border border-[#2c6d95] bg-gradient-to-r from-[#102e42] to-[#151522] p-5 shadow-[0_12px_35px_rgba(44,109,149,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#1b5275] text-[#9edcff]"><FiCalendar size={17} /></span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#9edcff]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Programar entrevista</h2><p className="mt-1 text-xs leading-5 text-[#b5d9ed]">La precalificación está completa. Genera horarios disponibles y envía la invitación para que la candidata elija su cita.</p></div></div><Link href="/admin/interviews" className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#1b5275] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#2776a7]">Ir a entrevistas <FiArrowRight size={15} /></Link></div>}
                            {inductionRecommendation && <div className="rounded-xl border border-[#b8863b] bg-gradient-to-r from-[#342515] to-[#151522] p-5 shadow-[0_12px_35px_rgba(184,134,59,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#6b4e22] text-[#ffe1a1]">→</span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#ffe1a1]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Confirmar inducción</h2><p className="mt-1 text-xs leading-5 text-[#dfc99e]">Verifica que la inducción terminó para preparar el perfil para su activación.</p></div></div><button type="button" onClick={() => changeStatus({ target: { value: 'READY_TO_ACTIVATE' } })} className="mt-5 w-full rounded-lg bg-[#a8792f] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#c18e3c]">Confirmar inducción completada →</button></div>}
                            {readyToActivateRecommendation && <div className="rounded-xl border border-[#2d8669] bg-gradient-to-r from-[#12372e] to-[#151522] p-5 shadow-[0_12px_35px_rgba(45,134,105,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#1e5b4a] text-[#9af2cb]">✓</span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#9af2cb]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Activar perfil</h2><p className="mt-1 text-xs leading-5 text-[#b9d8cb]">La inducción fue completada y el perfil ya está listo para activarse.</p></div></div><button type="button" onClick={() => changeStatus({ target: { value: 'ACTIVE' } })} className="mt-5 w-full rounded-lg bg-[#2d8669] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#3ba47f]">Activar perfil →</button></div>}
                            {contractingRecommendation && <div className="rounded-xl border border-[#b8863b] bg-gradient-to-r from-[#342515] to-[#151522] p-5 shadow-[0_12px_35px_rgba(184,134,59,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#6b4e22] text-[#ffe1a1]">→</span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#ffe1a1]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Iniciar contratación</h2><p className="mt-1 text-xs leading-5 text-[#dfc99e]">Envía los requisitos documentales y pasa el perfil a En espera para programar la contratación física.</p></div></div><button type="button" onClick={startContracting} disabled={contractingForm.processing} className="mt-5 w-full rounded-lg bg-[#a8792f] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#c18e3c]">{contractingForm.processing ? 'Enviando…' : 'Contactar y enviar requisitos →'}</button>{contractingForm.errors.contracting && <p className="mt-3 text-xs text-[#ffb1bd]">{contractingForm.errors.contracting}</p>}</div>}
                            {activationRecommendation && <div className="rounded-xl border border-[#2d8669] bg-gradient-to-r from-[#12372e] to-[#151522] p-5 shadow-[0_12px_35px_rgba(45,134,105,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#1e5b4a] text-[#9af2cb]">✓</span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#9af2cb]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Activar acceso al dashboard</h2><p className="mt-1 text-xs leading-5 text-[#b9d8cb]">El onboarding está listo. Crea las credenciales para que la persona pueda ingresar como {candidate.candidate_type === 'MONITOR' ? 'monitor' : 'modelo'}.</p></div></div><button type="button" onClick={() => setActivating(true)} className="mt-5 w-full rounded-lg bg-[#2d8669] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-[#3ba47f]">Crear y activar acceso →</button></div>}
                            {admissionRecommendation && <div className="rounded-xl border border-[#713080] bg-gradient-to-r from-[#24112d] to-[#15121d] p-5 shadow-[0_12px_35px_rgba(116,31,145,.12)] sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#713080] text-[#f5c8ff]">→</span><div><p className="text-[10px] font-semibold uppercase tracking-[.18em] text-[#e5a1f2]">Siguiente paso recomendado</p><h2 className="mt-3 text-base font-medium text-white">Admitir candidato</h2><p className="mt-1 text-xs leading-5 text-[#bdb3c5]">La evaluación de la entrevista está lista. Confirma la admisión para que la persona avance y reciba el correo de felicitación con las instrucciones de contacto.</p></div></div><button type="button" onClick={() => changeStatus({ target: { value: 'ADMITTED' } })} className="velvet-button mt-5 w-full gap-2">Marcar como admitido <FiArrowRight size={15} /></button></div>}
                            <div className="rounded-xl border border-[#343044] bg-[#151522] p-5 sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiShield size={17} /></span><div><p className="text-sm font-medium text-[#f2edf5]">Verificación de identidad</p><p className="mt-1 text-xs leading-5 text-[#969baa]">Documento, selfie y mayoría de edad.</p></div></div><span className={`mt-5 inline-flex rounded-full border px-3 py-1.5 text-[11px] ${identityStatus === 'APPROVED' ? 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' : identityStatus === 'DECLINED' ? 'border-[#7d3144] bg-[#321622] text-[#ffb1bd]' : 'border-[#5e3b69] bg-[#2b1735] text-[#e7b9f1]'}`}>{identityStatusLabels[identityStatus] || identityStatus}</span>{identityStatus === 'APPROVED' && <dl className="mt-5 space-y-2 text-xs"><div className="flex justify-between gap-4"><dt className="text-[#7f8495]">Mayor de edad</dt><dd className="text-[#9af2cb]">{identityData.age_verified ? 'Confirmada' : 'Pendiente'}</dd></div>{identityData.date_of_birth && <div className="flex justify-between gap-4"><dt className="text-[#7f8495]">Fecha validada</dt><dd className="text-[#c9c2cf]">{identityData.date_of_birth}</dd></div>}</dl>}{candidate.identity_verification_failure_reason && <p className="mt-4 text-xs leading-5 text-[#ffb1bd]">{candidate.identity_verification_failure_reason}</p>}</div>
                            <div><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiClock size={16} /></span><div><p className="text-sm font-medium text-white">Siguiente paso</p><p className="mt-1 text-xs text-[#7f8495]">Precalificación inicial</p></div></div><div className="mt-5 rounded-xl border border-[#343044] bg-[#151522] p-5 sm:p-6"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#3b1948] text-[#d56bea]"><FiFileText size={18} /></span><div><p className="text-sm font-medium text-[#f2edf5]">Formulario de requisitos</p><p className="mt-1 text-xs leading-5 text-[#969baa]">La candidata recibirá un enlace personal en su correo para completar la información inicial.</p></div></div><button type="button" onClick={sendPrequalification} disabled={!canSendForm || sendForm.processing} className="velvet-button mt-5 w-full gap-2">{sendForm.processing ? 'Enviando…' : formSent ? 'Reenviar formulario' : 'Enviar formulario'}<FiArrowRight size={15} /></button>{formCompleted && <p className="mt-4 flex items-center gap-2 text-xs text-[#8ff0bd]"><FiCheckCircle /> Formulario completado</p>}{formSent && !formCompleted && <p className="mt-4 text-[11px] leading-5 text-[#8f94a3]">Enviado el {formatFriendlyDateTime(candidate.prequalification_sent_at)}. El enlace vence el {formatFriendlyDateTime(candidate.prequalification_expires_at)}.</p>}{sendForm.errors.prequalification && <p className="mt-4 rounded-md border border-[#7d3144] bg-[#321622] px-3 py-2 text-xs text-[#ffb1bd]">{sendForm.errors.prequalification}</p>}</div></div>
                            <div className="border-t border-[#292d39] pt-7"><p className="text-[10px] uppercase tracking-[.2em] text-[#7f8495]">Contacto directo</p><div className="mt-5 space-y-3"><a href={emailHref || undefined} className={`flex items-center gap-3 rounded-xl border px-4 py-4 transition ${emailHref ? 'border-[#302c3c] bg-[#151622] hover:border-[#88429a] hover:bg-[#21172a]' : 'pointer-events-none border-[#262934] bg-[#12141c] opacity-50'}`}><span className="grid h-9 w-9 place-items-center rounded-full bg-[#3b1948] text-[#d56bea]"><FiMail size={15} /></span><span className="min-w-0"><strong className="block text-xs font-medium text-[#e7e1eb]">Enviar correo</strong><small className="mt-1 block truncate text-[10px] text-[#7f8495]">{candidate.lead.email || 'Correo no indicado'}</small></span></a><a href={whatsappHref || undefined} target="_blank" rel="noreferrer" className={`flex items-center gap-3 rounded-xl border px-4 py-4 transition ${whatsappHref ? 'border-[#254b49] bg-[#122321] hover:border-[#36ae97] hover:bg-[#173d37]' : 'pointer-events-none border-[#262934] bg-[#12141c] opacity-50'}`}><span className="grid h-9 w-9 items-center justify-center rounded-full bg-[#16483f] text-[#8ee2ca]"><FaWhatsapp size={15} /></span><span className="min-w-0"><strong className="block text-xs font-medium text-[#e7e1eb]">Escribir por WhatsApp</strong><small className="mt-1 block text-[10px] text-[#7f8495]">{candidate.lead.phone || 'WhatsApp no indicado'}</small></span></a></div></div>
                            <InterviewNotesUpload candidateId={candidate.id} status={candidate.status} documents={candidate.lead.documents || []} />
                        </div>
                    </aside>
                </div>
                <section className="mt-6 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] sm:p-7"><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiClock size={16} /></span><div><p className="text-sm font-medium text-white">Historial del proceso</p><p className="mt-1 text-xs text-[#7f8495]">Actividad registrada</p></div></div><div className="mt-6 grid gap-5 md:grid-cols-2">{candidate.activities?.length ? candidate.activities.map((item) => <div key={item.id} className="relative border-l border-[#8b28ad] pl-5"><span className="absolute -left-[5px] top-0 h-2.5 w-2.5 rounded-full bg-[#c22be8] shadow-[0_0_12px_rgba(194,43,232,.65)]" /><p className="text-sm text-[#e6e1ea]">{item.description}</p><p className="mt-1 text-xs text-[#7f8495]">{formatFriendlyDateTime(item.created_at)}</p></div>) : <p className="text-sm text-[#7f8495]">Aún no hay actividad registrada.</p>}</div></section>
            </div>
            {discarding && <div className="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"><form onSubmit={discard} className="w-full max-w-md rounded-2xl border border-[#49334f] bg-[#151522] p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-medium text-white">Descartar candidato</h2><p className="mt-2 text-xs leading-5 text-[#969baa]">Indica por qué no continuará en el proceso. Este motivo quedará en el historial.</p></div><button type="button" onClick={() => setDiscarding(false)} className="text-[#9297a7] hover:text-white" aria-label="Cerrar"><FiX /></button></div><textarea required minLength="5" value={discardForm.data.reason} onChange={(event) => discardForm.setData('reason', event.target.value)} className="velvet-input mt-5" rows="4" placeholder="Ej. No aprobó la verificación de identidad…" />{discardForm.errors.reason && <p className="mt-2 text-xs text-[#ffb1bd]">{discardForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDiscarding(false)} className="rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf]">Cancelar</button><button disabled={discardForm.processing} className="rounded-lg bg-[#7d3144] px-4 py-2 text-xs font-medium text-white">{discardForm.processing ? 'Descartando…' : 'Confirmar descarte'}</button></div></form></div>}
        </Layout>
    </>;
}

