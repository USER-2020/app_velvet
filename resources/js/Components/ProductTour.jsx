import { useCallback, useEffect, useRef, useState } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { usePage } from '@inertiajs/react';
import { FiHelpCircle, FiMessageCircle } from 'react-icons/fi';
import { useAuthorization } from '@/lib/authorization';
import { productTours, TOUR_VERSION, tourKeyForPath } from '@/lib/productTours';

export default function ProductTour() {
    const page = usePage();
    const { can, user } = useAuthorization();
    const autoStartedTours = useRef(new Set());
    const activeTour = useRef(false);
    const pendingObserver = useRef(null);
    const [launcherOpen, setLauncherOpen] = useState(false);
    const launcherRef = useRef(null);
    const progress = page.props.tourProgress || {};
    const toursEnabled = page.props.guidedTours?.enabled ?? true;
    const tourKey = tourKeyForPath(page.url);
    const path = page.url.split('?')[0];
    const isInternalModule = path === '/admin' || path.startsWith('/admin/') || path.startsWith('/monitor/') || path.startsWith('/model/');

    const markTour = useCallback((key, status) => {
        window.axios?.put(`/tour-progress/${key}`, { version: TOUR_VERSION, status }).catch(() => {});
    }, []);

    const startTour = useCallback((key, automatic = false) => {
        const definition = productTours[key];
        if (!definition || !definition.available(can) || activeTour.current || pendingObserver.current) return;

        if (automatic) autoStartedTours.current.add(key);

        const isVisible = (element) => {
            const style = window.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
        };

        const isLoadingOverlayVisible = () => Array.from(document.querySelectorAll(
            '.apply-intro-overlay, .dashboard-route-skeleton, .admin-skeleton[aria-busy="true"]',
        )).some(isVisible);

        const hasAllTargets = () => definition.steps
            .filter((step) => !step.optional)
            .every((step) => document.querySelector(step.element));

        const openTour = () => {
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                if (activeTour.current || isLoadingOverlayVisible() || !hasAllTargets()) return;

                const steps = definition.steps
                    .filter((step) => document.querySelector(step.element))
                    .map((step) => ({ element: step.element, popover: step.popover }));

                if (!steps.length) return;

                activeTour.current = true;
                let closed = false;
                let completed = false;
                let tour;
                tour = driver({
                    steps,
                    animate: true,
                    allowClose: true,
                    overlayOpacity: 0.72,
                    showProgress: true,
                    progressText: '{{current}} de {{total}}',
                    nextBtnText: 'Siguiente',
                    prevBtnText: 'Anterior',
                    doneBtnText: 'Finalizar',
                    popoverClass: 'velvet-driver-popover',
                    onNextClick: () => {
                        if (tour.isLastStep()) {
                            completed = true;
                            tour.destroy();
                            return;
                        }

                        tour.moveNext();
                    },
                    onCloseClick: () => tour.destroy(),
                    onDestroyed: () => {
                        activeTour.current = false;
                        if (!closed) {
                            closed = true;
                            markTour(key, completed ? 'completed' : 'skipped');
                        }
                    },
                });

                tour.drive();
            }));
        };

        if (hasAllTargets() && !isLoadingOverlayVisible()) {
            openTour();
            return;
        }

        let observer;
        const cleanup = () => {
            observer?.disconnect();
            window.removeEventListener('velvet-intro-overlay-complete', tryOpen);
            pendingObserver.current = null;
        };
        const tryOpen = () => {
            if (!hasAllTargets() || isLoadingOverlayVisible()) return;
            cleanup();
            openTour();
        };

        observer = new MutationObserver(tryOpen);
        pendingObserver.current = { disconnect: cleanup };
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['aria-busy', 'class', 'style'],
        });
        window.addEventListener('velvet-intro-overlay-complete', tryOpen);
        tryOpen();
    }, [can, markTour]);

    useEffect(() => () => pendingObserver.current?.disconnect(), []);

    useEffect(() => {
        const startRequestedTour = (event) => {
            const key = event.detail?.key;
            if (key) startTour(key);
        };
        window.addEventListener('velvet-tour:start', startRequestedTour);
        return () => window.removeEventListener('velvet-tour:start', startRequestedTour);
    }, [startTour]);

    useEffect(() => {
        pendingObserver.current?.disconnect();
        pendingObserver.current = null;
        setLauncherOpen(false);
    }, [page.url]);

    useEffect(() => {
        if (!launcherOpen) return undefined;
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') setLauncherOpen(false);
        };
        const closeOnOutside = (event) => {
            if (!launcherRef.current?.contains(event.target)) setLauncherOpen(false);
        };
        window.addEventListener('keydown', closeOnEscape);
        window.addEventListener('pointerdown', closeOnOutside);
        return () => {
            window.removeEventListener('keydown', closeOnEscape);
            window.removeEventListener('pointerdown', closeOnOutside);
        };
    }, [launcherOpen]);

    useEffect(() => {
        const welcome = progress['platform-welcome'];
        const isAdminDashboard = path === '/admin' || path === '/admin/recruitment';
        if (!toursEnabled || !isAdminDashboard || autoStartedTours.current.has('platform-welcome') || (welcome && welcome.version >= TOUR_VERSION)) return;

        startTour('platform-welcome', true);
    }, [path, progress, startTour, toursEnabled]);

    useEffect(() => {
        const moduleProgress = progress[tourKey];
        const isAdminDashboard = path === '/admin' || path === '/admin/recruitment';
        if (!toursEnabled || !isInternalModule || isAdminDashboard || tourKey === 'platform-welcome' || autoStartedTours.current.has(tourKey) || (moduleProgress && moduleProgress.version >= TOUR_VERSION)) return;

        startTour(tourKey, true);
    }, [isInternalModule, path, progress, startTour, tourKey, toursEnabled]);

    const canUseAi = can('ai.chat') && page.props.groqAssistant?.enabled;
    if (!user || ((!isInternalModule || !toursEnabled) && !canUseAi)) return null;

    const openAi = () => {
        setLauncherOpen(false);
        const button = document.querySelector('.velvet-launcher-toggle');
        const rect = button?.getBoundingClientRect();
        window.dispatchEvent(new CustomEvent('velvet-ai:open', {
            detail: rect ? { right: window.innerWidth - rect.right, bottom: window.innerHeight - rect.top } : undefined,
        }));
    };

    return <div ref={launcherRef} className={`velvet-launcher fixed bottom-5 right-5 z-[70] ${launcherOpen ? 'is-open' : ''}`}>
        {launcherOpen && <div className="velvet-launcher-actions">
            {canUseAi && <button type="button" onClick={openAi} data-tour="ai-chatbot-action" className="velvet-launcher-action" aria-label="Abrir Velvet AI"><FiMessageCircle size={17} /><span>Velvet AI</span></button>}
            {toursEnabled && <button type="button" onClick={() => { setLauncherOpen(false); startTour(tourKey); }} className="velvet-launcher-action" aria-label="Repetir guía de este módulo"><FiHelpCircle size={17} /><span>Repetir guía</span></button>}
        </div>}
        <button type="button" onClick={() => setLauncherOpen((current) => !current)} data-tour="ai-chatbot" aria-expanded={launcherOpen} aria-label={launcherOpen ? 'Cerrar accesos rápidos' : 'Abrir accesos rápidos'} className="velvet-launcher-toggle"><FiMessageCircle size={20} /></button>
    </div>;

}
