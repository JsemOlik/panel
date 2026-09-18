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
        {
            revalidateOnMount: true,
            // Presence is derived from the console stream, which is ingested in batches with about
            // a second and a half of flush latency, so the freshest this page can ever be is a
            // second or two behind the game. Polling at five seconds sits just under that, and
            // costs one small indexed query per open tab — a push transport (SSE/websocket) would
            // add a connection to maintain without making the data meaningfully newer.
            refreshInterval: 5000,
            // A backgrounded tab is not being watched; SWR revalidates on focus, so returning to
            // it is still immediate.
            refreshWhenHidden: false,
            ...(config || {}),
        }
    );
};

export { useServerPlayers };
