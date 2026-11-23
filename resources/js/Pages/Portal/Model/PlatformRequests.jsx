import { Head, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FiArrowLeft, FiCheckCircle, FiGlobe, FiSend } from 'react-icons/fi';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import { subscribeToRealtime } from '@/lib/ably';

const date = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const status = { PENDING: 'Pendiente de validación', APPROVED: 'Aprobada', REJECTED: 'Rechazada', CANCELLED: 'Cancelada' };

export default function PlatformRequests({ platforms = [], requests = [], canCreate = false }) {
    const form = useForm({ platform_catalog_id: platforms[0]?.id || '', nickname: '' });
    const submit = (event) => {
        event.preventDefault();
        form.post(route('model.platform-requests.store'), { preserveScroll: true, onSuccess: () => form.reset('nickname') });
    };
    useEffect(() => subscribeToRealtime((_data, event) => {
        if (!['operation.request.created', 'operation.request.updated'].includes(event)) return;
        router.reload({ only: ['requests'], preserveScroll: true, preserveState: true });
    }), []);

    return <>
        <Head title="Solicitar plataformas" />
        <Layout><div className="admin-page-content" data-portal-shell><header className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Perfil · Mis plataformas</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Solicitar plataformas</h1><p className="mt-2 max-w-3xl text-sm text-[#969baa]">Propón un nickname para una plataforma. El equipo revisará disponibilidad, identidad y autorización antes de vincularlo.</p></div></header>
            <div className="mt-8 flex items-center gap-2 text-xs text-[#8f8a99]"><FiArrowLeft aria-hidden="true" /><a href={route('model.profile')} className="hover:text-white">Volver a mi perfil</a><span aria-hidden="true">·</span><span className="text-[#e3a2ef]">Solicitar plataformas</span></div>
            <div className="model-module-columns mt-6">
                {canCreate && <section data-tour="model-platform-request-form" className="model-module-card"><div className="model-module-card__header"><div><p className="model-module-kicker">Nueva solicitud</p><h2>Solicita una plataforma</h2><p>Usa el nickname que deseas reservar. No se crea ninguna cuenta automáticamente.</p></div><FiGlobe aria-hidden="true" className="model-module-icon" /></div><form onSubmit={submit} className="model-module-form"><label>¿Qué nickname te gustaría?<input required minLength="3" maxLength="80" pattern="[A-Za-z0-9._-]+" value={form.data.nickname} onChange={(event) => form.setData('nickname', event.target.value)} placeholder="Ej. velvet_luna" autoComplete="off" aria-describedby="nickname-help" />{form.errors.nickname && <span className="model-module-error" role="alert">{form.errors.nickname}</span>}<small id="nickname-help">Usa letras, números, punto, guion o guion bajo.</small></label><label>Plataforma<select required value={form.data.platform_catalog_id} onChange={(event) => form.setData('platform_catalog_id', event.target.value)}>{platforms.length ? platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>) : <option value="">No hay plataformas disponibles</option>}</select>{form.errors.platform_catalog_id && <span className="model-module-error" role="alert">{form.errors.platform_catalog_id}</span>}</label><div className="model-module-callout"><strong>¿Qué ocurre después?</strong><span>El equipo validará el nickname y, si procede, gestionará la cuenta desde Plataformas. Recibirás el resultado en este historial.</span></div><button type="submit" className="model-module-button" disabled={form.processing || !platforms.length}><FiSend aria-hidden="true" />{form.processing ? 'Guardando…' : 'Guardar solicitud'}</button></form></section>}
                <section data-tour="model-platform-request-history" className="model-module-card"><div className="model-module-card__header"><div><p className="model-module-kicker">Seguimiento</p><h2>Mis solicitudes</h2><p>Consulta el estado de cada nickname solicitado.</p></div><FiCheckCircle aria-hidden="true" className="model-module-icon" /></div>{requests.length ? <ul className="model-request-list">{requests.map((item) => <li key={item.id}><div><strong>{item.platform}</strong><small>{date(item.createdAt)}</small></div><span className={`model-module-status ${item.status === 'APPROVED' ? 'is-success' : item.status === 'REJECTED' ? 'is-danger' : ''}`}>{status[item.status] || item.status}</span><p>Nickname: <strong>{item.nickname}</strong></p>{item.response && <p className="model-module-callout">{item.response}</p>}</li>)}</ul> : <div className="model-module-empty"><FiGlobe aria-hidden="true" /><p>Aún no has solicitado una plataforma.</p><span>Cuando envíes una solicitud aparecerá aquí con su estado.</span></div>}</section>
            </div>
        </div></Layout>
    </>;
}
