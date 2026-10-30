const SCRIPT_ID = 'google-analytics-script';

export function getGoogleAnalyticsId() {
    return String(import.meta.env.VITE_GOOGLE_ANALYTICS_ID || '').trim();
}

export function initializeGoogleAnalytics() {
    const measurementId = getGoogleAnalyticsId();
    if (!measurementId || typeof window === 'undefined' || typeof document === 'undefined') return false;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () {
        window.dataLayer.push(arguments);
    };

    if (!document.getElementById(SCRIPT_ID)) {
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        document.head.appendChild(script);
    }

    if (window.__velvetGoogleAnalyticsInitialized !== measurementId) {
        window.gtag('js', new Date());
        window.gtag('config', measurementId, { send_page_view: false });
        window.__velvetGoogleAnalyticsInitialized = measurementId;
    }

    return true;
}

export function trackGooglePageView() {
    if (typeof window === 'undefined' || !window.gtag || !window.__velvetGoogleAnalyticsInitialized) return;

    window.gtag('event', 'page_view', {
        page_path: window.location.pathname + window.location.search,
        page_location: window.location.href,
        page_title: document.title,
    });
}
