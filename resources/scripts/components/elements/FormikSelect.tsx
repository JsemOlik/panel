import React from 'react';
import { useField } from 'formik';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

// Radix Select does not allow empty string item values, so an empty form value is mapped to this sentinel.
const EMPTY_VALUE = '__empty__';

export interface FormikSelectOption {
    value: string;
    label: React.ReactNode;
    disabled?: boolean;
}

interface Props {
    name: string;
    options: FormikSelectOption[];
    id?: string;
    placeholder?: React.ReactNode;
    disabled?: boolean;
    className?: string;
}

// A Radix Select bound to a Formik field value.
const FormikSelect = ({ name, options, id, placeholder, disabled, className }: Props) => {
    const [field, , { setValue, setTouched }] = useField<string>(name);

    const toSelectValue = (value: string) => (value === '' ? EMPTY_VALUE : value);
    const hasEmptyOption = options.some((option) => option.value === '');
    // Show the placeholder when the field is empty and there is no explicit empty option to select.
    const value = field.value === '' && !hasEmptyOption ? '' : toSelectValue(field.value ?? '');

    return (
        <Select
            name={name}
            value={value}
            disabled={disabled}
            onValueChange={(v) => setValue(v === EMPTY_VALUE ? '' : v)}
            onOpenChange={(open) => !open && setTouched(true, false)}
        >
            <SelectTrigger id={id} className={className}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem
                        key={toSelectValue(option.value)}
                        value={toSelectValue(option.value)}
                        disabled={option.disabled}
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
};

export default FormikSelect;
