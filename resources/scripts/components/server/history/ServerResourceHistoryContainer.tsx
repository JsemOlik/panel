import React, { useEffect, useState } from 'react';
import { theme } from 'twin.macro';
import { CloudDownloadIcon, CloudUploadIcon } from '@heroicons/react/solid';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { useFlashKey } from '@/plugins/useFlash';
import { bytesToString } from '@/lib/formatters';
import { hexToRgba } from '@/lib/helpers';
import { primaryRgba } from '@/lib/primaryColor';
import getServerResourceHistory, {
    ResourceHistoryPoint,
    ResourceHistoryRange,
} from '@/api/server/getServerResourceHistory';
import HistoryChartBlock from '@/components/server/history/HistoryChartBlock';
import { getHistoryChartOptions } from '@/components/server/history/historyChart';

const ranges: { key: ResourceHistoryRange; label: string }[] = [
    { key: 'hour', label: 'Hour' },
    { key: 'day', label: 'Day' },
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
];

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearAndAddHttpError } = useFlashKey('server:resource-history');

    const [range, setRange] = useState<ResourceHistoryRange>('day');
    const [points, setPoints] = useState<ResourceHistoryPoint[] | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);

        getServerResourceHistory(uuid, range)
            .then((data) => {
                setPoints(data);
                clearAndAddHttpError();
            })
            .catch((error) => {
                clearAndAddHttpError(error);
            })
            .then(() => setLoading(false));
    }, [uuid, range]);

    return (
        <ServerContentBlock title={'Resource History'}>
            <FlashMessageRender byKey={'server:resource-history'} />
            <p className={'text-sm text-neutral-400 mb-4'}>
                Resource usage recorded on a schedule, independent of whether anyone has the console open. Missed
                samples (a node briefly unreachable, the server offline) show up as a break in the line rather than a
                flat zero.
            </p>
            <div className={'flex gap-2 mb-4'}>
                {ranges.map(({ key, label }) => (
                    <button
                        key={key}
                        onClick={() => setRange(key)}
                        className={
                            'px-3 py-1 rounded text-sm transition-colors ' +
                            (range === key
                                ? 'bg-primary-500 text-primary-50'
                                : 'bg-neutral-700 text-neutral-300 hover:bg-neutral-600')
                        }
                    >
                        {label}
                    </button>
                ))}
            </div>
            {loading && !points ? (
                <Spinner size={'large'} centered />
            ) : (
                <div className={'grid grid-cols-1 md:grid-cols-2 gap-2 sm:gap-4'}>
                    <HistoryChartBlock
                        title={'CPU Load'}
                        points={points || []}
                        options={getHistoryChartOptions(range, (value) => `${value.toFixed(0)}%`)}
                        series={[
                            {
                                label: 'CPU',
                                color: primaryRgba(400),
                                fillColor: primaryRgba(700, 0.5),
                                accessor: (point) => Number(point.cpu.toFixed(2)),
                            },
                        ]}
                    />
                    <HistoryChartBlock
                        title={'Memory'}
                        points={points || []}
                        options={getHistoryChartOptions(range, (value) => bytesToString(value))}
                        series={[
                            {
                                label: 'Memory',
                                color: primaryRgba(400),
                                fillColor: primaryRgba(700, 0.5),
                                accessor: (point) => point.memoryUsageInBytes,
                            },
                        ]}
                    />
                    <HistoryChartBlock
                        title={'Disk'}
                        points={points || []}
                        options={getHistoryChartOptions(range, (value) => bytesToString(value))}
                        series={[
                            {
                                label: 'Disk',
                                color: primaryRgba(400),
                                fillColor: primaryRgba(700, 0.5),
                                accessor: (point) => point.diskUsageInBytes,
                            },
                        ]}
                    />
                    <HistoryChartBlock
                        title={'Network'}
                        points={points || []}
                        options={getHistoryChartOptions(range, (value) => bytesToString(value))}
                        legend={
                            <>
                                <Tooltip arrow content={'Inbound'}>
                                    <CloudDownloadIcon className={'mr-2 w-4 h-4 text-yellow-400'} />
                                </Tooltip>
                                <Tooltip arrow content={'Outbound'}>
                                    <CloudUploadIcon className={'w-4 h-4 text-cyan-400'} />
                                </Tooltip>
                            </>
                        }
                        series={[
                            {
                                label: 'Network In',
                                color: primaryRgba(400),
                                fillColor: primaryRgba(700, 0.5),
                                accessor: (point) => point.networkRxInBytes,
                            },
                            {
                                label: 'Network Out',
                                color: theme('colors.yellow.400') as unknown as string,
                                fillColor: hexToRgba(theme('colors.yellow.700') as unknown as string, 0.5),
                                accessor: (point) => point.networkTxInBytes,
                            },
                        ]}
                    />
                </div>
            )}
        </ServerContentBlock>
    );
};
