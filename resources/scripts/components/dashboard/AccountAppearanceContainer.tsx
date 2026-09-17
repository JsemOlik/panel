import React, { useState } from 'react';
import { CheckIcon, DesktopComputerIcon, MoonIcon, SunIcon } from '@heroicons/react/outline';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { primaryColors } from '@/primaryColors';
import { getPrimaryColor, setPrimaryColor } from '@/lib/primaryColor';
import { setThemePreference, ThemePreference, useTheme } from '@/lib/theme';
import { cn } from '@/lib/utils';

const themes: { id: ThemePreference; name: string; icon: typeof SunIcon }[] = [
    { id: 'light', name: 'Light', icon: SunIcon },
    { id: 'dark', name: 'Dark', icon: MoonIcon },
    { id: 'system', name: 'System', icon: DesktopComputerIcon },
];

export default () => {
    const [selected, setSelected] = useState(getPrimaryColor);
    const { preference: theme } = useTheme();

    return (
        <PageContentBlock title={'Appearance'}>
            <div>
                <h3 className={'text-base font-semibold text-neutral-50'}>Theme</h3>
                <p className={'mt-1 text-sm text-neutral-400'}>
                    System follows the light or dark setting of your device. Like the primary color, it is saved on this
                    device only.
                </p>
                <div role={'radiogroup'} aria-label={'Theme'} className={'mt-4 grid grid-cols-3 gap-2 sm:gap-4'}>
                    {themes.map(({ id, name, icon: Icon }) => {
                        const active = id === theme;

                        return (
                            <button
                                key={id}
                                type={'button'}
                                role={'radio'}
                                aria-checked={active}
                                onClick={() => {
                                    setThemePreference(id);
                                }}
                                className={cn(
                                    'flex h-24 flex-col items-center justify-center gap-2 rounded-xl border text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 sm:h-28 sm:text-base',
                                    active
                                        ? 'border-primary-500 bg-primary-500/10 text-primary-400'
                                        : 'border-neutral-600 text-neutral-100 hover:bg-neutral-700 hover:text-neutral-50'
                                )}
                            >
                                <Icon className={'h-6 w-6'} aria-hidden={'true'} />
                                {name}
                            </button>
                        );
                    })}
                </div>
            </div>
            <div className={'mt-10'}>
                <h3 className={'text-base font-semibold text-neutral-50'}>Primary color</h3>
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
