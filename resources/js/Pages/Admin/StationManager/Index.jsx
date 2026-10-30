import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { FiCheckCircle, FiInfo, FiLink, FiShield, FiUsers, FiXCircle } from 'react-icons/fi';
import VelvetDateTimeField from '@/Components/VelvetDateTimeField';
import Layout from '../Recruitment/Layout';
import { subscribeToRealtime } from '@/lib/ably';
import { useRealtimeFallback } from '@/lib/useRealtimeFallback';

const dateLabel = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin fecha';
const tokenStatus = (token) => token.usedAt ? ['Usado', 'text-[#9af2cb]'] : new Date(token.expiresAt) <= new Date() ? ['Vencido', 'text-[#ffb1bd]'] : ['Disponible', 'text-[#f2d394]'];

export default function Index({ stations = [], rooms = [], models = [], monitors = [], studios = [], enrollmentCodes = [] }) {
    const page = usePage();
    const [copiedCode, setCopiedCode] = useState(false);
    const [realtimeUnavailable, setRealtimeUnavailable] = useState(!import.meta.env.VITE_ABLY_ENABLED);
    const form = useForm({ station_id: '', room_id: '', model_candidate_id: '', monitor_candidate_id: '', starts_at: '', ends_at: '', notes: '' });
    const codeForm = useForm({ studio_id: studios[0] || '' });
    const launcherEnrollmentCode = page.props.flash?.launcherEnrollmentCode;
    const reload = useCallback(() => router.reload({ only: ['stations', 'enrollmentCodes'], preserveScroll: true, preserveState: true }), []);

    useEffect(() => subscribeToRealtime((_data, eventName) => {
        if (eventName?.startsWith('station-manager.')) reload();
    }, null, setRealtimeUnavailable), [reload]);
    useRealtimeFallback(reload, realtimeUnavailable);

    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/station-manager/assignments', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const copyCode = async () => {
        if (!launcherEnrollmentCode?.code) return;
        await navigator.clipboard.writeText(launcherEnrollmentCode.code);
        setCopiedCode(true);
        window.setTimeout(() => setCopiedCode(false), 1800);
    };

    return <>
        <Head title="Station Manager" />
        <Layout>
            <div className="mx-auto max-w-[1400px] px-5 py-8 lg:px-6 xl:px-8">
                <header className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div>
                        <p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Operación</p>
                        <h1 className="mt-2 font-editorial text-4xl tracking-[-.04em] text-white sm:text-5xl">Station Manager</h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-[#969baa]">Asocia estaciones técnicas del Launcher con rooms y personas autorizadas. VELVET Agent queda aislado como integración futura.</p>
                    </div>
                    <span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#2d8669] bg-[#12372e] px-4 py-2 text-xs font-medium text-[#9af2cb]"><FiCheckCircle /> Control de asignaciones</span>
                </header>

                <section data-tour="station-manager-enrollment" className="mt-8 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-lg bg-[#2b1735] text-[#df9bec]"><FiLink /></span><div><h2 className="text-lg text-white">Preparar enrolamiento de Launcher</h2><p className="mt-1 text-xs text-[#858a99]">Genera un código temporal para registrar una estación macOS o Windows.</p></div></div><form onSubmit={(event) => { event.preventDefault(); codeForm.post('/admin/station-manager/enrollment-codes', { preserveScroll: true }); }} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><label className="block flex-1 text-xs text-[#b6b1bd]">Estudio<select value={codeForm.data.studio_id} onChange={(event) => codeForm.setData('studio_id', event.target.value)} className="mt-2 w-full rounded-lg border border-[#343044] bg-[#0d0f16] px-3 py-2.5 text-sm text-white" required><option value="">Selecciona</option>{studios.map((studio) => <option key={studio} value={studio}>{studio}</option>)}</select></label><button type="submit" disabled={codeForm.processing || !studios.length} className="velvet-button justify-center">{codeForm.processing ? 'Generando...' : 'Generar código'}</button></form>{launcherEnrollmentCode && <div className="mt-4 rounded-lg border border-[#2d8669] bg-[#0d2922] p-4"><div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p className="text-[10px] uppercase tracking-[.16em] text-[#79c5ac]">{launcherEnrollmentCode.studioId} · vence {dateLabel(launcherEnrollmentCode.expiresAt)}</p><p className="mt-2 break-all font-mono text-sm text-[#d7f9ec]">{launcherEnrollmentCode.code}</p></div><button type="button" onClick={copyCode} className="rounded-lg border border-[#428d77] px-3 py-2 text-xs text-[#b8f2db]">{copiedCode ? 'Copiado' : 'Copiar código'}</button></div><p className="mt-2 text-[10px] text-[#a6dcca]">Cópialo en VELVET Launcher. Solo se puede usar una vez.</p></div>}</section>

                <section data-tour="station-manager-assignment" className="mt-8 grid gap-5 xl:grid-cols-[.8fr_1.2fr]">
                    <form onSubmit={submit} className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6">
                        <div className="flex items-center gap-3"><span className="grid h-9 w-9 place-items-center rounded-lg bg-[#2b1735] text-[#df9bec]"><FiLink /></span><div><h2 className="text-lg text-white">Nueva asignación</h2><p className="mt-1 text-xs text-[#858a99]">Master validará estación, room, modelo, monitor y solapamientos.</p></div></div>
                        <div className="mt-6 space-y-4">
                            {[
                                ['station_id', 'Estación', stations.filter((station) => !station.revokedAt).map((station) => ({ value: station.id, label: station.hostname + ' · ' + station.studioId }))],
                                ['room_id', 'Room', rooms.map((room) => ({ value: room.id, label: room.code + ' · ' + room.name }))],
                                ['model_candidate_id', 'Modelo', models.map((model) => ({ value: model.id, label: model.name }))],
                                ['monitor_candidate_id', 'Monitor (opcional)', monitors.map((monitor) => ({ value: monitor.id, label: monitor.name }))],
                            ].map(([name, label, options]) => <label key={name} className="block text-xs text-[#b6b1bd]">{label}<select value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} className="mt-2 w-full rounded-lg border border-[#343044] bg-[#0d0f16] px-3 py-2.5 text-sm text-white" required={name !== 'monitor_candidate_id'}><option value="">Selecciona</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>{form.errors[name] && <span className="mt-1 block text-xs text-[#ff9eae]">{form.errors[name]}</span>}</label>)}
                            <div className="grid gap-4"><VelvetDateTimeField label="Inicio" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} error={form.errors.starts_at} timeStep={15} /><VelvetDateTimeField label="Fin (opcional)" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} error={form.errors.ends_at} timeStep={15} /></div>
                            <label className="block text-xs text-[#b6b1bd]">Notas<textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} rows="2" className="mt-2 w-full rounded-lg border border-[#343044] bg-[#0d0f16] px-3 py-2.5 text-sm text-white" /></label>
                        </div>
                        <button type="submit" disabled={form.processing} className="velvet-button mt-5 w-full justify-center">{form.processing ? 'Validando...' : 'Crear asignación'}</button>
                        <p className="mt-3 flex items-start gap-2 text-[10px] leading-4 text-[#727685]"><FiShield className="mt-0.5 shrink-0 text-[#9af2cb]" />No se envían comandos a la estación. Esta fase solo controla la asignación operativa.</p>
                    </form>

                    <section data-tour="station-manager-stations" className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center justify-between gap-4"><div><h2 className="text-lg text-white">Estaciones y asignaciones</h2><p className="mt-1 text-xs text-[#858a99]">La asignación activa es la fuente de verdad operativa de Master.</p></div><span className="rounded-full border border-[#343044] px-3 py-1 text-[10px]">{stations.length} estaciones</span></div>{stations.length ? <div className="mt-5 space-y-3">{stations.map((station) => <article key={station.id} className="rounded-lg border border-[#292d39] bg-[#0d0f16] p-4"><div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><p className="font-medium text-white">{station.hostname}</p><p className="mt-1 font-mono text-[10px] text-[#696d7c]">{station.installationId} · {station.studioId}</p></div><span className={station.revokedAt ? 'inline-flex w-fit items-center gap-1.5 rounded-full bg-[#391d29] px-2.5 py-1 text-[10px] text-[#ffb1bd]' : station.assignment ? 'inline-flex w-fit items-center gap-1.5 rounded-full bg-[#12372e] px-2.5 py-1 text-[10px] text-[#9af2cb]' : 'inline-flex w-fit items-center gap-1.5 rounded-full bg-[#3a2b18] px-2.5 py-1 text-[10px] text-[#f2d394]'}>{station.revokedAt ? <><FiXCircle /> Revocada</> : station.assignment ? <><FiCheckCircle /> Asignada</> : <><FiInfo /> Sin asignación</>}</span></div>{station.assignment ? <div className="mt-4 grid gap-3 border-t border-[#292d39] pt-3 text-xs sm:grid-cols-3"><div><p className="text-[#696d7c]">Room</p><p className="mt-1 text-[#c8c2ce]">{station.assignment.room?.name || '—'}</p></div><div><p className="text-[#696d7c]">Modelo</p><p className="mt-1 text-[#c8c2ce]">{station.assignment.model || '—'}</p></div><div><p className="text-[#696d7c]">Monitor</p><p className="mt-1 text-[#c8c2ce]">{station.assignment.monitor || 'Sin asignar'}</p></div><p className="text-[10px] text-[#777b8a] sm:col-span-3">Desde {dateLabel(station.assignment.startsAt)}</p></div> : <p className="mt-4 border-t border-[#292d39] pt-3 text-xs text-[#858a99]">Esta estación todavía no tiene una asignación operativa.</p>}</article>)}</div> : <div className="py-16 text-center"><FiUsers className="mx-auto text-3xl text-[#4d5060]" /><p className="mt-3 text-sm text-[#858a99]">No hay estaciones enroladas.</p><p className="mt-1 text-xs text-[#656a79]">Las estaciones se registran desde VELVET Launcher cuando la integración técnica esté habilitada.</p></div>}</section>
                </section>

                <section data-tour="station-manager-enrollment-history" className="mt-6 rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6"><div className="flex items-center justify-between gap-4"><div><h2 className="text-lg text-white">Historial de códigos de enrolamiento</h2><p className="mt-1 text-xs text-[#858a99]">Los códigos no se vuelven a mostrar; solo se conserva su trazabilidad y vencimiento.</p></div><span className="rounded-full border border-[#343044] px-3 py-1 text-[10px] text-[#9da1af]">{enrollmentCodes.length} registros</span></div>{enrollmentCodes.length ? <div className="mt-5 divide-y divide-[#292d39]">{enrollmentCodes.map((token) => { const status = tokenStatus(token); return <div key={token.id} className="flex flex-col justify-between gap-2 py-3 text-xs sm:flex-row sm:items-center"><div><span className="text-white">{token.studioId}</span><span className="ml-3 text-[#858a99]">Generado por {token.issuedBy || '—'}</span></div><div className="flex gap-4"><span className={status[1]}>{status[0]}</span><span className="text-[#858a99]">{token.usedAt ? 'Usado ' + dateLabel(token.usedAt) : 'Vence ' + dateLabel(token.expiresAt)}</span></div></div>; })}</div> : <p className="py-8 text-center text-xs text-[#858a99]">Aún no hay códigos generados.</p>}</section>
            </div>
        </Layout>
    </>;
}
