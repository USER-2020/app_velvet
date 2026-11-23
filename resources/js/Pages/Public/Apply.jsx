import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import {
    FiAlertCircle, FiArrowLeft, FiArrowRight, FiBriefcase, FiCheck, FiClock,
    FiFileText, FiGlobe, FiLogOut, FiLock, FiShield, FiStar, FiUser, FiUserCheck, FiUsers,
} from 'react-icons/fi';
import BrandMark from '@/Components/BrandMark';
import PrivacyPolicyModal from '@/Components/PrivacyPolicyModal';
import CountrySelect from '@/Components/CountrySelect';
import VelvetDatePicker from '@/Components/VelvetDatePicker';
import VelvetPhoneInput from '@/Components/VelvetPhoneInput';
import VelvetSelect from '@/Components/VelvetSelect';
import { Switch } from '@/Components/ui/switch';
import { colombianCities } from '@/lib/colombianCities';

const steps = [
    { label: 'Perfil', sublabel: 'Tu rol en Velvet', Icon: FiUser },
    { label: 'Datos', sublabel: 'Información personal', Icon: FiFileText },
    { label: 'Experiencia', sublabel: 'Tu recorrido', Icon: FiBriefcase },
    { label: 'Disponibilidad', sublabel: 'Tu ritmo de trabajo', Icon: FiClock },
    { label: 'Revisión', sublabel: 'Confirma y envía', Icon: FiCheck },
];
const progressKey = 'velvet-apply-progress';
const sources = ['Instagram', 'TikTok', 'Facebook', 'Google', 'Referido', 'Evento', 'Otro'];
const sexOptions = [
    { value: 'WOMAN', label: 'Mujer' }, { value: 'MAN', label: 'Hombre' },
];
const sexLabels = Object.fromEntries(sexOptions.map((option) => [option.value, option.label]));
const countryOptions = [
    { value: 'Colombia', code: 'CO', label: 'Colombia' },
    { value: 'Argentina', code: 'AR', label: 'Argentina' },
    { value: 'Brasil', code: 'BR', label: 'Brasil' },
    { value: 'Chile', code: 'CL', label: 'Chile' },
    { value: 'Ecuador', code: 'EC', label: 'Ecuador' },
    { value: 'España', code: 'ES', label: 'España' },
    { value: 'Estados Unidos', code: 'US', label: 'Estados Unidos' },
    { value: 'México', code: 'MX', label: 'México' },
    { value: 'Panamá', code: 'PA', label: 'Panamá' },
    { value: 'Perú', code: 'PE', label: 'Perú' },
    { value: 'Puerto Rico', code: 'PR', label: 'Puerto Rico' },
    { value: 'República Dominicana', code: 'DO', label: 'República Dominicana' },
    { value: 'Uruguay', code: 'UY', label: 'Uruguay' },
    { value: 'Venezuela', code: 'VE', label: 'Venezuela' },
];
const englishLevels = [
    { value: 'A1', label: 'A1 · Inicial' },
    { value: 'A2', label: 'A2 · Básico' },
    { value: 'B1', label: 'B1 · Intermedio' },
    { value: 'B2', label: 'B2 · Intermedio alto' },
    { value: 'C1', label: 'C1 · Avanzado' },
    { value: 'C2', label: 'C2 · Dominio avanzado' },
    { value: 'NATIVE', label: 'Nativo' },
];
const experienceYearsOptions = [
    { value: '1', label: '1 año' },
    { value: '2', label: '2 años' },
    { value: '3_PLUS', label: '3 años o más' },
];

const getStoredProgress = () => {
    if (typeof window === 'undefined') return { currentStep: 0, completedSteps: [] };
    try { return JSON.parse(window.localStorage.getItem(progressKey)) || { currentStep: 0, completedSteps: [] }; } catch { return { currentStep: 0, completedSteps: [] }; }
};

const fieldsByStep = [
    ['candidate_type'],
    ['first_name', 'last_name', 'sex', 'email', 'phone', 'country', 'city', 'birth_date'],
    ['experience', 'experience_years', 'speaks_english', 'english_level'],
    ['availability', 'work_mode', 'source'],
    ['data_consent'],
];

const stepForErrors = (errors = {}) => fieldsByStep.findIndex((fields) => fields.some((field) => errors[field]));

function Connector({ active, hidden }) {
    if (hidden) return <div className="apply-stepper__spacer" aria-hidden="true" />;
    return <div className="apply-stepper__connector" aria-hidden="true"><motion.span initial={false} animate={{ scaleX: active ? 1 : 0 }} transition={{ duration: .42, ease: [0.22, 1, 0.36, 1] }} /></div>;
}

function Stepper({ current, hasError }) {
    const completedSteps = new Set(getStoredProgress().completedSteps || []);
    return <nav className="apply-stepper" aria-label="Progreso de la aplicación">{steps.map(({ label, sublabel, Icon }, index) => {
        const completed = completedSteps.has(index) || index < current;
        const active = index === current;
        const navigable = completed || active;
        const icon = hasError && active ? <FiAlertCircle size={21} strokeWidth={1.9} /> : completed && !active ? <FiCheck size={21} strokeWidth={2.2} /> : <Icon size={active ? 22 : 20} strokeWidth={1.8} />;
        return <div key={label} className="apply-stepper__item"><div className="apply-stepper__row"><Connector hidden={index === 0} active={completedSteps.has(index - 1) || index < current} /><button type="button" disabled={!navigable} aria-current={active ? 'step' : undefined} aria-label={`${label}${completed ? ', completado' : active ? ', actual' : ', pendiente'}`} onClick={() => navigable && window.dispatchEvent(new CustomEvent('velvet:navigate-step', { detail: index }))} className={`apply-stepper__circle ${active ? 'is-active' : ''} ${completed ? 'is-completed' : ''} ${hasError && active ? 'is-error' : ''} ${navigable ? 'is-navigable' : ''}`}>{active && <span className="apply-stepper__badge">{index + 1}</span>}{icon}</button><Connector hidden={index === steps.length - 1} active={completed} /></div><span className={`apply-stepper__label ${active ? 'is-active' : ''}`}>{label}</span><span className="apply-stepper__sublabel">{sublabel}</span></div>;
    })}</nav>;
}

function TrustPanel() { return <aside className="apply-trust-panel"><span className="apply-trust-panel__icon"><FiShield size={23} strokeWidth={1.7} /></span><span><strong>Tu información está segura</strong><small>Usamos tus datos solo para el proceso de selección.</small></span></aside>; }

function BrandPanel() {
    return <aside className="apply-brand-panel"><div className="apply-brand-panel__image" aria-hidden="true"><img src="/onboarding/profile-model.webp" alt="" /><span /></div><Link href="/" className="apply-brand-panel__logo"><BrandMark className="h-auto w-full max-w-[19rem]" /></Link><div className="apply-brand-panel__copy"><p className="apply-brand-panel__eyebrow">Onboarding</p><h2>Tu historia<br />comienza<br /><em>aquí.</em></h2><span className="apply-brand-panel__rule" /><p className="apply-brand-panel__description">Nos encanta conocer<br />personas reales, con sueños<br />grandes y disciplina para<br />hacerlos realidad.</p></div><footer className="apply-brand-panel__footer"><p>“El talento adecuado<br />siempre encuentra su lugar.”</p><strong>THE VELVET STUDIO</strong><small>REAL PEOPLE. BIGGER STORIES.</small></footer></aside>;
}

export default function Apply({ type = 'MODEL', draft = null }) {
    const [step, setStep] = useState(() => getStoredProgress().currentStep || 0);
    const [error, setError] = useState('');
    const [privacyOpen, setPrivacyOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const form = useForm({ candidate_type: type, first_name: '', last_name: '', sex: '', phone: '', email: '', country: 'Colombia', city: 'Manizales', birth_date: '', experience: '', experience_years: '', speaks_english: false, english_level: '', availability: 'Tiempo completo', work_mode: 'En estudio', source: '', motivation: '', data_consent: false });
    const set = (name) => (event) => { form.clearErrors(name); form.setData(name, event.target.value); };

    useEffect(() => { if (draft) { const draftData = { ...form.data, ...draft.data, candidate_type: draft.candidate_type || form.data.candidate_type }; form.setData({ ...draftData, country: draftData.country || 'Colombia', city: draftData.city || 'Manizales' }); setStep(draft.current_step || 0); } }, [draft]);
    useEffect(() => { if (form.data.work_mode !== 'En estudio') form.setData('work_mode', 'En estudio'); }, [form.data.work_mode]);
    useEffect(() => { const navigate = (event) => { setError(''); setStep(event.detail); }; window.addEventListener('velvet:navigate-step', navigate); return () => window.removeEventListener('velvet:navigate-step', navigate); }, []);
    useEffect(() => {
        const invalidStep = stepForErrors(form.errors);
        if (invalidStep >= 0 && invalidStep !== step) setStep(invalidStep);
    }, [form.errors, step]);

    const validate = () => {
        const required = step === 1 ? ['first_name', 'last_name', 'sex', 'email', 'phone', 'country', 'city'] : step === 2 ? ['experience', ...(form.data.candidate_type === 'MONITOR' ? ['experience_years'] : [])] : step === 3 ? ['availability', 'work_mode', 'source'] : [];
        const missing = required.find((name) => !form.data[name]);
        if (missing) { setError('Completa los campos requeridos para continuar.'); return false; }
        if (step === 2 && !form.data.experience.trim()) { setError('Cuéntanos sobre tu experiencia para continuar.'); return false; }
        if (step === 2 && form.data.candidate_type === 'MONITOR' && !form.data.experience_years) { setError('Selecciona tus años de experiencia para continuar.'); return false; }
        if (step === 2 && form.data.speaks_english && !form.data.english_level) { setError('Selecciona tu nivel de inglés para continuar.'); return false; }
        if (step === 1 && !form.data.birth_date) { setError('Indica tu fecha de nacimiento para verificar la mayoría de edad.'); return false; }
        if (step === 3 && form.data.candidate_type === 'MONITOR' && form.data.work_mode !== 'En estudio') { setError('Los monitores trabajan únicamente en el estudio.'); return false; }
        setError(''); return true;
    };
    const next = async () => {
        if (!validate()) return;
        // Los errores del envío final no deben bloquear la navegación después
        // de que la persona haya corregido el campo afectado.
        form.clearErrors();
        setSaving(true);
        try {
            if (window.localStorage.getItem('velvet-cookie-consent') !== 'accepted') { await window.axios.post('/cookie-consent', { preferences: { essential: true, analytics: false, personalization: false } }); window.localStorage.setItem('velvet-cookie-consent', 'accepted'); window.dispatchEvent(new Event('velvet-cookie-consent-accepted')); }
            const nextStep = Math.min(step + 1, 4); const progress = getStoredProgress(); progress.currentStep = nextStep; progress.completedSteps = Array.from(new Set([...(progress.completedSteps || []), step])); window.localStorage.setItem(progressKey, JSON.stringify(progress)); await window.axios.post('/apply/draft', { candidate_type: form.data.candidate_type, current_step: nextStep, data: form.data }); setStep(nextStep);
        } catch {
            setError('No se pudo guardar este paso. Revisa tu conexión e inténtalo nuevamente.');
        } finally { setSaving(false); }
    };
    const submit = (event) => { event.preventDefault(); if (!form.data.data_consent) { setError('Debes aceptar la política de tratamiento de datos.'); return; } form.post('/apply', { onSuccess: () => { window.localStorage.removeItem(progressKey); window.axios.delete('/apply/draft'); }, onError: (errors) => { if (errors.email || errors.phone) { setStep(1); setError('Ya existe una aplicación o vinculación con los datos ingresados. Revisa el correo y el teléfono.'); } else if (errors.first_name || errors.last_name || errors.sex || errors.birth_date || errors.country || errors.city) { setStep(1); setError('Revisa los datos personales antes de enviar la solicitud.'); } else if (errors.experience || errors.experience_years || errors.speaks_english || errors.english_level) { setStep(2); setError(errors.experience || errors.experience_years || errors.english_level || 'Revisa la información de experiencia e inglés antes de enviar la solicitud.'); } else if (errors.availability || errors.work_mode || errors.source) { setStep(3); setError('Revisa la información de disponibilidad antes de enviar la solicitud.'); } else { setError('No se pudo enviar la solicitud. Revisa los campos e inténtalo nuevamente.'); } } }); };
    const textField = (name, label, props = {}) => { const fieldClass = { first_name: 'apply-first-name-field', last_name: 'apply-last-name-field', email: 'apply-email-field' }[name] || ''; const field = <label className={`apply-field-label ${fieldClass}`}>{label}<input {...props} value={form.data[name]} onChange={set(name)} className={`velvet-input ${form.errors[name] ? 'is-error' : ''}`} />{form.errors[name] && <span className="apply-field-error">{form.errors[name]}</span>}</label>; if (name !== 'last_name') return field; return <>{field}{form.data.candidate_type === 'MONITOR' && <VelvetDatePicker label="Fecha de nacimiento" value={form.data.birth_date} onChange={(event) => { form.clearErrors('birth_date'); form.setData('birth_date', event.target.value); }} error={form.errors.birth_date} />}</>; };
    const card = (value, title, copy, image) => <motion.button type="button" onClick={() => form.setData('candidate_type', value)} whileHover={{ y: -4 }} whileTap={{ scale: .98 }} className={`apply-profile-card ${form.data.candidate_type === value ? 'is-selected' : ''}`}><img src={image} alt="" /><span className="apply-profile-card__shade" /><span className="apply-profile-card__body"><strong>{title}</strong><small>{copy}</small><span className="apply-profile-card__check">{form.data.candidate_type === value ? <FiCheck size={14} /> : null}</span></span></motion.button>;
    const workModeOptions = ['En estudio'];
    const fullName = `${form.data.first_name} ${form.data.last_name}`.trim() || 'Tu nombre';
    const initials = fullName.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const englishSummary = form.data.speaks_english ? form.data.english_level || 'Nivel pendiente' : 'No';
    const review = [
        ['Perfil', form.data.candidate_type === 'MODEL' ? 'Modelo Webcam' : 'Monitor(a)'],
        ['Nombre', fullName],
        ['Sexo', sexLabels[form.data.sex] || '—'],
        ['Contacto', `${form.data.email || '—'} · ${form.data.phone || '—'}`],
        ['Ciudad', `${form.data.city || '—'} · ${form.data.country || '—'}`],
        ['Años de experiencia', form.data.candidate_type === 'MONITOR' ? (experienceYearsOptions.find((option) => option.value === form.data.experience_years)?.label || '—') : 'No aplica'],
        ['Disponibilidad', `${form.data.work_mode || '—'} · ${form.data.availability || '—'}`],
        ['Inglés', englishSummary],
        ['Fuente', form.data.source || '—'],
    ];
    const firstName = form.data.first_name?.trim() || 'tu';
    const title = ['Elige tu perfil', 'Datos personales', <><em className="apply-heading-name">{firstName}</em>, cuéntanos de tu experiencia </>, 'Tu disponibilidad', 'Revisa tu información'][step];
    const description = ['Podrás contarnos más en los siguientes pasos.', 'Solo necesitamos lo esencial para contactarte y estudiar tu aplicación.', 'La experiencia es obligatoria y debe ser demostrable.', 'Cuéntanos cómo te gustaría trabajar.', 'Verifica tus datos antes de enviar la aplicación.'][step];
    const experienceSection = <div className="apply-experience-section"><label className="apply-field-label">Cuéntanos brevemente sobre tu experiencia<textarea value={form.data.experience ?? ''} onChange={(event) => form.setData('experience', event.target.value)} rows="7" className={`velvet-input ${form.errors.experience ? 'is-error' : ''}`} placeholder="Cuéntanos sobre tu experiencia relacionada con el perfil." required />{form.errors.experience && <span className="apply-field-error">{form.errors.experience}</span>}<span className="mt-1 block text-[11px] text-[#7f8495]">La experiencia indicada debe ser demostrable.</span></label>{form.data.candidate_type === 'MONITOR' && <VelvetSelect label="Años de experiencia" value={form.data.experience_years} onChange={set('experience_years')} options={experienceYearsOptions} error={form.errors.experience_years} />}<div className="apply-english-row"><div className="apply-english-toggle"><span><strong>¿Hablas inglés?</strong><small>Indica si puedes comunicarte en inglés.</small></span><Switch checked={form.data.speaks_english} onCheckedChange={(checked) => { form.setData('speaks_english', checked); if (!checked) form.setData('english_level', ''); }} aria-label="¿Hablas inglés?" /></div>{form.data.speaks_english && <VelvetSelect label="Nivel de inglés" value={form.data.english_level} onChange={set('english_level')} options={englishLevels} error={form.errors.english_level} />}</div></div>;

    return <><Head title="Aplicar a Velvet" /><main className="apply-page"><BrandPanel /><section className="apply-main"><header className="apply-header"><Link href="/" className="apply-header__back"><FiArrowLeft size={14} /><span>Proceso de aplicación</span></Link><Link href="/" className="apply-mobile-bookmark" aria-label="Ir al inicio"><img src="/favicon.ico" alt="" /></Link><Link href="/" className="apply-header__exit">Salir del onboarding <FiLogOut size={16} /></Link></header><div className="apply-main__inner"><Stepper current={step} hasError={Boolean(error)} /><div className="apply-heading-row"><div><p className="apply-eyebrow">Paso {step + 1} de {steps.length}</p><h1>{title}</h1><p className="apply-description">{description}</p></div><TrustPanel /></div><form onSubmit={submit} className="apply-form"><AnimatePresence mode="wait" initial={false}><motion.div key={step} initial={{ opacity: 0, x: 22, y: 4 }} animate={{ opacity: 1, x: 0, y: 0 }} exit={{ opacity: 0, x: -22, y: -4 }} transition={{ duration: .32, ease: [0.22, 1, 0.36, 1] }}>{step === 0 && <div className="apply-profile-grid">{card('MODEL', 'Modelo Webcam', 'Construye tu perfil y comienza tu proceso con Velvet.', '/onboarding/modelo.webp')}{card('MONITOR', 'Monitor(a)', 'Acompaña y desarrolla el talento Velvet.', '/onboarding/profile-monitor.webp')}</div>}{step === 1 && <div className="apply-fields-grid">{textField('first_name', 'Nombre', { required: true, autoComplete: 'given-name' })}{textField('last_name', 'Apellido', { required: true, autoComplete: 'family-name' })}<VelvetSelect label="Sexo" value={form.data.sex} onChange={set('sex')} options={sexOptions} error={form.errors.sex} />{form.data.candidate_type === 'MODEL' ? <VelvetDatePicker label="Fecha de nacimiento" value={form.data.birth_date} onChange={set('birth_date')} error={form.errors.birth_date} /> : <div />}{textField('email', 'Email', { type: 'email', required: true, autoComplete: 'email' })}<label className="apply-field-label apply-phone-field">WhatsApp<VelvetPhoneInput value={form.data.phone} onChange={(value) => form.setData('phone', value)} error={form.errors.phone} /></label><VelvetSelect label="Ciudad" value={form.data.city} onChange={set('city')} options={colombianCities} error={form.errors.city} /><CountrySelect value={form.data.country} onChange={set('country')} options={countryOptions} error={form.errors.country} /></div>}{step === 2 && experienceSection}{step === 3 && <div className="apply-fields-grid"><VelvetSelect label="Modalidad preferida" value={form.data.work_mode} onChange={set('work_mode')} options={workModeOptions} /><VelvetSelect label="Disponibilidad horaria" value={form.data.availability} onChange={set('availability')} options={['Tiempo completo', 'Medio tiempo', 'Por horas']} /><div className="sm:col-span-2"><VelvetSelect label="¿Cómo conociste Velvet?" value={form.data.source} onChange={set('source')} options={sources} error={form.errors.source} /></div></div>}{step === 4 && <><div className="apply-review apply-review-desktop">{review.map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}</div><label className="apply-consent"><input id="data-consent" type="checkbox" checked={form.data.data_consent} onChange={(event) => form.setData('data_consent', event.target.checked)} /><span>Confirmo que la información es correcta y acepto la <a href="/politica-de-privacidad" target="_blank" rel="noreferrer">política de privacidad</a> y los <a href="/terminos-y-condiciones" target="_blank" rel="noreferrer">términos y condiciones</a>.</span></label></>}</motion.div></AnimatePresence>{error && <motion.p initial={{ opacity: 0, x: -3 }} animate={{ opacity: 1, x: 0 }} className="apply-form-error"><FiAlertCircle size={15} />{error}</motion.p>}<div className="apply-actions"><button type="button" onClick={() => { setError(''); setStep((value) => Math.max(0, value - 1)); }} disabled={step === 0} className="apply-back-button"><FiArrowLeft size={17} /><span>Volver</span></button><div className="apply-progress-dots" aria-label={`Paso ${step + 1} de ${steps.length}`}>{steps.map((item, index) => <span key={item.label} className={index === step ? 'is-active' : index < step ? 'is-complete' : ''} />)}</div>{step < 4 ? <button type="button" onClick={next} disabled={saving} className="apply-next-button">{saving ? 'Guardando…' : 'Continuar'}<FiArrowRight size={17} /></button> : <button type="submit" disabled={form.processing} className="apply-next-button">{form.processing ? 'Enviando…' : 'Enviar mi solicitud'}<FiArrowRight size={17} /></button>}</div></form><footer className="apply-benefits"><span><FiUserCheck size={21} /><strong>Verificación DIDIT</strong><small>Identidad biométrica segura</small></span><i /><span><FiGlobe size={21} /><strong>Geo-bloqueo y VPN</strong><small>Acceso protegido por ubicación</small></span><i /><span><FiLock size={21} /><strong>Proceso confiable</strong><small>Tu información protegida</small></span></footer></div></section></main><PrivacyPolicyModal open={privacyOpen} onOpenChange={setPrivacyOpen} /></>;
}


