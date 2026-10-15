import { useEffect } from 'react';

/**
 * Mantiene los listados operativos sincronizados si el proveedor websocket
 * no está disponible. Solo consulta cuando la pestaña está visible.
 */
export function useRealtimeFallback(refresh, isRealtimeUnavailable, interval = 20000) {
    useEffect(() => {
        if (!isRealtimeUnavailable) return undefined;
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') refresh();
        }, interval);

        return () => window.clearInterval(timer);
    }, [refresh, interval]);
}
