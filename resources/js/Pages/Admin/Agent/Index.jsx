import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FiActivity, FiCheckCircle, FiClipboard, FiClock, FiDatabase, FiKey, FiMonitor, FiRefreshCw, FiServer, FiShield, FiWifi, FiXCircle } from 'react-icons/fi';
import Layout from '../Recruitment/Layout';

const dateLabel = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Nunca';
const isExpired = (value) => value && new Date(value) <= new Date();
const bytes = (value) => value == null ? '—' : value >= 1024 ** 3 ? `${(value / 1024 ** 3).toFixed(1)} GB` : `${Math.round(value / 1024 ** 2)} MB`;

function ObsSummary({ station }) {
    const obs = station.obs;
    const metrics = station.metrics;
    if (!obs) return <div className="rounded-lg border border-dashed border-[#343044] px-3 py-3 text-[11px] text-[#777b8a]">Sin heartbeat recibido</div>;
    const state = obs.webSocketConnected && obs.processRunning ? ['OBS conectado', 'text-[#9af2cb]', FiCheckCircle] : obs.processRunning ? ['OBS detectado', 'text-[#f2d394]', FiClock] : ['OBS no detectado', 'text-[#ffb1bd]', FiXCircle];
    const StateIcon = state[2];

    return <div className="min-w-[300px] rounded-lg border border-[#292d39] bg-[#0d0f16] p-3"><div className="flex items-center justify-between gap-3"><span className={`inline-flex items-center gap-1.5 text-xs ${state[1]}`}><StateIcon size={13} />{state[0]}</span>{obs.streaming != null && <span className={`rounded-full px-2 py-1 text-[10px] ${obs.streaming ? 'bg-[#391d29] text-[#ffb1bd]' : 'bg-[#151f25] text-[#9daeb6]'}`}>{obs.streaming ? 'Transmitiendo' : 'Sin streaming'}</span>}</div><div className="mt-3 grid grid-cols-2 gap-2 text-[11px]"><div><p className="text-[#696d7c]">WebSocket</p><p className="mt-1 text-[#c8c2ce]">{obs.webSocketConnected ? 'Conectado' : 'Desconectado'}</p></div><div><p className="text-[#696d7c]">Escena actual</p><p className="mt-1 truncate text-[#c8c2ce]">{obs.currentScene || '—'}</p></div><div><p className="text-[#696d7c]">CPU</p><p className="mt-1 text-[#c8c2ce]">{metrics?.cpuPercent != null ? `${metrics.cpuPercent.toFixed(1)}%` : '—'}</p></div><div><p className="text-[#696d7c]">RAM usada</p><p className="mt-1 text-[#c8c2ce]">{metrics?.ramUsedPercent != null ? `${metrics.ramUsedPercent.toFixed(1)}%` : '—'}</p></div></div>{obs.error && <p className="mt-3 border-t border-[#292d39] pt-2 text-[10px] leading-4 text-[#ffb1bd]">{obs.error}</p>}{obs.scenes?.length > 0 && <p className="mt-2 text-[10px] text-[#777b8a]">{obs.scenes.length} escenas reportadas</p>}{metrics?.diskAvailableBytes != null && <p className="mt-1 text-[10px] text-[#777b8a]">Disco disponible: {bytes(metrics.diskAvailableBytes)}</p>}</div>;
}

function Stat({ icon: Icon, label, value, tone = 'purple' }) {
    const colors = { purple: 'bg-[#2b1735] text-[#df9bec]', green: 'bg-[#12372e] text-[#9af2cb]', amber: 'bg-[#3a2b18] text-[#f2d394]', red: 'bg-[#391d29] text-[#ffb1bd]' };
    return <div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><span className={`grid h-9 w-9 place-items-center rounded-lg ${colors[tone]}`}><Icon /></span><p className="mt-5 text-2xl text-white">{value}</p><p className="mt-1 text-xs text-[#858a99]">{label}</p></div>;
}

export default function Index({ stations = [], tokens = [], studios = [], masterUrl, enrollmentMinutes = 10 }) {
    const page = usePage();
    const [copied, setCopied] = useState(false);
    const form = useForm({ studioId: studios[0] || '' });
    const issuedToken = page.props.flash?.agentToken;
    const online = stations.filter((station) => !station.revokedAt && !station.offlineAt).length;
    const offline = stations.filter((station) => !station.revokedAt && station.offlineAt).length;
    const revoked = stations.filter((station) => station.revokedAt).length;

    useEffect(() => {
        const timer = window.setInterval(() => router.reload({ only: ['stations', 'tokens'], preserveScroll: true, preserveState: true }), 15000);
        return () => window.clearInterval(timer);
    }, []);

    const generateToken = (event) => {
        event.preventDefault();
        form.post('/admin/agent/enrollment-tokens/panel', { preserveScroll: true });
    };

    const copyToken = async () => {
        if (!issuedToken?.token) return;
        await navigator.clipboard.writeText(issuedToken.token);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return <>
        <Head title="VELVET Agent" />
        <Layout>
            <div className="mx-auto max-w-[1400px] px-5 py-6 lg:px-6 xl:px-8">
                <header className="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Integraciones</p><h1 className="mt-2 font-editorial text-4xl tracking-[-.04em] text-white sm:text-5xl">VELVET Agent</h1><p className="mt-2 max-w-3xl text-sm leading-6 text-[#969baa]">Administra el enrolamiento, la conexión y la configuración de las estaciones que reportan a Velvet Master.</p></div><div className="rounded-lg border border-[#343044] bg-[#11131c] px-4 py-3"><p className="text-[10px] uppercase tracking-[.16em] text-[#858a99]">Master URL</p><p className="mt-1 max-w-[280px] truncate font-mono text-xs text-[#cfa0d9]">{masterUrl || 'No configurada'}</p></div></header>

                <section data-tour="agent-overview" className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><Stat icon={FiServer} label="Estaciones registradas" value={stations.length} /><Stat icon={FiWifi} label="Conectadas" value={online} tone="green" /><Stat icon={FiActivity} label="Offline" value={offline} tone="amber" /><Stat icon={FiXCircle} label="Revocadas" value={revoked} tone="red" /></section>

                <section data-tour="agent-token" className="mt-6 grid gap-5 xl:grid-cols-[.85fr_1.15fr]"><form onSubmit={generateToken} className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-lg bg-[#2b1735] text-[#e09aee]"><FiKey /></span><div><h2 className="text-lg text-white">Token de enrolamiento</h2><p className="mt-1 text-xs text-[#858a99]">Genera o regenera una credencial temporal para una estación.</p></div></div><label className="mt-6 block text-xs text-[#b6b1bd]">Estudio<select value={form.data.studioId} onChange={(event) => form.setData('studioId', event.target.value)} className="mt-2 w-full rounded-lg border border-[#343044] bg-[#0d0f16] px-3 py-2.5 text-sm text-white" required>{studios.length ? studios.map((studio) => <option key={studio} value={studio}>{studio}</option>) : <option value="">Configura AGENT_STUDIO_IDS</option>}</select></label>{form.errors.studioId && <p className="mt-2 text-xs text-[#ff9eae]">{form.errors.studioId}</p>}<button type="submit" disabled={form.processing || !studios.length} className="velvet-button mt-5 w-full justify-center gap-2"><FiRefreshCw />{form.processing ? 'Generando...' : 'Generar / regenerar token'}</button><p className="mt-3 text-[10px] leading-4 text-[#727685]">Válido durante {enrollmentMinutes} minutos y de un solo uso. Regenerar crea un token independiente.</p></form>
                    <div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-lg bg-[#12372e] text-[#9af2cb]"><FiShield /></span><div><h2 className="text-lg text-white">Token disponible para copiar</h2><p className="mt-1 text-xs text-[#858a99]">Se muestra únicamente en la respuesta de esta generación.</p></div></div>{issuedToken ? <div className="mt-6 rounded-lg border border-[#2d8669] bg-[#0d2922] p-4"><div className="flex items-start justify-between gap-4"><div className="min-w-0"><p className="text-[10px] uppercase tracking-[.16em] text-[#79c5ac]">{issuedToken.studioId} · vence {dateLabel(issuedToken.expiresAt)}</p><p className="mt-3 break-all font-mono text-sm leading-6 text-[#d7f9ec]">{issuedToken.token}</p></div><button type="button" onClick={copyToken} className="shrink-0 rounded-lg border border-[#428d77] px-3 py-2 text-xs text-[#b8f2db]">{copied ? 'Copiado' : 'Copiar'}</button></div><p className="mt-4 border-t border-[#28604f] pt-3 text-[10px] leading-4 text-[#a6dcca]">Úsalo en <code>POST /api/agent/enroll</code> junto con installationId, hostname e Idempotency-Key.</p></div> : <div className="mt-6 grid min-h-32 place-items-center rounded-lg border border-dashed border-[#343044] text-center"><FiKey className="text-2xl text-[#555968]" /><p className="mt-2 text-xs text-[#858a99]">Todavía no hay un token generado en esta sesión.</p></div>}</div>
                </section>

                <section data-tour="agent-stations" className="mt-6 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center justify-between gap-4"><div><h2 className="text-lg text-white">Estaciones Agent</h2><p className="mt-1 text-xs text-[#858a99]">Conexiones, estado del equipo y diagnóstico de OBS reportado por cada estación.</p></div><span className="rounded-full border border-[#343044] px-3 py-1 text-[10px] text-[#9da1af]">{stations.length} estaciones</span></div>{stations.length ? <div className="mt-5 overflow-x-auto"><table className="w-full min-w-[1080px] text-left text-xs"><thead className="border-b border-[#292d39] text-[10px] uppercase tracking-wider text-[#777b8a]"><tr><th className="px-3 py-3">Estación</th><th className="px-3 py-3">Estudio</th><th className="px-3 py-3">Estado</th><th className="px-3 py-3">Última conexión</th><th className="px-3 py-3">Versión</th><th className="px-3 py-3">Heartbeat</th><th className="px-3 py-3"><span className="inline-flex items-center gap-1.5"><FiMonitor /> OBS y equipo</span></th></tr></thead><tbody className="divide-y divide-[#292d39]">{stations.map((station) => { const status = station.revokedAt ? ['Revocada', 'text-[#ffb1bd]', FiXCircle] : station.offlineAt ? ['Offline', 'text-[#f2d394]', FiClock] : ['Conectada', 'text-[#9af2cb]', FiCheckCircle]; const StatusIcon = status[2]; return <tr key={station.id}><td className="px-3 py-4 align-top"><p className="font-medium text-white">{station.hostname}</p><p className="mt-1 font-mono text-[10px] text-[#696d7c]">{station.installationId}</p></td><td className="px-3 py-4 align-top text-[#c8c2ce]">{station.studioId}</td><td className={`px-3 py-4 align-top ${status[1]}`}><span className="inline-flex items-center gap-1.5"><StatusIcon size={13} />{status[0]}</span></td><td className="px-3 py-4 align-top text-[#a8a3b0]">{dateLabel(station.lastSeenAt)}</td><td className="px-3 py-4 align-top font-mono text-[#a8a3b0]">{station.version || '—'}</td><td className="px-3 py-4 align-top text-[#a8a3b0]">{station.configuration?.heartbeatSeconds || '—'} s</td><td className="px-3 py-4 align-top"><ObsSummary station={station} /></td></tr>; })}</tbody></table></div> : <div className="py-14 text-center"><FiServer className="mx-auto text-3xl text-[#4d5060]" /><p className="mt-3 text-sm text-[#858a99]">No hay estaciones enroladas todavía.</p><p className="mt-1 text-xs text-[#656a79]">Genera un token y úsalo desde VELVET Agent para registrar la primera estación.</p></div>}</section>

                <section className="mt-6 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-lg bg-[#2b1735] text-[#e09aee]"><FiClipboard /></span><div><h2 className="text-lg text-white">Historial de tokens</h2><p className="mt-1 text-xs text-[#858a99]">Los valores de token no se almacenan ni se vuelven a mostrar después de su emisión.</p></div></div><div className="mt-5 divide-y divide-[#292d39]">{tokens.length ? tokens.map((token) => <div key={token.id} className="flex flex-col justify-between gap-2 py-3 text-xs sm:flex-row sm:items-center"><div><span className="text-white">{token.studioId}</span><span className="ml-3 text-[#858a99]">Emitido por {token.issuedBy || '—'}</span></div><span className={token.usedAt ? 'text-[#9af2cb]' : isExpired(token.expiresAt) ? 'text-[#ffb1bd]' : 'text-[#f2d394]'}>{token.usedAt ? `Usado ${dateLabel(token.usedAt)}` : isExpired(token.expiresAt) ? `Expirado ${dateLabel(token.expiresAt)}` : `Vence ${dateLabel(token.expiresAt)}`}</span></div>) : <p className="py-8 text-center text-xs text-[#858a99]">Aún no hay tokens emitidos.</p>}</div></section>
            </div>
        </Layout>
    </>;
}
