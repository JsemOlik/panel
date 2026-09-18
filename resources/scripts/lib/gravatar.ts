// Gravatar identifies an account by a hash of its email address. It accepts a SHA-256 hash, which
// the browser can create on its own, so no hashing dependency is needed.
export const gravatarHash = async (email: string): Promise<string | null> => {
    const value = email.trim().toLowerCase();
    // Subtle crypto is only available in a secure context, so a panel served over plain HTTP
    // simply doesn't get avatars.
    const subtle = globalThis.crypto?.subtle;

    if (!value || !subtle) {
        return null;
    }

    try {
        const digest = await subtle.digest('SHA-256', new TextEncoder().encode(value));

        return Array.from(new Uint8Array(digest))
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    } catch {
        return null;
    }
};

// Asking for the 404 default means Gravatar doesn't answer with a generated image for accounts
// without a picture, so those fall back to the initials instead.
export const gravatarUrl = (hash: string, size: number): string =>
    `https://www.gravatar.com/avatar/${hash}?s=${size}&d=404`;
