import React, { memo, useState } from 'react';
import { ServerEggVariable } from '@/api/server/types';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { usePermissions } from '@/plugins/usePermissions';
import InputSpinner from '@/components/elements/InputSpinner';
import { Input } from '@/components/ui/input';
import Switch from '@/components/elements/Switch';
import { debounce } from 'debounce';
import updateStartupVariable from '@/api/server/updateStartupVariable';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import getServerStartup from '@/api/swr/getServerStartup';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import isEqual from 'react-fast-compare';
import { ServerContext } from '@/state/server';

interface Props {
    variable: ServerEggVariable;
}

// Radix Select does not allow empty string item values, so an empty option is mapped to this sentinel.
const EMPTY_SELECT_VALUE = '__empty__';
const toSelectValue = (value: string) => (value === '' ? EMPTY_SELECT_VALUE : value);

const VariableBox = ({ variable }: Props) => {
    const FLASH_KEY = `server:startup:${variable.envVariable}`;

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [loading, setLoading] = useState(false);
    const [canEdit] = usePermissions(['startup.update']);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { mutate } = getServerStartup(uuid);

    const setVariableValue = debounce((value: string) => {
        setLoading(true);
        clearFlashes(FLASH_KEY);

        updateStartupVariable(uuid, variable.envVariable, value)
            .then(([response, invocation]) =>
                mutate(
                    (data) => ({
                        ...data,
                        invocation,
                        variables: (data.variables || []).map((v) =>
                            v.envVariable === response.envVariable ? response : v
                        ),
                    }),
                    false
                )
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ error, key: FLASH_KEY });
            })
            .then(() => setLoading(false));
    }, 500);

    const useSwitch = variable.rules.some(
        (v) => v === 'boolean' || v === 'in:0,1' || v === 'in:1,0' || v === 'in:true,false' || v === 'in:false,true'
    );
    const isStringSwitch = variable.rules.some((v) => v === 'string');
    const selectValues = variable.rules.find((v) => v.startsWith('in:'))?.split(',') || [];

    return (
        <TitledGreyBox
            title={
                <p className='text-sm uppercase'>
                    {!variable.isEditable && (
                        <span className='bg-neutral-700 text-xs py-1 px-2 rounded-full mr-2 mb-1'>Read Only</span>
                    )}
                    {variable.name}
                </p>
            }
        >
            <FlashMessageRender byKey={FLASH_KEY} className='mb-2 md:mb-4' />
            <InputSpinner visible={loading}>
                {useSwitch ? (
                    <>
                        <Switch
                            readOnly={!canEdit || !variable.isEditable}
                            name={variable.envVariable}
                            defaultChecked={
                                isStringSwitch ? variable.serverValue === 'true' : variable.serverValue === '1'
                            }
                            onChange={(checked) => {
                                if (canEdit && variable.isEditable) {
                                    if (isStringSwitch) {
                                        setVariableValue(checked ? 'true' : 'false');
                                    } else {
                                        setVariableValue(checked ? '1' : '0');
                                    }
                                }
                            }}
                        />
                    </>
                ) : (
                    <>
                        {selectValues.length > 0 ? (
                            <>
                                <Select
                                    onValueChange={(value) =>
                                        setVariableValue(value === EMPTY_SELECT_VALUE ? '' : value)
                                    }
                                    name={variable.envVariable}
                                    defaultValue={toSelectValue(variable.serverValue ?? variable.defaultValue)}
                                    disabled={!canEdit || !variable.isEditable}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={variable.defaultValue} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectValues.map((selectValue) => {
                                            const value = selectValue.replace('in:', '');

                                            return (
                                                <SelectItem key={value} value={toSelectValue(value)}>
                                                    {value === '' ? <em>(empty)</em> : value}
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>
                            </>
                        ) : (
                            <>
                                <Input
                                    onKeyUp={(e) => {
                                        if (canEdit && variable.isEditable) {
                                            setVariableValue(e.currentTarget.value);
                                        }
                                    }}
                                    readOnly={!canEdit || !variable.isEditable}
                                    name={variable.envVariable}
                                    defaultValue={variable.serverValue ?? ''}
                                    placeholder={variable.defaultValue}
                                />
                            </>
                        )}
                    </>
                )}
            </InputSpinner>

            <p className='mt-1 text-xs text-neutral-300'>{variable.description}</p>
        </TitledGreyBox>
    );
};

export default memo(VariableBox, isEqual);
