import { gravatarHash, gravatarUrl } from '@/lib/gravatar';

describe('@/lib/gravatar.ts', function () {
    describe('gravatarHash()', function () {
        it('should hash the email address', async function () {
            await expect(gravatarHash('test@example.com')).resolves.toBe(
                '973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b'
            );
        });

        it('should ignore case and surrounding whitespace', async function () {
            await expect(gravatarHash('  Test@Example.com ')).resolves.toBe(
                '973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b'
            );
        });

        it('should return nothing without an email address', async function () {
            await expect(gravatarHash('')).resolves.toBeNull();
            await expect(gravatarHash('   ')).resolves.toBeNull();
        });
    });

    describe('gravatarUrl()', function () {
        it('should build the url for the requested size', function () {
            expect(gravatarUrl('abc', 56)).toBe('https://www.gravatar.com/avatar/abc?s=56&d=404');
        });
    });
});
