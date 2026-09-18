import React from 'react';
import { Line } from 'react-chartjs-2';
import { ChartOptions } from 'chart.js';
import ChartBlock from '@/components/server/console/ChartBlock';
import { ResourceHistoryPoint } from '@/api/server/getServerResourceHistory';
import { HistorySeries, toHistoryChartData } from '@/components/server/history/historyChart';

interface Props {
    title: string;
    legend?: React.ReactNode;
    points: ResourceHistoryPoint[];
    series: HistorySeries[];
    options: ChartOptions<'line'>;
}

export default ({ title, legend, points, series, options }: Props) => (
    <ChartBlock title={title} legend={legend}>
        {points.length === 0 ? (
            <p className={'text-sm text-center text-neutral-500 py-8'}>No data for this range yet.</p>
        ) : (
            <Line data={toHistoryChartData(points, series)} options={options} />
        )}
    </ChartBlock>
);
