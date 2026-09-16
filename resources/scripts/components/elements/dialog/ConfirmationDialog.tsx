import React from 'react';
import { Dialog, RenderDialogProps } from './';
import { Button } from '@/components/ui/button';

type ConfirmationProps = Omit<RenderDialogProps, 'description' | 'children'> & {
    children: React.ReactNode;
    confirm?: string | undefined;
    onConfirmed: (e: React.MouseEvent<HTMLButtonElement, MouseEvent>) => void;
};

export default ({ confirm = 'Okay', children, onConfirmed, ...props }: ConfirmationProps) => {
    return (
        <Dialog {...props} description={typeof children === 'string' ? children : undefined}>
            {typeof children !== 'string' && children}
            <Dialog.Footer>
                <Button variant={'secondary'} onClick={props.onClose}>
                    Cancel
                </Button>
                <Button variant={'destructive'} onClick={onConfirmed}>
                    {confirm}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};
