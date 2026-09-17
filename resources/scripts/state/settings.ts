import { action, Action } from 'easy-peasy';

export interface OAuthProvider {
    id: string;
    name: string;
    // Null when the provider uses the primary color.
    color: string | null;
    logo: string | null;
}

export interface SiteSettings {
    name: string;
    locale: string;
    version: string;
    recaptcha: {
        enabled: boolean;
        siteKey: string;
    };
    auth: {
        passwordLogin: boolean;
        providers: OAuthProvider[];
    };
}

export interface SettingsStore {
    data?: SiteSettings;
    setSettings: Action<SettingsStore, SiteSettings>;
}

const settings: SettingsStore = {
    data: undefined,

    setSettings: action((state, payload) => {
        state.data = payload;
    }),
};

export default settings;
