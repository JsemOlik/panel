import http, { FractalResponseData } from '@/api/http';

export type WallboardMemberRole = 'proxy' | 'member';

/**
 * A server's status as reported by the wallboard aggregate endpoint
 * (GET /api/client/areas/wallboard, see AreaWallboardController + WallboardStatusService).
 *
 * Exactly one of `live`, `stale`, `unreachable` is ever true — the backend guarantees this so the
 * frontend never has to guess which fields are trustworthy:
 *   - `live`: `state`/`cpuAbsolute`/`memoryBytes` came from Wings just now (or a cache no older
 *     than ~20s shared with the live "current usage" widget).
 *   - `stale`: Wings could not be reached, but a resource sample collected within the last few
 *     minutes exists — `state` etc. reflect that LAST KNOWN reading, and `lastSeenAt` says when.
 *     Must be rendered as visibly non-current (never as if it were live).
 *   - `unreachable`: no live data and nothing recent enough to trust either. `state` is null.
 *     Must never be rendered as any particular power state (not "offline", not "online") — this
 *     is "we don't know", which is a materially different, more urgent condition than "off".
 */
export interface WallboardServerStatus {
    id: number;
    uuid: string;
    name: string;
    role: WallboardMemberRole;
    sortOrder: number;
    isNodeUnderMaintenance: boolean;
    state: string | null;
    isSuspended: boolean;
    live: boolean;
    stale: boolean;
    unreachable: boolean;
    cpuAbsolute: number | null;
    memoryBytes: number | null;
    lastSeenAt: string | null;
}

export interface WallboardArea {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    members: WallboardServerStatus[];
}

export interface WallboardStatus {
    generatedAt: string;
    areas: WallboardArea[];
}

const rawDataToWallboardMember = (data: Record<string, any>): WallboardServerStatus => ({
    id: data.id,
    uuid: data.uuid,
    name: data.name,
    role: data.role,
    sortOrder: data.sort_order ?? 0,
    isNodeUnderMaintenance: data.is_node_under_maintenance ?? false,
    state: data.state ?? null,
    isSuspended: data.is_suspended ?? false,
    live: data.live ?? false,
    stale: data.stale ?? false,
    unreachable: data.unreachable ?? false,
    cpuAbsolute: data.cpu_absolute ?? null,
    memoryBytes: data.memory_bytes ?? null,
    lastSeenAt: data.last_seen_at ?? null,
});

const rawDataToWallboardArea = (data: Record<string, any>): WallboardArea => ({
    id: data.id,
    uuid: data.uuid,
    name: data.name,
    description: data.description ?? null,
    members: ((data.members as Record<string, any>[] | undefined) || []).map(rawDataToWallboardMember),
});

export default (): Promise<WallboardStatus> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/areas/wallboard')
            .then(({ data }: { data: FractalResponseData }) => {
                const attributes = data.attributes;

                resolve({
                    generatedAt: attributes.generated_at,
                    areas: ((attributes.areas as Record<string, any>[] | undefined) || []).map(rawDataToWallboardArea),
                });
            })
            .catch(reject);
    });
};
