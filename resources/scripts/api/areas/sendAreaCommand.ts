import http from '@/api/http';

// Richer than PowerActionResult (api/definitions/power.ts): a command failure/skip needs to
// distinguish *why* ("no_permission" vs "offline" vs "suspended") in a way a bare server name
// can't, so AreaCommandController returns one object per server instead of a name string.
export interface AreaCommandResultServer {
    id: number;
    uuid: string;
    name: string;
    reason?: string;
}

export interface AreaCommandResult {
    command: string;
    succeeded: AreaCommandResultServer[];
    skipped: AreaCommandResultServer[];
    failed: AreaCommandResultServer[];
}

// Sends a single console command to every server in an area. Mirrors sendAreaPowerAction.ts:
// the response is wrapped in a {object, attributes} envelope, unwrapped here the same way.
export default async (area: number | string, command: string): Promise<AreaCommandResult> => {
    const { data } = await http.post(`/api/client/areas/${area}/command`, { command });

    return data.attributes;
};
