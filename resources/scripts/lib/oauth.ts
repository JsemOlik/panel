// Messages for the reasons an OAuth login or account link fails, see app/Exceptions/Auth/OAuthException.php.
const errors: Record<string, string> = {
    denied: 'Přihlášení bylo zrušeno.',
    state: 'Platnost přihlášení vypršela. Zkus to prosím znovu.',
    provider: 'Nepodařilo se ověřit účet u poskytovatele přihlášení. Zkus to prosím znovu později.',
    not_linked:
        'K tomuto účtu není připojený žádný uživatel. Přihlas se heslem a připoj ho v nastavení účtu, nebo kontaktuj administrátora.',
    domain: 'E-mailová doména tohoto účtu nemá do panelu přístup.',
    email_unverified: 'E-mailová adresa tohoto účtu není ověřená.',
    already_linked: 'Tento účet je už připojený k jinému uživateli, nebo máš u této služby připojený jiný účet.',
    registration: 'Nepodařilo se vytvořit uživatele. Kontaktuj prosím administrátora.',
};

export const oauthErrorMessage = (reason: string): string =>
    errors[reason] || 'Přihlášení se nepodařilo. Zkus to prosím znovu.';

export const oauthRedirectUrl = (provider: string, link = false): string =>
    `/auth/oauth/${encodeURIComponent(provider)}/redirect${link ? '?link=1' : ''}`;
