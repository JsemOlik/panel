import useSWR, { ConfigInterface, responseInterface } from 'swr';
import { AxiosError } from 'axios';
import http, { PaginatedResult } from '@/api/http';
import { toPaginatedSet } from '@definitions/helpers';
import { ConsoleArchiveEntry, Transformers } from '@definitions/consoleArchive';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { ServerContext } from '@/state/server';

export interface ConsoleArchiveFilters {
    page?: number;
    query?: string;
    player?: string;
    source?: 'console' | 'chat';
    from?: string;
    to?: string;
}

const useConsoleArchive = (
    filters: ConsoleArchiveFilters,
    config?: ConfigInterface<PaginatedResult<ConsoleArchiveEntry>, AxiosError>
): responseInterface<PaginatedResult<ConsoleArchiveEntry>, AxiosError> => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const key = useServerSWRKey(['console-archive', filters]);

    return useSWR<PaginatedResult<ConsoleArchiveEntry>>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/console-archive`, {
                params: {
                    page: filters.page,
                    query: filters.query || undefined,
                    player: filters.player || undefined,
                    source: filters.source || undefined,
                    from: filters.from || undefined,
                    to: filters.to || undefined,
                },
            });

            return toPaginatedSet(data, Transformers.toConsoleArchiveEntry);
        },
        { revalidateOnMount: false, ...(config || {}) }
    );
};

export { useConsoleArchive };
