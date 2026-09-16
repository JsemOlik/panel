import React, { forwardRef } from 'react';
import { Field as FormikField, FieldProps } from 'formik';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldMessage } from '@/components/elements/InputError';

interface OwnProps {
    name: string;
    label?: string;
    description?: string;
    validate?: (value: any) => undefined | string | Promise<any>;
}

type Props = OwnProps & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'name'>;

const Field = forwardRef<HTMLInputElement, Props>(({ id, name, label, description, validate, ...props }, ref) => (
    <FormikField innerRef={ref} name={name} validate={validate}>
        {({ field, form: { errors, touched } }: FieldProps) => {
            const inputId = id || `field_${name}`;
            const error = touched[field.name] && errors[field.name];

            return (
                <div>
                    {label && (
                        <Label htmlFor={inputId} className={'mb-2 block'}>
                            {label}
                        </Label>
                    )}
                    <Input id={inputId} {...field} {...props} hasError={!!error} />
                    <FieldMessage error={error}>{description}</FieldMessage>
                </div>
            );
        }}
    </FormikField>
));
Field.displayName = 'Field';

export default Field;
