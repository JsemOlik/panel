import { Area, rawDataToAreaObject } from '@/api/areas/getArea';
import http, { PaginatedResult } from '@/api/http';

interface QueryParams {
    page?: number;
}

/**
 * The areas index is deliberately unpaginated server-side: AreaController filters the collection
 * through AreaPolicy in PHP rather than SQL, so paginating before that filter would produce short
 * or empty pages. Areas number in the low dozens at most, so the whole set is returned at once and
 * presented here as a single-page PaginatedResult to keep the list component's shape unchanged.
 */
export default ({ ...params }: QueryParams = {}): Promise<PaginatedResult<Area>> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/areas', { params })
            .then(({ data }) => {
                const items = (data.data || []).map(rawDataToAreaObject);

                resolve({
                    items,
                    pagination: {
                        total: items.length,
                        count: items.length,
                        perPage: items.length || 1,
                        currentPage: 1,
                        totalPages: 1,
                    },
                });
            })
            .catch(reject);
    });
};
