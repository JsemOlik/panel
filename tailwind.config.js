const colors = require('tailwindcss/colors');

// Dark slate/navy palette, converted from OKLCH to hex so colors can still be parsed
// and alpha-blended at runtime (e.g. by the console charts).
const gray = {
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
};

// 4CAMPS brand purple, with #8a4cf5 as the base shade.
const brand = {
    50: '#f5f3ff',
    100: '#ede9ff',
    200: '#ded5ff',
    300: '#c6b3ff',
    400: '#a989fc',
    500: '#8a4cf5',
    600: '#773dd7',
    700: '#6430b9',
    800: '#512995',
    900: '#3f2372',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Roboto"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#030612',
                // "primary" and "neutral" are deprecated, prefer the use of "blue" and "gray"
                // in new code.
                primary: brand,
                blue: brand,
                cyan: brand,
                gray: gray,
                neutral: gray,
                red: { ...colors.red, 500: '#fb2c36' },
                green: { ...colors.green, 500: '#22c55e' },
                yellow: { ...colors.yellow, 500: '#ffb900' },
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
