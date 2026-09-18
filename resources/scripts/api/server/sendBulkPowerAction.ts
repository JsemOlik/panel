import http from '@/api/http';
import { PowerActionResult, PowerSignal } from '@/api/definitions/power';

// Re-exported for backwards compatibility with existing call sites/imports.
export type { PowerSignal };
export type BulkPowerResult = PowerActionResult;

// Sends the power action to every server owned by the signed-in user.
export default async (signal: PowerSignal): Promise<BulkPowerResult> => {
    const { data } = await http.post('/api/client/servers/power', { signal });

    return data.attributes;
};
