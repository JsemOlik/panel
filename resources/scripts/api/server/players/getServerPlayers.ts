import useSWR, { ConfigInterface, responseInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { transform } from '@definitions/helpers';
import { PlayerStatus, ServerPlayer, Transformers } from '@definitions/players';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { ServerContext } from '@/state/server';

export interface ServerPlayerFilters {
    status?: PlayerStatus;
}

const useServerPlayers = (
    filters: ServerPlayerFilters = {},
    config?: ConfigInterface<ServerPlayer[], AxiosError>
): responseInterface<ServerPlayer[], AxiosError> => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const key = useServerSWRKey(['players', filters]);

    return useSWR<ServerPlayer[]>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/players`, {
                params: {
                    status: filters.status || undefined,
                },
            });

            return transform(data, Transformers.toServerPlayer, []);
        },
        { revalidateOnMount: true, ...(config || {}) }
    );
};

export { useServerPlayers };
