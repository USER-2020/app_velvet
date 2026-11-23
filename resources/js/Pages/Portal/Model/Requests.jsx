import { Head, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FiAlertCircle, FiCheckCircle, FiFileText, FiSend } from 'react-icons/fi';
import VelvetDateTimeField from '@/Components/VelvetDateTimeField';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import { subscribeToRealtime } from '@/lib/ably';

const date = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const status = { PENDING: 'Pendiente', APPROVED: 'Aprobada', REJECTED: 'Rechazada', CANCELLED: 'Cancelada' };
const typeLabels = { SUPPORT: 'Soporte', ROOM: 'Room o equipo', ROOM_REQUEST: 'Solicitud de room', SCHEDULE: 'Horario', SCHEDULE_CHANGE: 'Cambio de horario', PROFILE: 'Perfil', PLATFORM_REQUEST: 'Solicitud de plataforma' };

export default function Requests({ requests = [], canCreate = false }) {
    const form = useForm({ type: 'SUPPORT', details: '', starts_at: '', ends_at: '' });
    const submit = (event) => { event.preventDefault(); form.post(route('portal.requests.store'), { preserveScroll: true, onSuccess: () => form.reset('details', 'starts_at', 'ends_at') }); };
    useEffect(() => subscribeToRealtime((_data, event) => {
        if (!['operation.request.created', 'operation.request.updated'].includes(event)) return;
        router.reload({ only: ['requests'], preserveScroll: true, preserveState: true });
    }), []);

    return <>
        <Head title="Mis solicitudes" />
        <Layout><div className="admin-page-content" data-portal-shell><header className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Portal de modelo</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Mis solicitudes</h1><p className="mt-2 max-w-3xl text-sm text-[#969baa]">Envía una solicitud operativa y consulta su estado sin perder el historial de la conversación.</p></div></header>
            <div className="model-module-columns mt-8">
                {canCreate && <section data-tour="model-request-form" className="model-module-card"><div className="model-module-card__header"><div><p className="model-module-kicker">Canal operativo</p><h2>Nueva solicitud</h2><p>Describe una necesidad concreta para que el equipo pueda atenderla.</p></div><FiSend aria-hidden="true" className="model-module-icon" /></div><form onSubmit={submit} className="model-module-form"><label>Tipo<select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}><option value="SUPPORT">Soporte</option><option value="ROOM">Room o equipo</option><option value="SCHEDULE">Horario</option><option value="PROFILE">Perfil</option></select></label><label>Detalle<textarea required minLength="5" maxLength="5000" rows="5" value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} placeholder="Cuéntanos qué necesitas y cuándo aplica." /></label><div className="model-module-form__grid model-module-form__datetime"><VelvetDateTimeField label="Inicio" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} error={form.errors.starts_at} /><VelvetDateTimeField label="Fin" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} error={form.errors.ends_at} /></div>{form.errors.details && <p className="model-module-error" role="alert">{form.errors.details}</p>}<button type="submit" className="model-module-button" disabled={form.processing}><FiSend aria-hidden="true" />{form.processing ? 'Enviando…' : 'Enviar solicitud'}</button></form></section>}
                <section data-tour="model-request-history" className="model-module-card"><div className="model-module-card__header"><div><p className="model-module-kicker">Seguimiento</p><h2>Historial de solicitudes</h2><p>Consulta las respuestas y el estado más reciente.</p></div><FiFileText aria-hidden="true" className="model-module-icon" /></div>{requests.length ? <ul className="model-request-list" aria-live="polite">{requests.map((item) => <li key={item.id}><div className="model-request-list__content"><div className="model-request-list__header"><div><strong>{typeLabels[item.type] || item.type}</strong><small>{date(item.createdAt)}</small></div><span className={`model-module-status ${item.status === 'APPROVED' ? 'is-success' : item.status === 'REJECTED' ? 'is-danger' : ''}`}>{status[item.status] || item.status}</span></div><p>{item.details}</p>{item.response && <p className="model-module-callout">{item.response}</p>}</div></li>)}</ul> : <div className="model-module-empty"><FiAlertCircle aria-hidden="true" /><p>No hay solicitudes todavía.</p><span>Cuando envíes una, podrás seguirla desde este módulo.</span></div>}</section>
            </div>
        </div></Layout>
    </>;
}
