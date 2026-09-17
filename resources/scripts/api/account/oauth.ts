import useSWR, { ConfigInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { useUserSWRKey } from '@/plugins/useSWRKey';
import { OAuthProvider } from '@/state/settings';

export interface LinkedOAuthProvider extends OAuthProvider {
    linked: boolean;
    email: string | null;
    linkedAt: Date | null;
    lastUsedAt: Date | null;
}

export interface LinkedOAuthProviders {
    providers: LinkedOAuthProvider[];
    passwordLogin: boolean;
}

const useLinkedOAuthProviders = (config?: ConfigInterface<LinkedOAuthProviders, AxiosError>) => {
    const key = useUserSWRKey(['account', 'oauth']);

    return useSWR(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/oauth');

            return {
                passwordLogin: data.password_login,
                providers: data.data.map((datum: any) => ({
                    id: datum.id,
                    name: datum.name,
                    color: datum.color,
                    logo: datum.logo,
                    linked: datum.linked,
                    email: datum.email,
                    linkedAt: datum.linked_at ? new Date(datum.linked_at) : null,
                    lastUsedAt: datum.last_used_at ? new Date(datum.last_used_at) : null,
                })),
            };
        },
        { revalidateOnMount: false, ...(config || {}) }
    );
};

const unlinkOAuthProvider = async (id: string): Promise<void> => {
    await http.delete(`/api/client/account/oauth/${encodeURIComponent(id)}`);
};

export { useLinkedOAuthProviders, unlinkOAuthProvider };
