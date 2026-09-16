import React, { useContext, useEffect, useState } from 'react';
import asDialog from '@/hoc/asDialog';
import { Dialog, DialogWrapperContext } from '@/components/elements/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import disableAccountTwoFactor from '@/api/account/disableAccountTwoFactor';
import { useFlashKey } from '@/plugins/useFlash';
import { useStoreActions } from '@/state/hooks';
import FlashMessageRender from '@/components/FlashMessageRender';

const DisableTOTPDialog = () => {
    const [submitting, setSubmitting] = useState(false);
    const [password, setPassword] = useState('');
    const { clearAndAddHttpError } = useFlashKey('account:two-step');
    const { close, setProps } = useContext(DialogWrapperContext);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);

    useEffect(() => {
        setProps((state) => ({ ...state, preventExternalClose: submitting }));
    }, [submitting]);

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (submitting) return;

        setSubmitting(true);
        clearAndAddHttpError();
        disableAccountTwoFactor(password)
            .then(() => {
                updateUserData({ useTotp: false });
                close();
            })
            .catch(clearAndAddHttpError)
            .then(() => setSubmitting(false));
    };

    return (
        <form id={'disable-totp-form'} className={'mt-6'} onSubmit={submit}>
            <FlashMessageRender byKey={'account:two-step'} className={'-mt-2 mb-6'} />
            <Label className={'mb-2 block'} htmlFor={'totp-password'}>
                Password
            </Label>
            <Input
                id={'totp-password'}
                type={'password'}
                className={'h-10'}
                value={password}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.currentTarget.value)}
            />
            <Dialog.Footer>
                <Button variant={'secondary'} onClick={close}>
                    Cancel
                </Button>
                <Tooltip
                    delay={100}
                    disabled={password.length > 0}
                    content={'You must enter your account password to continue.'}
                >
                    <Button
                        variant={'destructive'}
                        type={'submit'}
                        form={'disable-totp-form'}
                        disabled={submitting || !password.length}
                    >
                        Disable
                    </Button>
                </Tooltip>
            </Dialog.Footer>
        </form>
    );
};

export default asDialog({
    title: 'Disable Two-Step Verification',
    description: 'Disabling two-step verification will make your account less secure.',
})(DisableTOTPDialog);
