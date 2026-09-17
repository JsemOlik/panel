const colors = require('tailwindcss/colors');

// Both palettes are CSS variables holding space-separated RGB channels, so they can change at runtime.
// The gray shades follow the user's theme (resources/scripts/lib/theme.ts) and the primary shades follow
// the color picked under Account > Appearance (resources/scripts/lib/primaryColor.ts).
const variablePalette = (name) =>
    Object.fromEntries(
        [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((shade) => [
            shade,
            ({ opacityValue }) =>
                opacityValue === undefined
                    ? `rgb(var(--color-${name}-${shade}))`
                    : `rgb(var(--color-${name}-${shade}) / ${opacityValue})`,
        ])
    );

const gray = variablePalette('gray');
const brand = variablePalette('primary');

module.exports = {
    content: ['./resources/scripts/**/*.{js,ts,tsx}'],
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
            borderColor: (theme) => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ],
};
