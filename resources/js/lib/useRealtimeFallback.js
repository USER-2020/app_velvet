import { useEffect } from 'react';

/**
 * Polls only while realtime is unavailable. The normal update path is Ably.
 */
export function useRealtimeFallback(refresh, isRealtimeUnavailable, interval = 20000) {
    useEffect(() => {
        if (!isRealtimeUnavailable) return undefined;

        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') refresh();
        }, interval);

        return () => window.clearInterval(timer);
    }, [refresh, interval, isRealtimeUnavailable]);
}
