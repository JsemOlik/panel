import http from '@/api/http';

export type ResourceHistoryRange = 'hour' | 'day' | 'week' | 'month';

export interface ResourceHistoryPoint {
    timestamp: string;
    state: string | null;
    cpu: number;
    memoryUsageInBytes: number;
    diskUsageInBytes: number;
    networkRxInBytes: number;
    networkTxInBytes: number;
}

interface RawResourceHistoryPoint {
    timestamp: string;
    state: string | null;
    cpu: number;
    memory_bytes: number;
    disk_bytes: number;
    network_rx_bytes: number;
    network_tx_bytes: number;
}

const rawDataToResourceHistoryPoint = (data: RawResourceHistoryPoint): ResourceHistoryPoint => ({
    timestamp: data.timestamp,
    state: data.state,
    cpu: data.cpu,
    memoryUsageInBytes: data.memory_bytes,
    diskUsageInBytes: data.disk_bytes,
    networkRxInBytes: data.network_rx_bytes,
    networkTxInBytes: data.network_tx_bytes,
});

export default (uuid: string, range: ResourceHistoryRange): Promise<ResourceHistoryPoint[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/resources/history`, { params: { range } })
            .then(({ data }) =>
                resolve(
                    (data.data || []).map(({ attributes }: { attributes: RawResourceHistoryPoint }) =>
                        rawDataToResourceHistoryPoint(attributes)
                    )
                )
            )
            .catch(reject);
    });
};
