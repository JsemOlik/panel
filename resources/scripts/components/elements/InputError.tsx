import React from 'react';
import { FormikErrors, FormikTouched } from 'formik';
import { cn } from '@/lib/utils';

interface FieldMessageProps {
    // The validation error for the field, if any. Takes precedence over the description.
    error?: unknown;
    children?: React.ReactNode;
    className?: string;
}

// Renders the validation error for a form field, falling back to its description.
export const FieldMessage = ({ error, children, className }: FieldMessageProps) => {
    if (error) {
        const message = Array.isArray(error) ? error[0] : error;

        return (
            <p role={'alert'} className={cn('field-message mt-2 text-xs text-red-400', className)}>
                {String(message).charAt(0).toUpperCase() + String(message).slice(1)}
            </p>
        );
    }

    return children ? <p className={cn('field-message mt-2 text-xs text-neutral-400', className)}>{children}</p> : null;
};

interface Props {
    errors: FormikErrors<any>;
    touched: FormikTouched<any>;
    name: string;
    children?: string | number | null | undefined;
}

const InputError = ({ errors, touched, name, children }: Props) => (
    <FieldMessage error={touched[name] && errors[name]}>{children}</FieldMessage>
);

export default InputError;
