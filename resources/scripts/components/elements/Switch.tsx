import React, { useMemo } from 'react';
import { v4 } from 'uuid';
import { Label } from '@/components/ui/label';
import { Switch as UiSwitch } from '@/components/ui/switch';

export interface SwitchProps {
    name: string;
    label?: string;
    description?: string;
    checked?: boolean;
    defaultChecked?: boolean;
    readOnly?: boolean;
    onChange?: (checked: boolean) => void;
}

// A shadcn/ui switch with an optional label and description next to it.
const Switch = ({ name, label, description, checked, defaultChecked, readOnly, onChange }: SwitchProps) => {
    const uuid = useMemo(() => v4(), []);

    return (
        <div className={'flex items-center'}>
            <UiSwitch
                id={uuid}
                name={name}
                checked={checked}
                defaultChecked={defaultChecked}
                disabled={readOnly}
                onCheckedChange={(value: boolean) => onChange && onChange(value)}
            />
            {(label || description) && (
                <div className={'ml-4 w-full'}>
                    {label && (
                        <Label className={'block cursor-pointer'} htmlFor={uuid}>
                            {label}
                        </Label>
                    )}
                    {description && <p className={'mt-2 text-sm text-neutral-400'}>{description}</p>}
                </div>
            )}
        </div>
    );
};

export default Switch;
