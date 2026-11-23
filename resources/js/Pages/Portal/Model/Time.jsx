import { Head } from '@inertiajs/react';
import { FiActivity, FiClock, FiMonitor, FiRadio, FiUsers } from 'react-icons/fi';
import Layout from '@/Pages/Admin/Recruitment/Layout';

const duration = (seconds = 0) => {
    const value = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    return hours ? `${hours} h ${minutes} min` : `${minutes} min`;
};

const date = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';

export default function Time({ summary = {}, sessions = [], timeoutSeconds = 90 }) {
    const cards = [
        ['Sesiones registradas', summary.sessions || 0, FiUsers],
        ['Activas ahora', summary.active || 0, FiActivity],
        ['Tiempo conectado', duration(summary.connectedSeconds), FiClock],
        ['Tiempo transmitiendo', duration(summary.streamingSeconds), FiRadio],
    ];

    return <>
        <Head title="Mi tiempo" />
        <Layout><div className="admin-page-content" data-portal-shell><header className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Portal de modelo</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Mi tiempo</h1><p className="mt-2 max-w-3xl text-sm text-[#969baa]">Consulta tus sesiones de trabajo, conexión y transmisión registradas por VELVET Agent.</p></div></header>
            <section data-tour="model-time-summary" aria-label="Resumen de tiempo" className="model-module-grid mt-8">
                {cards.map(([label, value, Icon]) => <article key={label} className="model-module-card model-module-stat">
                    <Icon aria-hidden="true" className="model-module-icon" />
                    <strong>{value}</strong>
                    <span>{label}</span>
                </article>)}
            </section>
            <section data-tour="model-time-sessions" className="model-module-card mt-6 overflow-hidden">
                <div className="model-module-card__header"><div><p className="model-module-kicker">Trazabilidad</p><h2>Historial de sesiones</h2><p>Los tiempos se calculan con marcas del servidor y tienen una tolerancia de {timeoutSeconds} segundos.</p></div><FiMonitor aria-hidden="true" className="model-module-icon" /></div>
                {sessions.length ? <div className="model-module-table-wrap"><table className="model-module-table"><caption className="sr-only">Historial de mis sesiones de VELVET Agent</caption><thead><tr><th>Inicio</th><th>Estación</th><th>Estado</th><th>Conectado</th><th>Transmitiendo</th><th>OBS</th></tr></thead><tbody>{sessions.map((session) => <tr key={session.id}><td>{date(session.startedAt)}<small>{session.endedAt ? `Finalizó ${date(session.endedAt)}` : 'En curso o sin cierre'}</small></td><td>{session.station?.hostname || 'Sin estación'}<small>{session.station?.studioId || '—'}</small></td><td><span className={`model-module-status ${session.status === 'active' ? 'is-success' : ''}`}>{session.status === 'active' ? 'Activa' : 'Cerrada'}</span>{session.endReason && <small>{session.endReason}</small>}</td><td>{duration(session.totalConnectedSeconds)}</td><td>{duration(session.totalStreamingSeconds)}</td><td>{session.obs?.streaming ? 'Transmitiendo' : 'En espera'}</td></tr>)}</tbody></table></div> : <div className="model-module-empty"><FiClock aria-hidden="true" /><p>Aún no hay sesiones registradas.</p><span>Cuando Agent envíe una sesión, aparecerá aquí con su estado y duración.</span></div>}
            </section>
        </div></Layout>
    </>;
}
