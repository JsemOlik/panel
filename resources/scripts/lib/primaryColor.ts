import { defaultPrimaryColor, PrimaryColor, primaryColors } from '@/primaryColors';

const storageKey = 'primary_color';

type Rgb = [number, number, number];

// How far each shade is mixed towards white (positive) or black (negative) from the base color.
const shades: Record<number, number> = {
    50: 0.95,
    100: 0.9,
    200: 0.75,
    300: 0.6,
    400: 0.3,
    500: 0,
    600: -0.15,
    700: -0.3,
    800: -0.45,
    900: -0.6,
};

const parseHex = (hex: string): Rgb => {
    const value = parseInt(hex.replace('#', ''), 16);

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
};

const mix = ([r, g, b]: Rgb, amount: number): Rgb => {
    const target = amount > 0 ? 255 : 0;
    const weight = Math.abs(amount);

    return [r, g, b].map((channel) => Math.round(channel + (target - channel) * weight)) as Rgb;
};

// Falls back to the default color, so an unknown or missing id never leaves the app unstyled.
export const resolvePrimaryColor = (id: string | null): PrimaryColor =>
    primaryColors.find((color) => color.id === id) ||
    primaryColors.find((color) => color.id === defaultPrimaryColor) ||
    primaryColors[0];

export const getPrimaryColor = (): PrimaryColor => {
    try {
        return resolvePrimaryColor(localStorage.getItem(storageKey));
    } catch {
        return resolvePrimaryColor(null);
    }
};

// Builds the "r g b" channel values of every shade, keyed by the shade number.
export const primaryColorShades = (color: PrimaryColor): Record<string, string> => {
    const base = parseHex(color.value);

    return Object.fromEntries(Object.entries(shades).map(([shade, amount]) => [shade, mix(base, amount).join(' ')]));
};

// Writes the color's shades into the CSS variables the Tailwind "primary" palette reads from.
export const applyPrimaryColor = (color: PrimaryColor) => {
    Object.entries(primaryColorShades(color)).forEach(([shade, channels]) => {
        document.documentElement.style.setProperty(`--color-primary-${shade}`, channels);
    });
};

export const setPrimaryColor = (color: PrimaryColor) => {
    applyPrimaryColor(color);

    try {
        localStorage.setItem(storageKey, color.id);
    } catch {
        // Storage may be unavailable (e.g. private browsing), the color then only lasts for this visit.
    }
};

// Resolves a primary shade to an rgba() string, for places that can't use CSS variables (e.g. canvas).
export const primaryRgba = (shade: number, alpha = 1): string => {
    const channels = getComputedStyle(document.documentElement)
        .getPropertyValue(`--color-primary-${shade}`)
        .trim()
        .split(' ')
        .join(', ');

    return `rgba(${channels}, ${alpha})`;
};
