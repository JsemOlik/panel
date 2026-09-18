import { primaryColorShades, resolvePrimaryColor } from '@/lib/primaryColor';
import { defaultPrimaryColor, primaryColors } from '@/primaryColors';

describe('@/lib/primaryColor.ts', function () {
    describe('resolvePrimaryColor()', function () {
        it('should return the color with the given id', function () {
            expect(resolvePrimaryColor('teal').id).toBe('teal');
        });

        it('should fall back to the default color', function () {
            expect(resolvePrimaryColor(null).id).toBe(defaultPrimaryColor);
            expect(resolvePrimaryColor('chartreuse').id).toBe(defaultPrimaryColor);
        });

        it('should have a color for the default id', function () {
            expect(primaryColors.find((color) => color.id === defaultPrimaryColor)).toBeDefined();
        });
    });

    describe('primaryColorShades()', function () {
        const shades = primaryColorShades({ id: 'test', name: 'Test', value: '#8a4cf5' });

        it('should use the color itself for the 500 shade', function () {
            expect(shades['500']).toBe('138 76 245');
        });

        it('should mix the lighter shades towards white', function () {
            expect(shades['50']).toBe('249 246 255');
            expect(shades['400']).toBe('173 130 248');
        });

        it('should mix the darker shades towards black', function () {
            expect(shades['600']).toBe('117 65 208');
            expect(shades['900']).toBe('55 30 98');
        });

        it('should return every shade the Tailwind palette uses', function () {
            expect(Object.keys(shades)).toEqual(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900']);
        });

        it('should return channels that can be read as rgb values', function () {
            Object.values(shades).forEach((channels) => {
                const values = channels.split(' ').map(Number);

                expect(values).toHaveLength(3);
                values.forEach((value) => {
                    expect(Number.isInteger(value)).toBe(true);
                    expect(value).toBeGreaterThanOrEqual(0);
                    expect(value).toBeLessThanOrEqual(255);
                });
            });
        });

        it('should handle the colors users can pick', function () {
            primaryColors.forEach((color) => {
                expect(primaryColorShades(color)['500']).toMatch(/^\d+ \d+ \d+$/);
            });
        });
    });
});
