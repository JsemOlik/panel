import { useEffect, useState } from 'react';

export type ThemePreference = 'system' | 'light' | 'dark';
export type Theme = Exclude<ThemePreference, 'system'>;

const storageKey = 'theme';

// Dispatched on the window whenever the applied theme changes.
const themeChangeEvent = 'panel:theme-change';

const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] as const;

// The gray palette for each theme, from the strongest foreground (50) to the page background (800-900).
// Light mode mirrors the dark scale, so classes like `bg-neutral-700 text-neutral-50` work in both themes.
const grays: Record<Theme, Record<typeof shades[number], string>> = {
    dark: {
        50: '#f9fafb',
        100: '#e6ecf2',
        200: '#cad5e2', // sidebar foreground
        300: '#aebcce',
        400: '#90a1b9', // muted foreground
        500: '#455369',
        600: '#1c2432', // secondary / muted surfaces
        700: '#080f1c', // cards
        800: '#050a17', // page background
        900: '#030612',
    },
    light: {
        50: '#020617',
        100: '#0f172a',
        200: '#1e293b',
        300: '#334155',
        400: '#64748b',
        500: '#94a3b8',
        600: '#e2e8f0',
        700: '#ffffff',
        800: '#f1f5f9',
        900: '#e9eef4',
    },
};

// Colors of the sidebar and top bar, which sit slightly apart from the page background.
const chrome: Record<Theme, Record<string, string>> = {
    dark: {
        background: '#070c1a',
        border: 'rgba(255, 255, 255, 0.1)',
        hover: 'rgba(255, 255, 255, 0.05)',
    },
    light: {
        background: '#ffffff',
        border: 'rgba(15, 23, 42, 0.1)',
        hover: 'rgba(15, 23, 42, 0.05)',
    },
};

const channels = (hex: string): string => {
    const value = parseInt(hex.replace('#', ''), 16);

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255].join(' ');
};

const systemQuery = () => window.matchMedia('(prefers-color-scheme: light)');

export const getThemePreference = (): ThemePreference => {
    try {
        const value = localStorage.getItem(storageKey);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
};

export const resolveTheme = (preference: ThemePreference): Theme =>
    preference === 'system' ? (systemQuery().matches ? 'light' : 'dark') : preference;

// Writes the theme's colors into the CSS variables the Tailwind "gray" palette reads from.
const applyTheme = (theme: Theme) => {
    const root = document.documentElement;

    shades.forEach((shade) => root.style.setProperty(`--color-gray-${shade}`, channels(grays[theme][shade])));
    Object.entries(chrome[theme]).forEach(([name, value]) => root.style.setProperty(`--chrome-${name}`, value));

    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    window.dispatchEvent(new CustomEvent(themeChangeEvent));
};

let unsubscribe: (() => void) | undefined;

export const applyThemePreference = (preference: ThemePreference) => {
    applyTheme(resolveTheme(preference));

    unsubscribe?.();
    unsubscribe = undefined;

    // Follow the operating system's setting while it changes.
    if (preference === 'system') {
        const query = systemQuery();
        const listener = () => applyTheme(resolveTheme('system'));

        query.addEventListener('change', listener);
        unsubscribe = () => query.removeEventListener('change', listener);
    }
};

export const setThemePreference = (preference: ThemePreference) => {
    try {
        localStorage.setItem(storageKey, preference);
    } catch {
        // Storage may be unavailable (e.g. private browsing), the theme then only lasts for this visit.
    }

    applyThemePreference(preference);
};

// The saved preference and the theme currently shown, kept in sync wherever the theme is changed.
export const useTheme = (): { preference: ThemePreference; theme: Theme } => {
    const read = () => ({ preference: getThemePreference(), theme: document.documentElement.dataset.theme as Theme });
    const [state, setState] = useState(read);

    useEffect(() => {
        const listener = () => setState(read());

        window.addEventListener(themeChangeEvent, listener);

        return () => window.removeEventListener(themeChangeEvent, listener);
    }, []);

    return state;
};

// Resolves a gray shade to an rgba() string, for places that can't use CSS variables (e.g. canvas).
export const grayRgba = (shade: number, alpha = 1): string => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(`--color-gray-${shade}`).trim();

    return `rgba(${value.split(' ').join(', ')}, ${alpha})`;
};
