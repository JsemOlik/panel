import http, { FractalResponseData } from '@/api/http';
import { rawDataToServerObject, Server } from '@/api/server/getServer';

export type AreaMemberRole = 'proxy' | 'member';

export type AreaMember = Server & {
    role: AreaMemberRole;
    sortOrder: number;
};

export interface Area {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    members: AreaMember[];
}

/**
 * AreaTransformer embeds each member as a flat ServerTransformer payload with the pivot's
 * `area_role`/`area_sort_order` merged in alongside it — not as a nested Fractal relationship —
 * so wrap it back into the `{ attributes }` shape rawDataToServerObject expects.
 */
export const rawDataToAreaMember = (data: Record<string, any>): AreaMember => ({
    ...rawDataToServerObject({ object: 'server', attributes: data } as FractalResponseData),
    role: data.area_role,
    sortOrder: data.area_sort_order ?? 0,
});

export const rawDataToAreaObject = ({ attributes: data }: FractalResponseData): Area => ({
    id: data.id,
    uuid: data.uuid,
    name: data.name,
    description: data.description && data.description.length > 0 ? data.description : null,
    members: ((data.members as Record<string, any>[] | undefined) || []).map(rawDataToAreaMember),
});

export default (id: number | string): Promise<Area> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/areas/${id}`)
            .then(({ data }) => resolve(rawDataToAreaObject(data)))
            .catch(reject);
    });
};
