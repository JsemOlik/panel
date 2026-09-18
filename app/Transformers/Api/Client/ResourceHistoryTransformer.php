<?php

namespace Pterodactyl\Transformers\Api\Client;

/**
 * Shapes a single resource-history point (see ResourceHistoryController) for the client
 * API. Points arrive already normalized to a common shape by the controller, regardless of
 * whether they were sourced from raw samples or hourly rollups.
 */
class ResourceHistoryTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return 'resource_history_point';
    }

    /**
     * @param array{timestamp: string, state: string|null, cpu: float, memory_bytes: int, disk_bytes: int, network_rx_bytes: int, network_tx_bytes: int} $point
     */
    public function transform(array $point): array
    {
        return [
            'timestamp' => $point['timestamp'],
            'state' => $point['state'],
            'cpu' => $point['cpu'],
            'memory_bytes' => $point['memory_bytes'],
            'disk_bytes' => $point['disk_bytes'],
            'network_rx_bytes' => $point['network_rx_bytes'],
            'network_tx_bytes' => $point['network_tx_bytes'],
        ];
    }
}
