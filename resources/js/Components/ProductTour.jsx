import { useCallback, useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { usePage } from '@inertiajs/react';
import { FiHelpCircle } from 'react-icons/fi';
import { useAuthorization } from '@/lib/authorization';
import { productTours, TOUR_VERSION, tourKeyForPath } from '@/lib/productTours';

export default function ProductTour() {
    const page = usePage();
    const { can, user } = useAuthorization();
    const autoStartedTours = useRef(new Set());
    const activeTour = useRef(false);
    const pendingObserver = useRef(null);
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
        pendingObserver.current?.disconnect();
        pendingObserver.current = null;
    }, [page.url]);

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

    if (!user || !isInternalModule || !toursEnabled) return null;

    return <button type="button" onClick={() => startTour(tourKey)} className="group fixed bottom-5 right-5 z-[60] inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-[#88429a] bg-[#27142f] text-xs font-semibold text-[#f5d4fc] shadow-[0_12px_30px_rgba(0,0,0,.35)] transition-[width,border-color,background-color] duration-300 ease-out hover:w-36 hover:border-[#d56bea] hover:bg-[#3a1a46] focus-visible:w-36 focus-visible:border-[#d56bea] focus-visible:bg-[#3a1a46] focus-visible:outline-none" aria-label="Repetir guía de este módulo"><FiHelpCircle size={18} className="shrink-0" /><span className="max-w-0 translate-x-2 overflow-hidden whitespace-nowrap opacity-0 transition-[max-width,opacity,transform] duration-300 ease-out group-hover:ml-2 group-hover:max-w-24 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:ml-2 group-focus-visible:max-w-24 group-focus-visible:translate-x-0 group-focus-visible:opacity-100">Repetir guía</span></button>;
}
