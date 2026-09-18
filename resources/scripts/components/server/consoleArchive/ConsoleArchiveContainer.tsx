import React, { useEffect, useState } from 'react';
import { useConsoleArchive, ConsoleArchiveFilters } from '@/api/server/consoleArchive';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ConsoleArchiveEntry } from '@definitions/consoleArchive';
import { format } from 'date-fns';

const sourceLabel: Record<ConsoleArchiveEntry['source'], string> = {
    chat: 'Chat',
    console: 'Console',
};

export default () => {
    const { clearAndAddHttpError } = useFlashKey('server:console-archive');
    const [filters, setFilters] = useState<ConsoleArchiveFilters>({ page: 1 });
    const [queryInput, setQueryInput] = useState('');
    const [playerInput, setPlayerInput] = useState('');

    const { data, isValidating, error } = useConsoleArchive(filters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        setFilters((value) => ({ ...value, page: 1, query: queryInput, player: playerInput }));
    };

    return (
        <ServerContentBlock title={'Console Archive'}>
            <FlashMessageRender byKey={'server:console-archive'} />
            <p className={'text-sm text-neutral-400 mb-4'}>
                Captured console and chat output for this server, recorded continuously regardless of whether anyone had
                the console open. Classification of a line as chat is best-effort — search covers every captured line
                either way.
            </p>
            <form onSubmit={applyFilters} className={'flex flex-col sm:flex-row gap-2 mb-4'}>
                <Input
                    className={'sm:max-w-sm'}
                    placeholder={'Search captured text…'}
                    value={queryInput}
                    onChange={(e) => setQueryInput(e.currentTarget.value)}
                />
                <Input
                    className={'sm:max-w-[12rem]'}
                    placeholder={'Player name'}
                    value={playerInput}
                    onChange={(e) => setPlayerInput(e.currentTarget.value)}
                />
                <Button type={'submit'}>Search</Button>
            </form>
            {!data && isValidating ? (
                <Spinner centered />
            ) : !data?.items.length ? (
                <p className={'text-sm text-center text-neutral-400'}>
                    No archived console entries match the current filters.
                </p>
            ) : (
                <div className={'bg-neutral-800 rounded-lg divide-y divide-neutral-700'}>
                    {data.items.map((entry) => (
                        <div key={entry.id} className={'flex gap-3 px-4 py-2 text-sm font-mono'}>
                            <span className={'text-neutral-500 shrink-0'}>
                                {format(entry.loggedAt, 'yyyy-MM-dd HH:mm:ss')}
                            </span>
                            <span
                                className={
                                    entry.source === 'chat' ? 'text-primary-400 shrink-0' : 'text-neutral-500 shrink-0'
                                }
                            >
                                [{sourceLabel[entry.source]}
                                {entry.player ? `: ${entry.player}` : ''}]
                            </span>
                            <span className={'text-neutral-200 break-all'}>{entry.line}</span>
                        </div>
                    ))}
                </div>
            )}
            {data && (
                <PaginationFooter
                    pagination={data.pagination}
                    onPageSelect={(page) => setFilters((value) => ({ ...value, page }))}
                />
            )}
        </ServerContentBlock>
    );
};
