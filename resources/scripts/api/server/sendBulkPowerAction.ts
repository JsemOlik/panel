import http from '@/api/http';

export type PowerSignal = 'start' | 'stop' | 'restart' | 'kill';

export interface BulkPowerResult {
    signal: PowerSignal;
    succeeded: string[];
    skipped: string[];
    failed: string[];
}

// Sends the power action to every server owned by the signed-in user.
export default async (signal: PowerSignal): Promise<BulkPowerResult> => {
    const { data } = await http.post('/api/client/servers/power', { signal });

    return data.attributes;
};
