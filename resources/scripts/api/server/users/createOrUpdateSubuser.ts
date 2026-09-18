import http from '@/api/http';
import { rawDataToServerSubuser } from '@/api/server/users/getServerSubusers';
import { Subuser } from '@/state/server/subusers';

interface Params {
    email: string;
    permissions: string[];
    expiresAt?: string | null;
}

export default (uuid: string, params: Params, subuser?: Subuser): Promise<Subuser> => {
    return new Promise((resolve, reject) => {
        const { expiresAt, ...rest } = params;

        http.post(`/api/client/servers/${uuid}/users${subuser ? `/${subuser.uuid}` : ''}`, {
            ...rest,
            ...(expiresAt !== undefined ? { expires_at: expiresAt } : {}),
        })
            .then((data) => resolve(rawDataToServerSubuser(data.data)))
            .catch(reject);
    });
};
