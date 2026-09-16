import React from 'react';
import { PaginatedResult } from '@/api/http';
import tw from 'twin.macro';
import { Button } from '@/components/ui/button';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faAngleDoubleLeft, faAngleDoubleRight } from '@fortawesome/free-solid-svg-icons';

interface RenderFuncProps<T> {
    items: T[];
    isLastPage: boolean;
    isFirstPage: boolean;
}

interface Props<T> {
    data: PaginatedResult<T>;
    showGoToLast?: boolean;
    showGoToFirst?: boolean;
    onPageSelect: (page: number) => void;
    children: (props: RenderFuncProps<T>) => React.ReactNode;
}

function Pagination<T>({ data: { items, pagination }, onPageSelect, children }: Props<T>) {
    const isFirstPage = pagination.currentPage === 1;
    const isLastPage = pagination.currentPage >= pagination.totalPages;

    const pages = [];

    // Start two spaces before the current page. If that puts us before the starting page default
    // to the first page as the starting point.
    const start = Math.max(pagination.currentPage - 2, 1);
    const end = Math.min(pagination.totalPages, pagination.currentPage + 5);

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return (
        <>
            {children({ items, isFirstPage, isLastPage })}
            {pages.length > 1 && (
                <div css={tw`mt-4 flex justify-center gap-2`}>
                    {pages[0] > 1 && !isFirstPage && (
                        <Button variant={'outline'} size={'icon'} onClick={() => onPageSelect(1)}>
                            <FontAwesomeIcon icon={faAngleDoubleLeft} />
                        </Button>
                    )}
                    {pages.map((i) => (
                        <Button
                            variant={pagination.currentPage !== i ? 'outline' : 'default'}
                            size={'icon'}
                            key={`block_page_${i}`}
                            onClick={() => onPageSelect(i)}
                        >
                            {i}
                        </Button>
                    ))}
                    {pages[4] < pagination.totalPages && !isLastPage && (
                        <Button variant={'outline'} size={'icon'} onClick={() => onPageSelect(pagination.totalPages)}>
                            <FontAwesomeIcon icon={faAngleDoubleRight} />
                        </Button>
                    )}
                </div>
            )}
        </>
    );
}

export default Pagination;
