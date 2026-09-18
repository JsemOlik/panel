import useSWR, { ConfigInterface, responseInterface } from 'swr';
import { AxiosError } from 'axios';
import http, { PaginatedResult } from '@/api/http';
import { toPaginatedSet } from '@definitions/helpers';
import { ServerPlayerSession, Transformers } from '@definitions/players';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { ServerContext } from '@/state/server';

const useServerPlayerSessions = (
    player: string | null,
    page = 1,
    config?: ConfigInterface<PaginatedResult<ServerPlayerSession>, AxiosError>
): responseInterface<PaginatedResult<ServerPlayerSession>, AxiosError> => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const key = useServerSWRKey(['player-sessions', player, page]);

    return useSWR<PaginatedResult<ServerPlayerSession>>(
        player ? key : null,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/players/${player}/sessions`, {
                params: { page },
            });

            return toPaginatedSet(data, Transformers.toServerPlayerSession);
        },
        {
            revalidateOnMount: false,
            // Matches the roster's cadence, so an expanded player's history gains its join/leave
            // row at the same moment the dot next to their name changes colour.
            refreshInterval: 5000,
            refreshWhenHidden: false,
            ...(config || {}),
        }
    );
};

export { useServerPlayerSessions };
