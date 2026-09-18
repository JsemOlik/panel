import { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import { DeepPartial } from 'ts-essentials';
import { theme } from 'twin.macro';
import { format } from 'date-fns';
import { primaryRgba } from '@/lib/primaryColor';
import { getOptions } from '@/components/server/console/chart';
import { ResourceHistoryPoint, ResourceHistoryRange } from '@/api/server/getServerResourceHistory';

/**
 * The live console charts (see `@/components/server/console/chart.ts`) use a fixed-width
 * ring buffer plotted on an index-based x-axis (`min: 0, max: 19`), which only makes sense
 * for "the last 20 pushes". History charts plot real points spread across an hour to a
 * month, so instead of introducing chart.js's `time` scale (which needs
 * `chartjs-adapter-date-fns`, not currently a dependency here) this uses a plain `linear`
 * x-axis fed real Unix-ms timestamps, with tick labels formatted on the fly — same visual
 * result, no new dependency, and it reuses `getOptions`/theming from the live chart so the
 * two look identical.
 */
function tickFormat(range: ResourceHistoryRange): string {
    return range === 'hour' || range === 'day' ? 'HH:mm' : 'MMM d';
}

export interface HistorySeries {
    label: string;
    color: string;
    fillColor?: string;
    /** Extracts the y-value for this series from a single history point. */
    accessor: (point: ResourceHistoryPoint) => number;
}

/**
 * Converts a list of chronologically-ordered history points into chart.js data for one or
 * more series plotted against real time. The response never gap-fills (see the
 * resources/history read contract), so a missing minute/hour is simply absent from `points`
 * rather than needing to be nulled out here — chart.js draws a straight segment across
 * whatever span separates two real points, which is an acceptable simplification at the
 * "was it laggy yesterday afternoon" granularity this chart targets.
 */
function toHistoryChartData(points: ResourceHistoryPoint[], series: HistorySeries[]): ChartData<'line'> {
    return {
        datasets: series.map((s) => ({
            label: s.label,
            data: points.map((point) => ({ x: new Date(point.timestamp).getTime(), y: s.accessor(point) })),
            fill: true,
            borderColor: s.color,
            backgroundColor: s.fillColor ?? primaryRgba(700, 0.5),
        })) as ChartDataset<'line'>[],
    };
}

/**
 * Builds chart.js options for a history chart: a real-time-scaled x-axis (see above) plus
 * an optional y-axis tick formatter (e.g. to append "%" or format bytes).
 */
function getHistoryChartOptions(
    range: ResourceHistoryRange,
    yTickCallback?: (value: number) => string
): ChartOptions<'line'> {
    return getOptions({
        scales: {
            x: {
                type: 'linear',
                min: undefined,
                max: undefined,
                grid: { display: false, drawBorder: false },
                ticks: {
                    display: true,
                    color: theme('colors.gray.400') as unknown as string,
                    font: { family: theme('fontFamily.sans'), size: 11, weight: '400' },
                    callback(value) {
                        return format(new Date(Number(value)), tickFormat(range));
                    },
                },
            },
            y: yTickCallback
                ? {
                      ticks: {
                          callback: (value) => yTickCallback(Number(value)),
                      },
                  }
                : undefined,
        },
        plugins: {
            tooltip: {
                enabled: true,
                mode: 'index',
                intersect: false,
                callbacks: {
                    title: ([item]) => (item ? format(new Date(item.parsed.x), 'PPpp') : ''),
                },
            },
        },
    } as DeepPartial<ChartOptions<'line'>>);
}

export { toHistoryChartData, getHistoryChartOptions };
