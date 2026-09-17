import React from 'react';
import { HeartIcon } from '@heroicons/react/solid';
import { useStoreState } from '@/state/hooks';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { BrandLogo } from '@/components/elements/BrandLogo';

// The people behind this fork of the Panel, listed on the "O Pteru" page.
const authors = ['Oliver Steiner'];

const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

export default () => {
    const { name, version } = useStoreState((state) => state.settings.data!);

    return (
        <PageContentBlock title={'O Pteru'}>
            <BrandLogo title={name} className={'block h-12 w-auto'} />
            <p className={'mt-6 max-w-2xl text-sm leading-relaxed text-neutral-300 sm:text-base'}>
                Ptero je panel pro správu všech herních serverů 4CAMPS na jednom místě. Je postavený na open-source
                projektu Pterodactyl Panel, upraveném pro potřeby 4CAMPS.
            </p>
            <code
                className={'mt-6 inline-block rounded-md bg-neutral-600 px-3 py-1.5 font-mono text-sm text-neutral-200'}
            >
                v: {version}
            </code>

            <h3 className={'mt-10 text-sm font-semibold text-neutral-300'}>Autoři projektu</h3>
            <ul className={'mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3'}>
                {authors.map((author) => (
                    <li key={author} className={'flex items-center gap-3'}>
                        <span
                            aria-hidden={'true'}
                            className={
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-600 text-xs font-semibold text-neutral-300'
                            }
                        >
                            {initials(author)}
                        </span>
                        <span className={'text-neutral-100'}>{author}</span>
                    </li>
                ))}
            </ul>
            <p className={'mt-6 flex items-start gap-2 text-sm text-neutral-300'}>
                <HeartIcon className={'mt-0.5 h-4 w-4 shrink-0 text-red-500'} aria-hidden={'true'} />
                <span>
                    Postaveno na{' '}
                    <a
                        href={'https://pterodactyl.io'}
                        target={'_blank'}
                        rel={'noopener noreferrer'}
                        className={'text-primary-400 hover:underline'}
                    >
                        Pterodactyl Panelu
                    </a>
                    . Děkujeme jeho autorům a všem, kdo se na projektu podíleli.
                </span>
            </p>
        </PageContentBlock>
    );
};
