import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FiArrowRight, FiCamera, FiCheckCircle, FiFileText, FiLock, FiRefreshCw, FiShield } from 'react-icons/fi';
import BrandMark from '../../Components/BrandMark';

const typeLabels = { MODEL: 'Modelo Webcam', MONITOR: 'Monitor(a)' };

const identityStatusLabels = {
    NOT_STARTED: 'Sin iniciar',
    IN_PROGRESS: 'En progreso',
    IN_REVIEW: 'En revisión manual',
    APPROVED: 'Identidad verificada',
    DECLINED: 'No aprobada',
    EXPIRED: 'Sesión vencida',
    ABANDONED: 'No completada',
};

function FieldError({ message }) {
    return message ? <span className="mt-1 block text-xs text-[#ff91a4]">{message}</span> : null;
}

export default function Prequalification({ candidate, actionUrl, identitySessionUrl, identityVerification = {} }) {
    const initial = candidate.initial || {};
    const draftStorageKey = candidate.id ? `velvet.prequalification.draft.${candidate.id}` : null;
    const storedDraft = (() => {
        if (!draftStorageKey || typeof window === 'undefined') return {};

        try {
            const stored = JSON.parse(window.localStorage.getItem(draftStorageKey) || 'null');
            if (!stored || Date.now() - Number(stored.savedAt) > 7 * 24 * 60 * 60 * 1000) {
                window.localStorage.removeItem(draftStorageKey);
                return {};
            }

            return stored.data || {};
        } catch {
            return {};
        }
    })();
    const [identityStatus, setIdentityStatus] = useState(identityVerification.status || 'NOT_STARTED');
    const [identityError, setIdentityError] = useState('');
    const [consentError, setConsentError] = useState('');
    const [startingIdentity, setStartingIdentity] = useState(false);
    const form = useForm({
        availability: storedDraft.availability || initial.availability || '',
        work_mode: storedDraft.work_mode || initial.work_mode || (candidate.type === 'MONITOR' ? 'En estudio' : ''),
        experience: storedDraft.experience || initial.experience || '',
        motivation: storedDraft.motivation || initial.motivation || '',
        social_networks: storedDraft.social_networks || '',
        identity_document: null,
        accept_terms: false,
    });
    // Mantener las mismas opciones del onboarding y conservar valores de
    // aplicaciones antiguas para que el select nunca oculte un dato guardado.
    const workModeOptions = candidate.type === 'MONITOR' ? ['En estudio'] : ['Desde casa', 'En estudio', 'Presencial', 'Híbrido'];
    const workModes = [...new Set([...(form.data.work_mode ? [form.data.work_mode] : []), ...workModeOptions])];
    const identityEnabled = Boolean(identityVerification.enabled);
    const identityApproved = identityStatus === 'APPROVED';
    const canRestartIdentity = ['IN_REVIEW', 'DECLINED', 'EXPIRED', 'ABANDONED'].includes(identityStatus);

    useEffect(() => {
        setIdentityStatus(identityVerification.status || 'NOT_STARTED');
    }, [identityVerification.status]);

    useEffect(() => {
        if (!draftStorageKey || typeof window === 'undefined') return;

        const { availability, work_mode, experience, motivation, social_networks } = form.data;
        try {
            window.localStorage.setItem(draftStorageKey, JSON.stringify({
                savedAt: Date.now(),
                data: { availability, work_mode, experience, motivation, social_networks },
            }));
        } catch {
            // El formulario sigue funcionando aunque el navegador bloquee el almacenamiento local.
        }
    }, [draftStorageKey, form.data.availability, form.data.work_mode, form.data.experience, form.data.motivation, form.data.social_networks]);

    const startIdentityVerification = async (restart = false) => {
        setIdentityError('');
        setStartingIdentity(true);
        try {
            const response = await window.axios.post(identitySessionUrl, restart ? { restart: true } : {});
            setIdentityStatus(response.data.status || 'IN_PROGRESS');
            if (response.data.url) {
                // Didit devuelve al callback configurado al terminar; navegar en la
                // pestaña actual evita acumular ventanas de verificación abiertas.
                window.location.assign(response.data.url);
            }
        } catch (error) {
            setIdentityError(error.response?.data?.message || 'No se pudo iniciar la verificación. Inténtalo nuevamente.');
        } finally {
            setStartingIdentity(false);
        }
    };

    const refreshIdentityStatus = async () => {
        setIdentityError('');
        try {
            const response = await window.axios.post(identitySessionUrl, { sync: true });
            setIdentityStatus(response.data.status || 'NOT_STARTED');
            router.reload({
                only: ['identityVerification'],
                preserveScroll: true,
                onSuccess: (page) => setIdentityStatus(page.props.identityVerification?.status || 'NOT_STARTED'),
            });
        } catch (error) {
            setIdentityError(error.response?.data?.message || 'No se pudo actualizar el estado. Inténtalo nuevamente.');
        }
    };

    const submit = (event) => {
        event.preventDefault();
        if (!form.data.accept_terms) {
            setConsentError('Debes confirmar que la información es verdadera y aceptar la política de privacidad y los términos y condiciones para continuar.');
            return;
        }
        setConsentError('');
        form.post(actionUrl, {
            forceFormData: true,
            onSuccess: () => {
                if (draftStorageKey && typeof window !== 'undefined') window.localStorage.removeItem(draftStorageKey);
            },
        });
    };

    return <>
        <Head title="Requisitos iniciales" />
        <main className="min-h-screen bg-[#090a10] px-4 py-8 text-[#f7f1fb] sm:px-6 lg:py-12">
            <div className="mx-auto max-w-3xl">
                <header className="flex items-center justify-between border-b border-[#292936] pb-6">
                    <BrandMark className="h-9 w-auto" />
                    <span className="text-right text-[10px] uppercase tracking-[.22em] text-[#9d8fa5]">Proceso de selección</span>
                </header>
                <section className="mt-10">
                    <p className="text-xs font-semibold uppercase tracking-[.28em] text-[#d56bea]">Siguiente paso</p>
                    <div className="mt-3 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div><h1 className="font-editorial text-4xl text-white sm:text-5xl">Requisitos iniciales</h1><p className="mt-3 max-w-2xl text-sm leading-6 text-[#a9a4b1]">Hola, {candidate.name}. Completa esta información para que nuestro equipo pueda revisar tu perfil y continuar con la precalificación.</p></div>
                        <span className="w-fit rounded-full border border-[#9142a7]/50 bg-[#3d164d] px-3 py-1.5 text-xs text-[#f0c1fa]">{typeLabels[candidate.type] || 'Candidata'}</span>
                    </div>
                </section>
                <form onSubmit={submit} className="mt-8 rounded-2xl border border-[#292d39] bg-[#11131c]/90 p-5 shadow-[0_18px_55px_rgba(0,0,0,.2)] sm:p-8">
                    <div className="flex items-start gap-3 border-b border-[#292d39] pb-6"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiFileText size={18} /></span><div><h2 className="text-sm font-medium text-white">Cuéntanos un poco más</h2><p className="mt-1 text-xs leading-5 text-[#7f8495]">La información se usará únicamente para evaluar tu aplicación.</p></div></div>
                    <div className="mt-7 grid gap-6 sm:grid-cols-2">
                        <label className="block text-xs text-[#d8d3dc]">Disponibilidad horaria<select value={form.data.availability} onChange={(event) => form.setData('availability', event.target.value)} className="velvet-input" required><option value="">Selecciona una opción</option><option>Tiempo completo</option><option>Medio tiempo</option><option>Por horas</option></select><span className="mt-1 block text-[11px] text-[#7f8495]">Prellenada desde tu onboarding.</span><FieldError message={form.errors.availability} /></label>
                        <label className="block text-xs text-[#d8d3dc]">Modalidad preferida<select value={form.data.work_mode} onChange={(event) => form.setData('work_mode', event.target.value)} className="velvet-input" required><option value="">Selecciona una opción</option>{workModes.map((mode) => <option key={mode}>{mode}</option>)}</select><span className="mt-1 block text-[11px] text-[#7f8495]">Prellenada desde tu onboarding.</span><FieldError message={form.errors.work_mode} /></label>
                        <label className="block text-xs text-[#d8d3dc] sm:col-span-2">Experiencia relevante<textarea value={form.data.experience ?? ''} onChange={(event) => form.setData('experience', event.target.value)} rows="5" className="velvet-input" placeholder="Cuéntanos sobre tu experiencia relacionada con este rol." required /><span className="mt-1 block text-[11px] text-[#7f8495]">Tomada de tu aplicación inicial. Puedes actualizarla si tienes información adicional.</span><FieldError message={form.errors.experience} /></label>
                        <label className="block text-xs text-[#d8d3dc] sm:col-span-2">¿Por qué quieres trabajar con Velvet?<textarea value={form.data.motivation} onChange={(event) => form.setData('motivation', event.target.value)} rows="4" className="velvet-input" placeholder="Comparte tus motivaciones y expectativas." required /><FieldError message={form.errors.motivation} /></label>
                        <label className="block text-xs text-[#d8d3dc]">Redes sociales <span className="text-[#7f8495]">(opcional)</span><input type="text" value={form.data.social_networks} onChange={(event) => form.setData('social_networks', event.target.value)} className="velvet-input" placeholder="Instagram, TikTok, Facebook..." /><FieldError message={form.errors.social_networks} /></label>
                        <section className="rounded-xl border border-[#343044] bg-[#151522] p-4 sm:col-span-2" aria-labelledby="identity-verification-title"><div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiShield size={17} /></span><div className="min-w-0"><h3 id="identity-verification-title" className="text-sm font-medium text-[#f2edf5]">Verificación de identidad</h3><p className="mt-1 text-xs leading-5 text-[#969baa]">Tomaremos una foto de tu documento y una selfie para confirmar que es auténtico y que eres mayor de edad.</p></div></div>{identityEnabled ? <><div className="mt-4 flex flex-wrap items-center gap-3"><span className={`rounded-full border px-3 py-1 text-[11px] ${identityApproved ? 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' : identityStatus === 'DECLINED' ? 'border-[#7d3144] bg-[#321622] text-[#ffb1bd]' : 'border-[#5e3b69] bg-[#2b1735] text-[#e7b9f1]'}`}>{identityStatusLabels[identityStatus] || identityStatus}</span>{identityApproved && <span className="inline-flex items-center gap-1 text-[11px] text-[#9af2cb]"><FiCheckCircle /> Lista para enviar</span>}</div>{identityVerification.data?.failure_reason && !identityApproved && <p className="mt-3 rounded-lg border border-[#624b2b] bg-[#2b2115] px-3 py-2 text-xs leading-5 text-[#f2cf91]">{identityVerification.data.failure_reason}</p>}{!identityApproved && <div className="mt-4 flex flex-col gap-3 sm:flex-row"><button type="button" onClick={() => startIdentityVerification(canRestartIdentity)} disabled={startingIdentity} className="velvet-button w-full gap-2 sm:w-auto"><FiCamera size={15} />{startingIdentity ? 'Iniciando…' : canRestartIdentity ? 'Iniciar nueva verificación' : identityStatus === 'IN_PROGRESS' ? 'Continuar verificación' : 'Iniciar verificación'}</button>{identityStatus === 'IN_PROGRESS' && <button type="button" onClick={() => startIdentityVerification(true)} disabled={startingIdentity} className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf] transition hover:border-[#8c43a0] hover:text-white"><FiRefreshCw size={14} />Reiniciar verificación</button>}{(identityStatus === 'IN_PROGRESS' || identityStatus === 'IN_REVIEW') && <button type="button" onClick={refreshIdentityStatus} className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#343044] px-4 py-2 text-xs text-[#c9c2cf] transition hover:border-[#8c43a0] hover:text-white"><FiRefreshCw size={14} />Actualizar estado</button>}</div>}</> : <p className="mt-4 rounded-lg border border-[#51445a] bg-[#11121a] px-3 py-2 text-xs leading-5 text-[#c3bdc9]">La verificación automática aún no está configurada. Mientras tanto, carga una imagen o PDF de tu documento para revisión manual.</p>}{identityError && <p className="mt-3 rounded-md border border-[#7d3144] bg-[#321622] px-3 py-2 text-xs text-[#ffb1bd]">{identityError}</p>}<FieldError message={form.errors.identity_verification} /></section>
                        {!identityEnabled && <label className="block text-xs text-[#d8d3dc] sm:col-span-2">Documento de identidad<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(event) => form.setData('identity_document', event.target.files?.[0] || null)} className="velvet-input file:mr-3 file:rounded-md file:border-0 file:bg-[#3d164d] file:px-3 file:py-2 file:text-xs file:text-[#f0c1fa]" required /><span className="mt-1 block text-[11px] text-[#7f8495]">JPG, PNG o PDF. Máximo 5 MB.</span><FieldError message={form.errors.identity_document} /></label>}
                    </div>
                    <label className="mt-7 flex items-start gap-3 border-t border-[#292d39] pt-6 text-xs leading-5 text-[#aaa3b0]"><input type="checkbox" checked={form.data.accept_terms} onChange={(event) => { form.setData('accept_terms', event.target.checked); if (event.target.checked) setConsentError(''); }} aria-required="true" aria-invalid={Boolean(consentError || form.errors.accept_terms)} className="mt-1 rounded border-[#51445a] bg-[#11121a] text-[#c23bea] focus:ring-[#c23bea]" /><span>Confirmo que la información es verdadera y autorizo a The Velvet Studio a usarla para evaluar mi aplicación. He leído la <a href="/politica-de-privacidad" target="_blank" rel="noreferrer" className="text-[#e5a1f2] underline">política de privacidad</a> y los <a href="/terminos-y-condiciones" target="_blank" rel="noreferrer" className="text-[#e5a1f2] underline">términos y condiciones</a>.</span></label><FieldError message={consentError || form.errors.accept_terms} />
                    {form.errors.prequalification && <p className="mt-5 rounded-lg border border-[#7d3144] bg-[#321622] px-4 py-3 text-xs text-[#ffb1bd]">{form.errors.prequalification}</p>}
                    <div className="mt-8 flex flex-col-reverse items-center justify-between gap-4 border-t border-[#292d39] pt-6 sm:flex-row"><p className="flex items-center gap-2 text-[11px] text-[#7f8495]"><FiLock /> Tus datos se mantienen protegidos.</p><button type="submit" disabled={form.processing || (identityEnabled && !identityApproved)} className="velvet-button w-full gap-2 sm:w-auto">{form.processing ? 'Enviando…' : identityEnabled && !identityApproved ? 'Verifica tu identidad' : 'Enviar requisitos'}<FiArrowRight size={16} /></button></div>
                </form>
            </div>
        </main>
    </>;
}

