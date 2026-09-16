import React from 'react';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import { Field, FieldProps } from 'formik';
import Switch, { SwitchProps } from '@/components/elements/Switch';

const FormikSwitch = ({ name, label, ...props }: SwitchProps) => {
    return (
        <FormikFieldWrapper name={name}>
            <Field name={name}>
                {({ field, form }: FieldProps) => (
                    <Switch
                        name={name}
                        label={label}
                        onChange={(checked) => {
                            form.setFieldTouched(name);
                            form.setFieldValue(field.name, checked);
                        }}
                        checked={!!field.value}
                        {...props}
                    />
                )}
            </Field>
        </FormikFieldWrapper>
    );
};

export default FormikSwitch;
