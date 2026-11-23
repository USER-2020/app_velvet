import { useEffect, useState } from 'react';

const storageKey = 'velvet-theme';
const themeColors = { dark: '#090a10', light: '#faf9fc' };

function syncThemeColor(theme) {
    if (typeof document === 'undefined') return;
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', themeColors[theme] || themeColors.dark);
}

export function getTheme() {
    if (typeof window === 'undefined') return 'dark';
    return window.localStorage.getItem(storageKey) || 'dark';
}

export function setTheme(theme) {
    if (typeof document !== 'undefined') document.documentElement.dataset.theme = theme;
    syncThemeColor(theme);
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, theme);
        window.dispatchEvent(new CustomEvent('velvet:theme-changed', { detail: theme }));
    }
}

export function useTheme() {
    const [theme, setCurrentTheme] = useState(getTheme);

    useEffect(() => {
        setTheme(theme);
        const onThemeChange = (event) => setCurrentTheme(event.detail || 'dark');
        window.addEventListener('velvet:theme-changed', onThemeChange);
        return () => window.removeEventListener('velvet:theme-changed', onThemeChange);
    }, [theme]);

    return [theme, (nextTheme) => setCurrentTheme(nextTheme)];
}
