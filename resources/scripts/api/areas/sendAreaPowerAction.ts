import http from '@/api/http';
import { PowerActionResult } from '@/api/definitions/power';

// Areas only support ordered start/stop/restart — 'kill' is intentionally not exposed at the
// area level (it's a single-server emergency action; sequencing it adds risk for no benefit).
export type AreaPowerSignal = 'start' | 'stop' | 'restart';

// Sends a sequenced power action to every member of an area. This can take significantly
// longer than a single-server power action (the backend waits for the members to reach a
// stable state before acting on the proxy), so callers should surface that this may take up
// to 60-90 seconds rather than appearing hung.
export default async (area: number | string, signal: AreaPowerSignal): Promise<PowerActionResult<AreaPowerSignal>> => {
    const { data } = await http.post(`/api/client/areas/${area}/power`, { signal });

    return data.attributes;
};
