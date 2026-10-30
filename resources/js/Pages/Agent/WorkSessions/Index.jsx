import { Head } from '@inertiajs/react';
import { FiActivity, FiClock, FiMonitor, FiRadio, FiUsers } from 'react-icons/fi';
import Layout from '../../Admin/Recruitment/Layout';

const formatDuration = (seconds = 0) => {
    const value = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    return hours ? `${hours}h ${minutes}m` : `${minutes}m`;
};

const formatDate = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';

const scopeLabels = { own: 'Mis sesiones', assigned: 'Sesiones de mis modelos', all: 'Sesiones de trabajo' };

export default function Index({ scope = 'all', sessions = [], summary = {}, timeoutSeconds = 90 }) {
    const cards = [
        ['Sesiones registradas', summary.sessions || 0, FiUsers],
        ['Activas ahora', summary.active || 0, FiActivity],
        ['Tiempo conectado', formatDuration(summary.connectedSeconds), FiClock],
        ['Tiempo transmitiendo', formatDuration(summary.streamingSeconds), FiRadio],
    ];

    return <>
        <Head title="Sesiones de trabajo" />
        <Layout>
            <main className="mx-auto max-w-[1400px] px-5 py-6 lg:px-6 xl:px-8">
                <header data-tour="work-sessions-header" className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">VELVET Agent</p><h1 className="mt-2 font-editorial text-4xl tracking-[-.04em] text-white sm:text-5xl">{scopeLabels[scope]}</h1><p className="mt-2 max-w-2xl text-sm leading-6 text-[#969baa]">Tiempo de trabajo autenticado, transmisión y estado de OBS registrados por Master.</p></div>
                    <div className="rounded-lg border border-[#343044] bg-[#151522] px-4 py-3 text-right"><p className="text-[10px] uppercase tracking-[.16em] text-[#858a99]">Tolerancia de desconexión</p><p className="mt-1 text-sm text-white">{timeoutSeconds} segundos</p></div>
                </header>
                <section data-tour="work-sessions-summary" className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{cards.map(([label, value, Icon]) => <div key={label} className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><Icon className="text-[#d99ae8]" size={19} /><p className="mt-5 text-2xl text-white">{value}</p><p className="mt-1 text-xs text-[#858a99]">{label}</p></div>)}</section>
                <section data-tour="work-sessions-table" className="mt-7 overflow-hidden rounded-xl border border-[#292d39] bg-[#11131c]/90"><div className="flex items-center justify-between border-b border-[#292d39] px-5 py-4"><div><h2 className="text-lg text-white">Historial de sesiones</h2><p className="mt-1 text-xs text-[#858a99]">Los tiempos son calculados con timestamps del servidor.</p></div><FiMonitor className="text-[#8bdcf0]" /></div><div className="overflow-x-auto">{sessions.length ? <table className="w-full min-w-[920px] text-left text-xs"><thead className="border-b border-[#292d39] text-[10px] uppercase tracking-[.14em] text-[#777d8f]"><tr><th className="px-5 py-3">Usuario</th><th className="px-5 py-3">Estación</th><th className="px-5 py-3">Inicio</th><th className="px-5 py-3">Estado</th><th className="px-5 py-3">Conectado</th><th className="px-5 py-3">Transmitiendo</th><th className="px-5 py-3">OBS</th></tr></thead><tbody className="divide-y divide-[#292d39]">{sessions.map((session) => <tr key={session.id} className="text-[#c8c2ce]"><td className="px-5 py-4"><p className="font-medium text-white">{session.user?.name || 'Usuario eliminado'}</p><p className="mt-1 text-[10px] text-[#777d8f]">{session.user?.email || '—'}</p></td><td className="px-5 py-4"><p>{session.station?.hostname || '—'}</p><p className="mt-1 text-[10px] text-[#777d8f]">{session.station?.studioId || '—'}</p></td><td className="px-5 py-4">{formatDate(session.startedAt)}</td><td className="px-5 py-4"><span className={`rounded-full border px-2.5 py-1 text-[10px] ${session.status === 'active' ? 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' : 'border-[#343044] bg-[#151522] text-[#aaa3b0]'}`}>{session.status}</span>{session.endReason && <p className="mt-2 text-[10px] text-[#777d8f]">{session.endReason}</p>}</td><td className="px-5 py-4 text-white">{formatDuration(session.totalConnectedSeconds)}</td><td className="px-5 py-4 text-white">{formatDuration(session.totalStreamingSeconds)}</td><td className="px-5 py-4">{session.lastObs?.streaming ? <span className="text-[#9af2cb]">Transmitiendo</span> : <span className="text-[#858a99]">En espera</span>}<p className="mt-1 text-[10px] text-[#777d8f]">{session.lastObs?.currentScene || 'Sin escena'}</p></td></tr>)}</tbody></table> : <div className="px-5 py-16 text-center"><FiClock className="mx-auto text-3xl text-[#4d5060]" /><p className="mt-3 text-sm text-[#858a99]">Todavía no hay sesiones de trabajo registradas.</p></div>}</div></section>
            </main>
        </Layout>
    </>;
}
