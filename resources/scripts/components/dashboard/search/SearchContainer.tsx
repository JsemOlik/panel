import React, { useState } from 'react';
import { SearchIcon } from '@heroicons/react/outline';
import useEventListener from '@/plugins/useEventListener';
import SearchModal from '@/components/dashboard/search/SearchModal';

const isMac = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform);

export default () => {
    const [visible, setVisible] = useState(false);

    useEventListener('keydown', (e: KeyboardEvent) => {
        if (['input', 'textarea'].indexOf(((e.target as HTMLElement).tagName || 'input').toLowerCase()) < 0) {
            if (!visible && (e.metaKey || e.ctrlKey) && ['k', '/'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                setVisible(true);
            }
        }
    });

    return (
        <>
            {visible && <SearchModal appear visible={visible} onDismissed={() => setVisible(false)} />}
            {/* A full search field from the tablet layout up, an icon button below it. */}
            <button
                type={'button'}
                onClick={() => setVisible(true)}
                aria-label={'Search'}
                className={
                    'flex h-9 w-9 items-center justify-center gap-2 rounded-lg text-neutral-400 transition-colors hover:bg-neutral-600 hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 md:w-full md:justify-start md:border md:border-neutral-600 md:bg-neutral-700/60 md:px-3 md:hover:bg-neutral-700'
                }
            >
                <SearchIcon className={'h-4 w-4 shrink-0'} aria-hidden={'true'} />
                <span className={'hidden flex-1 text-left text-sm md:block'}>Search...</span>
                <kbd
                    className={
                        'hidden rounded bg-primary-500/15 px-1.5 py-0.5 font-sans text-xs font-medium text-primary-400 md:block'
                    }
                >
                    {isMac ? '⌘K' : 'Ctrl K'}
                </kbd>
            </button>
        </>
    );
};
