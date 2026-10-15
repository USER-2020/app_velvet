import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster, toast } from 'react-hot-toast';
import { useEffect, useState } from 'react';
import ProductTour from '@/Components/ProductTour';

function renderInertiaPage({ Component, props, key }) {
    const child = <Component key={key} {...props} />;
    let page = child;

    if (typeof Component.layout === 'function') page = Component.layout(child);
    if (Array.isArray(Component.layout)) page = Component.layout.concat(child).reverse().reduce((children, Layout) => <Layout {...props}>{children}</Layout>);

    return <>{page}<ProductTour /></>;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const isPublicPath = (pathname) => pathname === '/'
    || pathname === '/login'
    || pathname === '/apply'
    || pathname === '/politica-de-privacidad'
    || pathname === '/terminos-y-condiciones'
    || pathname.startsWith('/prequalification/')
    || pathname.startsWith('/forgot-password')
    || pathname.startsWith('/reset-password')
    || pathname.startsWith('/verify-email')
    || pathname.startsWith('/confirm-password');

const pathFromVisit = (visit) => new URL(visit?.url || window.location.href, window.location.origin).pathname;
const isPublicGetVisit = (visit) => String(visit?.method || 'get').toLowerCase() === 'get'
    && isPublicPath(pathFromVisit(visit));

function PublicRouteOverlay() {
    const [active, setActive] = useState(() => isPublicPath(window.location.pathname));
    const [fading, setFading] = useState(false);

    useEffect(() => {
        let fadeTimer;
        let removeTimer;

        const clearTimers = () => {
            window.clearTimeout(fadeTimer);
            window.clearTimeout(removeTimer);
        };

        const schedule = () => {
            clearTimers();
            setActive(true);
            setFading(false);
            fadeTimer = window.setTimeout(() => {
                setFading(true);
                window.dispatchEvent(new Event('velvet-intro-overlay-complete'));
                removeTimer = window.setTimeout(() => setActive(false), 1400);
            }, 3200);
        };

        const onStart = (event) => {
            // Un POST a /login o /logout conserva esa URL en el objeto de visita,
            // aunque Laravel responda con una redirección al dashboard. Solo las
            // navegaciones GET hacia una ruta pública deben mostrar este overlay.
            const publicRoute = isPublicGetVisit(event.detail?.visit);
            window.dispatchEvent(new CustomEvent('velvet-public-route-changed', { detail: { publicRoute } }));
            clearTimers();
            setFading(false);
            setActive(publicRoute);
        };

        const onFinish = (event) => {
            // La URL del navegador ya corresponde al destino final de un redirect
            // de Inertia; evita dejar el login visible tras autenticar otro usuario.
            const publicRoute = isPublicGetVisit(event.detail?.visit)
                && isPublicPath(window.location.pathname);
            if (publicRoute) schedule();
            else {
                clearTimers();
                setFading(false);
                setActive(false);
            }
        };

        const removeStartListener = router.on('start', onStart);
        const removeFinishListener = router.on('finish', onFinish);
        if (isPublicPath(window.location.pathname)) schedule();

        return () => {
            clearTimers();
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    if (!active) return null;

    return <div className={`apply-intro-overlay ${fading ? 'apply-intro-overlay--fading' : ''}`} role="status" aria-label="Cargando aplicación"><div className="apply-intro-overlay__content"><img src="/LOGO_OVERLAY.svg" alt="The Velvet Studio" className="apply-intro-overlay__logo" /><div className="apply-intro-overlay__progress-track"><div className="apply-intro-overlay__progress-bar" /></div></div></div>;
}

function ModuleSkeleton({ path, block }) {
    const rows = (count = 6) => Array.from({ length: count }).map((_, index) => <div key={index} className="flex gap-4 border-t border-[#242833] p-4">{block('h-8 w-8')}{block('h-4 flex-1')}{block('h-4 w-24')}{block('h-4 w-20')}</div>);
    const metricCards = (count = 3) => <div className="mt-7 grid gap-3 sm:grid-cols-3">{Array.from({ length: count }).map((_, index) => <div key={index} className="rounded-xl border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-4')}{block('mt-5 h-7 w-16')}{block('mt-2 h-3 w-28')}</div>)}</div>;
    const isNotificationHistory = path.includes('/notifications/history');
    const isCandidateDetail = /\/candidates\/\d+$/.test(path);
    const isLeadDetail = /\/leads\/\d+$/.test(path);

    if (isCandidateDetail || isLeadDetail) return <div className="admin-page-content"><div className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div className="space-y-3">{block('h-3 w-48')}{block('h-10 w-96')}{block('h-3 w-32')}</div><div className="flex gap-3">{block('h-8 w-24')}{block('h-9 w-28')}</div></div><div className="mt-7 grid gap-5 xl:grid-cols-[1.35fr_.65fr]"><section className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-48')}{block('mt-3 h-3 w-64')}{block('mt-8 h-56 w-full')}{block('mt-5 h-28 w-full')}</section><aside className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-40')}{block('mt-5 h-40 w-full')}{block('mt-5 h-24 w-full')}</aside></div><section className="mt-6 rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-48')}{block('mt-5 h-40 w-full')}</section></div>;
    if (isNotificationHistory) return <div className="admin-page-content"><div className="space-y-3">{block('h-3 w-32')}{block('h-10 w-80')}{block('h-3 w-[30rem]')}</div><div className="mt-7 flex gap-2">{block('h-9 w-20')}{block('h-9 w-28')}{block('h-9 w-32')}</div><section className="mt-6 overflow-hidden rounded-xl border border-[#242833] bg-[#10121a]">{rows(8)}</section></div>;
    if (path.endsWith('/leads')) return <div className="admin-page-content"><div className="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-48')}{block('h-3 w-96')}</div><div className="flex gap-2">{block('h-10 w-72')}{block('h-10 w-28')}</div></div>{metricCards(4)}<section className="mt-6 overflow-hidden rounded-xl border border-[#242833] bg-[#10121a]">{rows(7)}</section></div>;
    if (path.endsWith('/candidates')) return <div className="admin-page-content"><div className="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div className="space-y-3">{block('h-3 w-28')}{block('h-10 w-64')}{block('h-3 w-96')}</div><div className="flex gap-2">{block('h-10 w-72')}{block('h-10 w-28')}</div></div>{metricCards(3)}<div className="mt-6 grid gap-4 xl:grid-cols-2">{Array.from({ length: 6 }).map((_, index) => <article key={index} className="rounded-xl border border-[#242833] bg-[#10121a] p-5">{block('h-9 w-9')}{block('mt-4 h-4 w-48')}{block('mt-3 h-3 w-64')}{block('mt-5 h-2 w-full')}</article>)}</div></div>;
    if (path.endsWith('/interviews')) return <div className="admin-page-content"><div className="flex flex-col justify-between gap-4 md:flex-row md:items-end"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-56')}{block('h-3 w-96')}</div>{block('h-9 w-44')}</div><section className="mt-7 rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-48')}{block('mt-4 h-10 w-full')}{rows(6)}</section><section className="mt-6 rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-40')}{rows(4)}</section></div>;
    if (path.endsWith('/calendar')) return <div className="admin-page-content"><div className="flex justify-between gap-4">{block('h-10 w-56')}{block('h-10 w-36')}</div><div className="mt-7 grid gap-5 xl:grid-cols-[1.5fr_.7fr]"><section className="rounded-xl border border-[#242833] bg-[#10121a] p-5">{block('h-8 w-full')}{block('mt-5 h-[28rem] w-full')}</section><aside className="rounded-xl border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-40')}{rows(5)}</aside></div></div>;
    if (path.endsWith('/processes')) return <div className="admin-page-content"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-64')}{block('h-3 w-[30rem]')}</div><div className="mt-7 flex justify-end gap-2">{block('h-10 w-80')}{block('h-10 w-32')}{block('h-10 w-32')}</div>{metricCards(3)}<div className="mt-6 space-y-4">{Array.from({ length: 3 }).map((_, index) => <div key={index} className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-64')}{block('mt-5 h-2 w-full')}{block('mt-2 h-2 w-full')}{block('mt-5 ml-auto h-9 w-24')}</div>)}</div></div>;
    if (path.endsWith('/validations')) return <div className="admin-page-content"><div className="flex justify-between gap-4">{block('h-10 w-64')}{block('h-10 w-72')}</div>{metricCards(4)}<section className="mt-6 overflow-hidden rounded-xl border border-[#242833] bg-[#10121a]">{rows(6)}</section></div>;
    if (path.endsWith('/users')) return <div className="admin-page-content"><div className="flex justify-between gap-4">{block('h-10 w-56')}{block('h-10 w-32')}</div><section className="mt-7 overflow-hidden rounded-xl border border-[#242833] bg-[#10121a]">{rows(8)}</section></div>;
    if (path.endsWith('/access')) return <div className="admin-page-content"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-64')}{block('h-3 w-96')}</div><div className="mt-7 grid gap-5 xl:grid-cols-[.7fr_1.3fr]"><aside className="rounded-xl border border-[#242833] bg-[#10121a] p-5">{rows(5)}</aside><section className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-5 w-48')}{rows(7)}</section></div></div>;
    if (path.endsWith('/workflows')) return <div className="admin-page-content"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-64')}{block('h-3 w-96')}</div><div className="mt-7 grid gap-5 xl:grid-cols-2">{Array.from({ length: 2 }).map((_, index) => <section key={index} className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-5 w-48')}{block('mt-3 h-3 w-72')}{rows(5)}</section>)}</div></div>;
    return <div className="admin-page-content"><div className="space-y-3">{block('h-3 w-24')}{block('h-10 w-64')}</div>{metricCards(6)}<section className="mt-6 grid gap-5 xl:grid-cols-[1.55fr_.85fr]"><div className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-48')}{block('mt-6 h-56 w-full')}</div><div className="rounded-xl border border-[#242833] bg-[#10121a] p-6">{block('h-4 w-40')}{block('mx-auto mt-8 h-40 w-40 rounded-full')}</div></section></div>;
}
function DashboardRouteSkeleton() {
    const [visible, setVisible] = useState(false);
    const [targetPath, setTargetPath] = useState(() => window.location.pathname);

    useEffect(() => {
        const isDashboardPath = (pathname) => pathname === '/dashboard' || pathname.startsWith('/admin');
        const removeStartListener = router.on('start', (event) => {
            // Partial reloads (for example, realtime updates) must not replace
            // the visible page with the full route skeleton.
            if (event.detail?.visit?.only?.length) return;
            const pathname = pathFromVisit(event.detail?.visit);
            setTargetPath(pathname);
            setVisible(isDashboardPath(pathname));
        });
        const removeFinishListener = router.on('finish', () => setVisible(false));
        return () => { removeStartListener(); removeFinishListener(); };
    }, []);

    if (!visible) return null;
    const block = (className = '') => <span className={`block animate-pulse rounded-md bg-[#1a1d28] ${className}`} />;
    return <div className="dashboard-route-skeleton" aria-busy="true" aria-label="Cargando módulo"><ModuleSkeleton path={targetPath} block={block} /></div>;
}

function GlobalCrudToasts() {
    useEffect(() => {
        const requestToastId = 'velvet-crud-request';
        const isCrudMutation = (visit) => {
            const method = String(visit?.method || 'get').toLowerCase();
            const pathname = new URL(visit?.url || window.location.href, window.location.origin).pathname;

            // Solo las operaciones de escritura del panel administrativo son CRUD.
            // Login, logout, onboarding y navegación no deben mostrar estos avisos.
            return method !== 'get' && pathname.startsWith('/admin/');
        };
        const firstError = (errors) => Object.values(errors || {}).flat().find(Boolean);
        let mutationInFlight = false;

        const onStart = (event) => {
            if (isCrudMutation(event.detail?.visit)) {
                mutationInFlight = true;
                toast.loading('Procesando…', { id: requestToastId });
            }
        };
        const onSuccess = (event) => {
            if (!mutationInFlight) return;
            const flash = event.detail?.page?.props?.flash || {};
            mutationInFlight = false;
            toast.success(flash.success || 'Cambios guardados correctamente.', { id: requestToastId });
        };
        const onError = (event) => {
            if (!mutationInFlight) return;
            const message = firstError(event.detail?.errors) || 'No se pudo completar la operación.';
            mutationInFlight = false;
            toast.error(message, { id: requestToastId });
        };
        const onException = () => {
            if (!mutationInFlight) return;
            mutationInFlight = false;
            toast.error('Ocurrió un error inesperado. Inténtalo de nuevo.', { id: requestToastId });
        };
        const onFinish = (event) => {
            if (mutationInFlight && (event.detail?.visit?.cancelled || event.detail?.visit?.interrupted)) {
                mutationInFlight = false;
                toast.dismiss(requestToastId);
            }
        };
        const onInvalid = (event) => {
            if (event.detail?.response?.status === 419) {
                event.preventDefault();
                window.location.reload();
            }
        };

        const removeStart = router.on('start', onStart);
        const removeSuccess = router.on('success', onSuccess);
        const removeError = router.on('error', onError);
        const removeException = router.on('exception', onException);
        const removeFinish = router.on('finish', onFinish);
        const removeInvalid = router.on('invalid', onInvalid);

        return () => { removeStart(); removeSuccess(); removeError(); removeException(); removeFinish(); removeInvalid(); };
    }, []);

    return <Toaster position="top-center" reverseOrder={false} gutter={10} toastOptions={{ duration: 4200, className: 'velvet-toast', success: { className: 'velvet-toast velvet-toast--success' }, error: { className: 'velvet-toast velvet-toast--error' }, loading: { className: 'velvet-toast velvet-toast--loading' } }} />;
}

function CookieConsentBanner() {
    const [visible, setVisible] = useState(false);
    const [preferencesOpen, setPreferencesOpen] = useState(false);
    const [preferences, setPreferences] = useState({ essential: true, analytics: false, personalization: false });

    useEffect(() => {
        const accepted = () => setVisible(false);
        const show = () => { if (isPublicPath(window.location.pathname) && window.localStorage.getItem('velvet-cookie-consent') !== 'accepted') setVisible(true); };
        const routeChanged = (event) => { if (!event.detail?.publicRoute) setVisible(false); };
        if (isPublicPath(window.location.pathname) && window.location.pathname !== '/apply') show();
        window.addEventListener('velvet-intro-overlay-complete', show);
        window.addEventListener('velvet-cookie-consent-accepted', accepted);
        window.addEventListener('velvet-public-route-changed', routeChanged);
        return () => { window.removeEventListener('velvet-intro-overlay-complete', show); window.removeEventListener('velvet-cookie-consent-accepted', accepted); window.removeEventListener('velvet-public-route-changed', routeChanged); };
    }, []);

    const save = async (nextPreferences) => {
        window.localStorage.setItem('velvet-cookie-consent', 'accepted');
        setVisible(false);
        setPreferencesOpen(false);
        await window.axios.post('/cookie-consent', { preferences: nextPreferences });
    };

    const acceptAll = () => save({ essential: true, analytics: true, personalization: true });
    const rejectOptional = () => save({ essential: true, analytics: false, personalization: false });

    if (!visible) return null;
    return <><div className="cookie-consent-backdrop" aria-hidden="true" /><aside className="cookie-consent-banner" role="dialog" aria-modal="true" aria-label="Consentimiento de cookies"><div><p className="cookie-consent-banner__title">Tu privacidad importa</p><p className="cookie-consent-banner__copy">Configura tus preferencias para continuar. Las cookies esenciales permiten guardar tu aplicación.</p></div><div className="cookie-consent-banner__actions"><button type="button" onClick={() => window.dispatchEvent(new Event('velvet-open-cookie-preferences'))} className="cookie-consent-banner__link">Configurar cookies</button><button type="button" onClick={() => save({ essential: true, analytics: true, personalization: true })} className="velvet-button cookie-consent-banner__button">Aceptar todas</button></div></aside></>;
}

function CookiePreferencesEntry() {
    const [open, setOpen] = useState(false);
    const [preferences, setPreferences] = useState({ essential: true, analytics: false, personalization: false });
    const [publicRoute, setPublicRoute] = useState(() => isPublicPath(window.location.pathname));

    useEffect(() => {
        const openModal = () => setOpen(true);
        const routeChanged = (event) => { setPublicRoute(Boolean(event.detail?.publicRoute)); if (!event.detail?.publicRoute) setOpen(false); };
        window.addEventListener('velvet-open-cookie-preferences', openModal);
        window.addEventListener('velvet-public-route-changed', routeChanged);
        return () => { window.removeEventListener('velvet-open-cookie-preferences', openModal); window.removeEventListener('velvet-public-route-changed', routeChanged); };
    }, []);

    if (!publicRoute) return null;
    const save = async (nextPreferences) => {
        window.localStorage.setItem('velvet-cookie-consent', 'accepted');
        setOpen(false);
        await window.axios.post('/cookie-consent', { preferences: nextPreferences });
    };

    return <>{!open && <button type="button" onClick={() => setOpen(true)} className="cookie-preferences-entry">Configurar cookies</button>}{open && <div className="cookie-consent-modal__backdrop" role="presentation"><section className="cookie-consent-modal" role="dialog" aria-modal="true" aria-labelledby="cookie-preferences-title"><div className="flex items-start justify-between gap-4"><div><p id="cookie-preferences-title" className="cookie-consent-modal__title">Personaliza tus cookies</p><p className="cookie-consent-modal__copy">Las cookies esenciales permiten guardar tu aplicación. Las demás son opcionales.</p></div><button type="button" onClick={() => setOpen(false)} className="cookie-consent-modal__close" aria-label="Cerrar">×</button></div><div className="cookie-consent-modal__options"><label className="cookie-consent-modal__option"><span><strong>Esenciales</strong><small>Seguridad, sesión y recuperación del formulario.</small></span><input type="checkbox" checked disabled /></label><label className="cookie-consent-modal__option"><span><strong>Analítica</strong><small>Ayuda a entender el uso general del sitio.</small></span><input type="checkbox" checked={preferences.analytics} onChange={(event) => setPreferences({ ...preferences, analytics: event.target.checked })} /></label><label className="cookie-consent-modal__option"><span><strong>Personalización</strong><small>Recuerda preferencias para mejorar la experiencia.</small></span><input type="checkbox" checked={preferences.personalization} onChange={(event) => setPreferences({ ...preferences, personalization: event.target.checked })} /></label></div><div className="cookie-consent-modal__actions"><button type="button" onClick={() => save(preferences)} className="velvet-button">Guardar preferencias</button></div></section></div>}</>;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<><App {...props}>{renderInertiaPage}</App><GlobalCrudToasts /><PublicRouteOverlay /><DashboardRouteSkeleton /><CookieConsentBanner /><CookiePreferencesEntry /></>);
    },
    progress: {
        color: '#4B5563',
    },
});

