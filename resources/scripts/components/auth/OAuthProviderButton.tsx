import React from 'react';
import { OAuthProvider } from '@/state/settings';
import { cn } from '@/lib/utils';

interface Props {
    provider: OAuthProvider;
    label?: string;
    className?: string;
    onClick: () => void;
    disabled?: boolean;
}

export default ({ provider, label, className, onClick, disabled }: Props) => (
    <button
        type={'button'}
        onClick={onClick}
        disabled={disabled}
        style={provider.color ? { backgroundColor: provider.color } : undefined}
        className={cn(
            'flex h-10 w-full items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium text-white shadow-sm transition-[filter] hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-800 disabled:pointer-events-none disabled:opacity-60',
            !provider.color && 'bg-primary-500',
            className
        )}
    >
        {provider.logo && <img src={provider.logo} alt={''} className={'h-5 w-5 shrink-0 object-contain'} />}
        <span className={'truncate'}>{label ?? provider.name}</span>
    </button>
);
