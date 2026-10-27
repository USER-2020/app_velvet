const SCRIPT_ID = 'meta-pixel-script';

export function getMetaPixelId() {
    return String(import.meta.env.VITE_META_PIXEL_ID || '').trim();
}

export function initializeMetaPixel() {
    const pixelId = getMetaPixelId();
    if (!pixelId || typeof window === 'undefined' || typeof document === 'undefined') return false;

    if (!window.fbq) {
        const fbq = function (...args) {
            if (fbq.callMethod) fbq.callMethod(...args);
            else fbq.queue.push(args);
        };
        fbq.queue = [];
        fbq.loaded = true;
        fbq.version = '2.0';
        window.fbq = fbq;
    }

    if (!document.getElementById(SCRIPT_ID)) {
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.async = true;
        script.src = 'https://connect.facebook.net/en_US/fbevents.js';
        document.head.appendChild(script);
    }

    if (!window.__velvetMetaPixelInitialized) {
        window.fbq('init', pixelId);
        window.__velvetMetaPixelInitialized = pixelId;
    }

    return true;
}

export function trackMetaPageView() {
    if (typeof window !== 'undefined' && window.fbq && window.__velvetMetaPixelInitialized) {
        window.fbq('track', 'PageView');
    }
}
