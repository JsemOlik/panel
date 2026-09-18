import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import useSWR from 'swr';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faLayerGroup } from '@fortawesome/free-solid-svg-icons';
import getAreas from '@/api/areas/getAreas';
import { Area } from '@/api/areas/getArea';
import { PaginatedResult } from '@/api/http';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Pagination from '@/components/elements/Pagination';
import useFlash from '@/plugins/useFlash';

const AreaRowBox = styled(GreyRowBox)`
    ${tw`grid grid-cols-12 gap-4`};
`;

const AreaRow = ({ area, className }: { area: Area; className?: string }) => {
    const proxy = area.members.find((member) => member.role === 'proxy');

    return (
        <AreaRowBox as={Link} to={`/areas/${area.uuid}`} className={className}>
            <div css={tw`flex items-center col-span-12 sm:col-span-8`}>
                <div className={'icon mr-4'}>
                    <FontAwesomeIcon icon={faLayerGroup} />
                </div>
                <div className={'min-w-0'}>
                    <p css={tw`text-lg break-words`}>{area.name}</p>
                    {!!area.description && (
                        <p css={tw`text-sm text-neutral-300 break-words line-clamp-2`}>{area.description}</p>
                    )}
                </div>
            </div>
            <div css={tw`hidden sm:flex col-span-4 items-center justify-end`}>
                <p css={tw`text-sm text-neutral-400`}>
                    {area.members.length} {area.members.length === 1 ? 'server' : 'servers'}
                    {proxy ? ` · proxy: ${proxy.name}` : ''}
                </p>
            </div>
        </AreaRowBox>
    );
};

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const { data: areas, error } = useSWR<PaginatedResult<Area>>(['/api/client/areas', page], () => getAreas({ page }));

    React.useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'areas', error });
        if (!error) clearFlashes('areas');
    }, [error]);

    return (
        <PageContentBlock title={'Areas'} showFlashKey={'areas'}>
            <h1 css={tw`text-2xl font-semibold text-neutral-50 mb-4`}>Areas</h1>
            {!areas ? (
                <Spinner centered size={'large'} />
            ) : (
                <Pagination data={areas} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((area, index) => (
                                <AreaRow key={area.id} area={area} className={index > 0 ? 'mt-2' : undefined} />
                            ))
                        ) : (
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                There are no areas associated with your account.
                            </p>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
