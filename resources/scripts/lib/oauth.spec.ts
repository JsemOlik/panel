import { oauthErrorMessage, oauthRedirectUrl } from '@/lib/oauth';

describe('@/lib/oauth.ts', function () {
    describe('oauthErrorMessage()', function () {
        it('should return the message for a known reason', function () {
            expect(oauthErrorMessage('denied')).toBe('Přihlášení bylo zrušeno.');
            expect(oauthErrorMessage('domain')).toBe('E-mailová doména tohoto účtu nemá do panelu přístup.');
        });

        it('should return a generic message for an unknown reason', function () {
            const generic = 'Přihlášení se nepodařilo. Zkus to prosím znovu.';

            expect(oauthErrorMessage('something_else')).toBe(generic);
            expect(oauthErrorMessage('')).toBe(generic);
        });

        // The reasons come from app/Exceptions/Auth/OAuthException.php.
        it('should have a message for every reason the backend uses', function () {
            [
                'denied',
                'state',
                'provider',
                'not_linked',
                'domain',
                'email_unverified',
                'already_linked',
                'registration',
            ].forEach((reason) => {
                expect(oauthErrorMessage(reason)).not.toBe('Přihlášení se nepodařilo. Zkus to prosím znovu.');
            });
        });
    });

    describe('oauthRedirectUrl()', function () {
        it('should build the login url', function () {
            expect(oauthRedirectUrl('ebd5bbf1-3ea1-436b-a175-a7fc681175d8')).toBe(
                '/auth/oauth/ebd5bbf1-3ea1-436b-a175-a7fc681175d8/redirect'
            );
        });

        it('should ask for a link when requested', function () {
            expect(oauthRedirectUrl('abc', true)).toBe('/auth/oauth/abc/redirect?link=1');
        });

        it('should encode the provider', function () {
            expect(oauthRedirectUrl('a/b c')).toBe('/auth/oauth/a%2Fb%20c/redirect');
        });
    });
});
