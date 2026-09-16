import React, { useState } from 'react';
import { CheckIcon } from '@heroicons/react/outline';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { primaryColors } from '@/primaryColors';
import { getPrimaryColor, setPrimaryColor } from '@/lib/primaryColor';
import { cn } from '@/lib/utils';

export default () => {
    const [selected, setSelected] = useState(getPrimaryColor);

    return (
        <PageContentBlock title={'Appearance'}>
            <div className={'mt-10'}>
                <h2 className={'text-base font-semibold text-neutral-50'}>Primary color</h2>
                <p className={'mt-1 text-sm text-neutral-400'}>
                    Applied to the main elements of the Panel. It is saved on this device only, so you&apos;ll need to
                    set it again on each device.
                </p>
                <p className={'text-sm text-neutral-400'}>Selected color: {selected.name.toLowerCase()}.</p>
                <div
                    role={'radiogroup'}
                    aria-label={'Primary color'}
                    className={
                        'mt-4 grid grid-cols-4 gap-2 rounded-xl border border-neutral-600 bg-neutral-700 p-3 sm:grid-cols-8 sm:gap-3'
                    }
                >
                    {primaryColors.map((color) => {
                        const active = color.id === selected.id;

                        return (
                            <button
                                key={color.id}
                                type={'button'}
                                role={'radio'}
                                aria-checked={active}
                                aria-label={color.name}
                                title={color.name}
                                onClick={() => {
                                    setPrimaryColor(color);
                                    setSelected(color);
                                }}
                                style={{ backgroundColor: color.value }}
                                className={cn(
                                    'flex h-12 items-center justify-center rounded-lg transition-transform duration-150 hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-50 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-700 sm:h-16',
                                    active && 'ring-2 ring-neutral-50/60 ring-offset-2 ring-offset-neutral-700'
                                )}
                            >
                                {active && <CheckIcon className={'h-5 w-5 text-white'} aria-hidden={'true'} />}
                            </button>
                        );
                    })}
                </div>
            </div>
        </PageContentBlock>
    );
};
