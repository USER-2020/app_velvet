import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiCheck, FiSearch } from 'react-icons/fi';
import Layout from '../Recruitment/Layout';
import OperationsPageHeader from '@/Components/OperationsPageHeader';

const Field = ({ label, children, className = '' }) => <label className={`block text-xs font-medium text-[#c8c2ce] ${className}`}><span>{label}</span>{children}</label>;
const CardHeading = ({ title, description }) => <div><h2 className="text-base font-semibold text-white">{title}</h2><p className="mt-1 text-xs leading-5 text-[#858a99]">{description}</p></div>;

export default function Index({ platforms = [], models = [] }) {
    const platform = useForm({ code: '', name: '' });
    const account = useForm({ platform_catalog_id: '', name: '', legal_holder: '', payment_receiver: '', connection_mode: 'MANUAL', credential_token: '', credentials: {} });
    const link = useForm({ studio_account_id: '', candidate_id: '', account_identifier: '', ownership_type: 'STUDIO', is_authorized: true, notes: '' });
    const importForm = useForm({ studio_account_id: '', original_name: 'reporte-manual.csv', content: '' });
    const chaturbate = useForm({ username: '', token: '', stats_url: '', events_url: '' });
    const [identitySearch, setIdentitySearch] = useState(null);
    const [nicknameValidation, setNicknameValidation] = useState(null);
    const submit = (event, form, url) => { event.preventDefault(); form.post(url, { preserveScroll: true, onSuccess: () => form.reset() }); };
    const saveAccount = (event) => { event.preventDefault(); account.transform(({ credential_token, ...data }) => ({ ...data, credentials: credential_token ? { token: credential_token } : {} })).post('/admin/platforms/accounts', { preserveScroll: true, onSuccess: () => account.reset() }); };
    const accounts = platforms.flatMap((item) => item.accounts.map((accountItem) => ({ ...accountItem, platform: item.name })));
    const chaturbateAccounts = accounts.filter((item) => item.provider_key === 'chaturbate' || item.platform.toLowerCase() === 'chaturbate');
    const pendingChaturbateAccounts = chaturbateAccounts.filter((item) => item.status !== 'CONNECTED');
    const connectChaturbate = (event, accountItem) => { event.preventDefault(); chaturbate.post(`/admin/platforms/accounts/${accountItem.id}/chaturbate/connect`, { preserveScroll: true, onSuccess: () => chaturbate.reset() }); };
    const selectedLinkedAccount = accounts.find((item) => String(item.id) === String(link.data.studio_account_id));
    const camGirlFinderRequest = async (label, url, payload, retried = false) => {
        const requestId = `cgf-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const startedAt = performance.now();
        const safePayload = { ...payload, nickname: payload.nickname ? '[redacted-from-log]' : undefined };

        console.groupCollapsed(`[CamGirlFinder] ${label} · ${requestId}`);
        console.info('Request', { url, payload: safePayload });
        console.groupEnd();

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json().catch(() => ({ message: 'La respuesta no contiene JSON válido.' }));
            const result = {
                requestId,
                httpStatus: response.status,
                ok: response.ok,
                durationMs: Math.round(performance.now() - startedAt),
                body,
            };

            if (response.status === 419 && !retried) {
                console.warn('[CamGirlFinder] CSRF token expired. Refreshing token and retrying once.', result);
                const csrfResponse = await fetch('/csrf-token', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const csrfBody = await csrfResponse.json().catch(() => ({}));
                if (csrfBody.token) {
                    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', csrfBody.token);
                    return camGirlFinderRequest(label, url, payload, true);
                }
            }

            if (response.ok) {
                console.info(`[CamGirlFinder] ${label} response`, result);
            } else {
                console.error(`[CamGirlFinder] ${label} failed`, result);
            }

            return body;
        } catch (error) {
            console.error(`[CamGirlFinder] ${label} network error`, {
                requestId,
                durationMs: Math.round(performance.now() - startedAt),
                message: error?.message,
                error,
            });
            return { provider: 'camgirlfinder', status: 'pending', message: 'No fue posible contactar el backend.' };
        }
    };
    const discoverIdentity = async () => {
        if (!link.data.candidate_id) return;
        setIdentitySearch({ status: 'pending', message: 'Buscando coincidencias…', matches: [] });
        const result = await camGirlFinderRequest('identity discovery', '/admin/platforms/accounts/camgirlfinder/discover', { candidate_id: link.data.candidate_id });
        setIdentitySearch(result);
    };
    const validateNickname = async () => {
        if (!link.data.account_identifier || !selectedLinkedAccount) return;
        setNicknameValidation({ status: 'pending', message: 'Validando nickname…' });
        const result = await camGirlFinderRequest('nickname validation', '/admin/platforms/accounts/camgirlfinder/validate-nickname', { nickname: link.data.account_identifier, platform: selectedLinkedAccount.platform });
        setNicknameValidation(result);
    };
    const submitLink = (event) => { event.preventDefault(); if (!nicknameValidation || nicknameValidation.status === 'found') return; link.post(`/admin/platforms/accounts/${link.data.studio_account_id}/models`, { preserveScroll: true, onSuccess: () => { link.reset(); setIdentitySearch(null); setNicknameValidation(null); } }); };
    const LoadingDots = () => <span role="status" aria-label="Validando" className="inline-flex gap-1 align-middle"><i className="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-.2s]" /><i className="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-.1s]" /><i className="h-1.5 w-1.5 animate-bounce rounded-full bg-current" /></span>;

    return <><Head title="Plataformas Studio" /><Layout><main className="mx-auto max-w-[1440px] space-y-6">
        <OperationsPageHeader eyebrow="Operación de plataformas" title="Cuentas Studio y conexiones seguras" description="Administra catálogos, cuentas y asociaciones manuales. Ninguna conexión real se activa sin credenciales oficiales y un adaptador aprobado." />
        <section data-tour="platform-safety" className="rounded-xl border border-[#80508c] bg-[#25172d] px-4 py-3 text-sm leading-6 text-[#e7b2f1]">Estado seguro: secretos cifrados, registros manuales y conexiones oficiales controladas. Las credenciales nunca se muestran en la interfaz.</section>

        <section className="grid items-start gap-5 md:grid-cols-2 xl:grid-cols-3">
            <form data-tour="platform-catalog" onSubmit={(event) => submit(event, platform, '/admin/platforms')} className="flex min-h-[276px] flex-col rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 sm:p-6"><CardHeading title="Catálogo de plataformas" description="Crea una referencia administrativa, sin conectar proveedores." /><div className="mt-5 space-y-4"><Field label="Código"><input required value={platform.data.code} onChange={(event) => platform.setData('code', event.target.value)} className="velvet-input mt-1.5" placeholder="Ej. chaturbate" /></Field><Field label="Nombre visible"><input required value={platform.data.name} onChange={(event) => platform.setData('name', event.target.value)} className="velvet-input mt-1.5" placeholder="Nombre interno" /></Field></div><button disabled={platform.processing} className="velvet-button mt-auto self-start pt-4">Agregar plataforma</button></form>
            <form onSubmit={saveAccount} className="flex min-h-[448px] flex-col rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 sm:p-6"><CardHeading title="Registrar cuenta Studio" description="La información sensible se guarda cifrada." /><div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-1"><Field label="Plataforma"><select required value={account.data.platform_catalog_id} onChange={(event) => account.setData('platform_catalog_id', event.target.value)} className="velvet-input mt-1.5"><option value="">Selecciona</option>{platforms.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field><Field label="Nombre interno"><input required value={account.data.name} onChange={(event) => account.setData('name', event.target.value)} className="velvet-input mt-1.5" /></Field><Field label="Modo"><select value={account.data.connection_mode} onChange={(event) => account.setData('connection_mode', event.target.value)} className="velvet-input mt-1.5"><option value="MANUAL">Manual</option><option value="SIMULATED">Simulado</option></select></Field><Field label="Titular legal"><input value={account.data.legal_holder} onChange={(event) => account.setData('legal_holder', event.target.value)} className="velvet-input mt-1.5" /></Field><Field label="Receptor de pagos"><input value={account.data.payment_receiver} onChange={(event) => account.setData('payment_receiver', event.target.value)} className="velvet-input mt-1.5" /></Field><Field label="Token autorizado (opcional)"><input type="password" autoComplete="new-password" value={account.data.credential_token} onChange={(event) => account.setData('credential_token', event.target.value)} className="velvet-input mt-1.5" placeholder="Se cifra al guardar" /></Field></div><button disabled={account.processing} className="velvet-button mt-auto self-start pt-4">Guardar cuenta</button></form>
            <form onSubmit={submitLink} className="flex min-h-[448px] flex-col rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 sm:p-6 md:col-span-2 xl:col-span-1">
                <CardHeading title="Validar y vincular cuenta" description="Busca primero la identidad con Didit y valida el nuevo nickname antes de asociarlo." />
                <div className="mt-7 grid gap-7 sm:grid-cols-2 xl:grid-cols-1">
                    <Field label="Cuenta Studio"><select required value={link.data.studio_account_id} onChange={(event) => { link.setData('studio_account_id', event.target.value); setNicknameValidation(null); }} className="velvet-input mt-2"><option value="">Selecciona</option>{accounts.map((item) => <option key={item.id} value={item.id}>{item.platform} · {item.name}</option>)}</select></Field>
                    <Field label="Modelo activa"><select required value={link.data.candidate_id} onChange={(event) => { link.setData('candidate_id', event.target.value); setIdentitySearch(null); }} className="velvet-input mt-2"><option value="">Selecciona</option>{models.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></Field>
                    <button type="button" onClick={discoverIdentity} disabled={!link.data.candidate_id || identitySearch?.status === 'pending'} className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#80508c] px-3 py-2 text-xs text-[#e5a5f2]"><FiSearch />Buscar coincidencias con foto de Didit</button>
                    {identitySearch && <div className="rounded-lg border border-[#343044] bg-[#151522] p-3 text-[11px] text-[#aaa2b2]">{identitySearch.matches?.length ? identitySearch.matches.slice(0, 4).map((match, index) => <p key={`${match.platform}-${index}`} className="py-1 text-white">{match.model || match.name} · {match.platform}</p>) : identitySearch.status === 'pending' ? <span className="inline-flex items-center gap-2 text-[#e5a5f2]">Buscando coincidencias <LoadingDots /></span> : 'Sin coincidencias preventivas.'}</div>}
                    <Field label="Nuevo nickname" className="sm:col-span-2 xl:col-span-1"><input required value={link.data.account_identifier} onChange={(event) => { link.setData('account_identifier', event.target.value); setNicknameValidation(null); }} className="velvet-input mt-2" placeholder="Alias o identificador manual" /></Field>
                    <button type="button" onClick={validateNickname} disabled={!link.data.account_identifier || !selectedLinkedAccount || nicknameValidation?.status === 'pending'} className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#80508c] px-3 py-2 text-xs text-[#e5a5f2]"><FiCheck />Validar nickname preventivamente</button>
                    {nicknameValidation && <p className={`rounded-md border px-3 py-2 text-[11px] ${nicknameValidation.status === 'found' ? 'border-[#75405d] bg-[#321622] text-[#ffb1bd]' : nicknameValidation.status === 'not_found' ? 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' : 'border-[#755d2a] bg-[#3b3017] text-[#f5d88c]'}`}>{nicknameValidation.status === 'found' ? 'El nickname ya aparece registrado.' : nicknameValidation.status === 'not_found' ? 'No aparece en CamGirlFinder; continúa con confirmación oficial.' : <span className="inline-flex items-center gap-2">Validando nickname <LoadingDots /></span>}</p>}
                </div>
                <div className="mt-6 border-t border-[#292d39] pt-5">
                    <button disabled={link.processing || !nicknameValidation || nicknameValidation.status === 'found' || nicknameValidation.status === 'pending'} className="velvet-button self-start">Vincular cuenta</button>
                </div>
            </form>
        </section>

        <section data-tour="platform-accounts" className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 sm:p-6"><CardHeading title="Cuentas registradas" description="Estado y modo de cada cuenta administrada." /><div className="velvet-scrollbar mt-5 grid max-h-[420px] gap-3 overflow-y-auto pr-1 lg:grid-cols-2 xl:grid-cols-3">{accounts.length ? accounts.map((item) => <article key={item.id} className="rounded-lg border border-[#343044] bg-[#151522] p-4"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-medium text-[#d56bea]">{item.platform}</p><p className="mt-1 text-sm font-medium text-white">{item.name}</p></div><span className="rounded-full border border-[#5e3b69] px-2 py-1 text-[10px] text-[#e7b2f1]">{item.connection_mode}</span></div><p className="mt-3 text-xs leading-5 text-[#858a99]">Estado: {item.status}. Titular: {item.legal_holder || 'Pendiente'}.</p>{item.last_synced_at && <p className="mt-1 text-xs text-[#858a99]">Última sincronización: {new Date(item.last_synced_at).toLocaleString()}</p>}{item.last_error && <p className="mt-2 text-xs text-[#ffb1bd]">{item.last_error}</p>}{item.connection_mode === 'SIMULATED' && <button type="button" onClick={() => router.post(`/admin/platforms/accounts/${item.id}/simulate-sync`, {}, { preserveScroll: true })} className="mt-3 text-xs font-medium text-[#9af2cb]">Ejecutar simulación segura</button>}{item.provider_key === 'chaturbate' && item.connection_mode === 'LIVE' && <button type="button" onClick={() => router.post(`/admin/platforms/accounts/${item.id}/chaturbate/sync`, {}, { preserveScroll: true })} className="mt-3 block text-xs font-medium text-[#9af2cb]">Sincronizar Chaturbate</button>}</article>) : <p className="rounded-lg border border-dashed border-[#343044] px-4 py-12 text-center text-sm text-[#858a99] lg:col-span-2 xl:col-span-3">Aún no hay cuentas Studio.</p>}</div></section>
        {pendingChaturbateAccounts.length > 0 && <section data-tour="chaturbate-connect" className="rounded-xl border border-[#80508c] bg-[#21152a] p-5 sm:p-6"><CardHeading title="Conectar Chaturbate" description="Pega la URL completa generada en Chaturbate o completa usuario y token. La master valida Stats API y guarda las credenciales cifradas." /><p className="mt-2 text-xs text-[#e7b2f1]">Cuenta seleccionada: {pendingChaturbateAccounts[0].name}. Para repetir esta guía abre <a className="underline" href="/admin/platforms?integration=chaturbate">este enlace</a>.</p><form onSubmit={(event) => connectChaturbate(event, pendingChaturbateAccounts[0])} className="mt-5 grid gap-4 md:grid-cols-2"><Field label="URL de Stats API"><input type="url" value={chaturbate.data.stats_url} onChange={(event) => chaturbate.setData('stats_url', event.target.value)} className="velvet-input mt-1.5" placeholder="https://chaturbate.com/statsapi/?username=...&token=..." /></Field><Field label="URL de Events API (opcional)"><input type="url" value={chaturbate.data.events_url} onChange={(event) => chaturbate.setData('events_url', event.target.value)} className="velvet-input mt-1.5" placeholder="https://eventsapi.chaturbate.com/events/..." /></Field><Field label="Usuario (si no usas URL)"><input value={chaturbate.data.username} onChange={(event) => chaturbate.setData('username', event.target.value)} className="velvet-input mt-1.5" /></Field><Field label="Token (si no usas URL)"><input type="password" autoComplete="new-password" value={chaturbate.data.token} onChange={(event) => chaturbate.setData('token', event.target.value)} className="velvet-input mt-1.5" /></Field><button disabled={chaturbate.processing} className="velvet-button md:col-span-2 md:w-fit">Verificar y conectar</button></form></section>}
        <section data-tour="platform-import" className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5 sm:p-6"><div className="grid gap-5 xl:grid-cols-[minmax(0,.7fr)_minmax(0,1.3fr)]"><CardHeading title="Importar reporte manual" description="Registra un CSV oficial sin enviar información a un servicio externo." /><form onSubmit={(event) => submit(event, importForm, `/admin/platforms/accounts/${importForm.data.studio_account_id}/import`)} className="grid gap-4 sm:grid-cols-2"><Field label="Cuenta Studio"><select required value={importForm.data.studio_account_id} onChange={(event) => importForm.setData('studio_account_id', event.target.value)} className="velvet-input mt-1.5"><option value="">Selecciona una cuenta</option>{accounts.map((item) => <option key={item.id} value={item.id}>{item.platform} · {item.name}</option>)}</select></Field><Field label="Nombre del archivo"><input value={importForm.data.original_name} onChange={(event) => importForm.setData('original_name', event.target.value)} className="velvet-input mt-1.5" /></Field><Field label="Contenido CSV" className="sm:col-span-2"><textarea required rows="5" value={importForm.data.content} onChange={(event) => importForm.setData('content', event.target.value)} className="velvet-input mt-1.5 font-mono text-xs" placeholder={'fecha,metrica,valor\n2026-10-12,ejemplo,1'} /></Field><button disabled={importForm.processing} className="velvet-button w-full sm:col-span-2 sm:w-fit">Registrar importación</button></form></div></section>
    </main></Layout></>;
}
