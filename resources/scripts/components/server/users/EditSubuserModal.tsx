import React, { useContext, useEffect, useRef } from 'react';
import { Subuser } from '@/state/server/subusers';
import { Form, Formik, useFormikContext } from 'formik';
import { array, object, string } from 'yup';
import Field from '@/components/elements/Field';
import { Actions, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import createOrUpdateSubuser from '@/api/server/users/createOrUpdateSubuser';
import { ServerContext } from '@/state/server';
import FlashMessageRender from '@/components/FlashMessageRender';
import Can from '@/components/elements/Can';
import { usePermissions } from '@/plugins/usePermissions';
import { useDeepCompareMemo } from '@/plugins/useDeepCompareMemo';
import tw from 'twin.macro';
import { Button } from '@/components/ui/button';
import PermissionTitleBox from '@/components/server/users/PermissionTitleBox';
import asModal from '@/hoc/asModal';
import PermissionRow from '@/components/server/users/PermissionRow';
import ModalContext from '@/context/ModalContext';

type Props = {
    subuser?: Subuser;
};

interface Values {
    email: string;
    permissions: string[];
    expiresAt: string;
}

// Converts a Date into the value a "datetime-local" input expects (local time,
// no timezone/seconds suffix).
const toDatetimeLocalValue = (date: Date): string => {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(
        date.getMinutes()
    )}`;
};

const EXPIRY_PRESETS: { label: string; hours: number }[] = [
    { label: '8 hours', hours: 8 },
    { label: '1 day', hours: 24 },
    { label: '3 days', hours: 72 },
    { label: '7 days', hours: 168 },
];

// Renders the expiry field plus quick preset buttons that set it relative to now.
// Split into its own component so it can reach the Formik context for setFieldValue.
const ExpiresAtField = () => {
    const { values, setFieldValue } = useFormikContext<Values>();

    const applyPreset = (hours: number) => {
        const date = new Date();
        date.setHours(date.getHours() + hours);
        setFieldValue('expiresAt', toDatetimeLocalValue(date));
    };

    return (
        <div css={tw`mt-6`}>
            <Field
                type={'datetime-local'}
                name={'expiresAt'}
                label={'Access expires'}
                description={
                    'Leave blank for permanent access. Once this time passes the user immediately loses all access to this server.'
                }
            />
            <div css={tw`mt-2 flex flex-wrap gap-2`}>
                {EXPIRY_PRESETS.map(({ label, hours }) => (
                    <button
                        key={label}
                        type={'button'}
                        css={tw`text-xs px-2 py-1 rounded bg-neutral-700 text-neutral-200 hover:bg-neutral-600 transition-colors`}
                        onClick={() => applyPreset(hours)}
                    >
                        {label}
                    </button>
                ))}
                {values.expiresAt && (
                    <button
                        type={'button'}
                        css={tw`text-xs px-2 py-1 rounded bg-neutral-700 text-neutral-200 hover:bg-neutral-600 transition-colors`}
                        onClick={() => setFieldValue('expiresAt', '')}
                    >
                        Never
                    </button>
                )}
            </div>
        </div>
    );
};

const EditSubuserModal = ({ subuser }: Props) => {
    const ref = useRef<HTMLHeadingElement>(null);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSubuser = ServerContext.useStoreActions((actions) => actions.subusers.appendSubuser);
    const { clearFlashes, clearAndAddHttpError } = useStoreActions(
        (actions: Actions<ApplicationStore>) => actions.flashes
    );
    const { dismiss, setPropOverrides } = useContext(ModalContext);

    const isRootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const permissions = useStoreState((state) => state.permissions.data);
    // The currently logged in user's permissions. We're going to filter out any permissions
    // that they should not need.
    const loggedInPermissions = ServerContext.useStoreState((state) => state.server.permissions);
    const [canEditUser] = usePermissions(subuser ? ['user.update'] : ['user.create']);

    // The permissions that can be modified by this user.
    const editablePermissions = useDeepCompareMemo(() => {
        const cleaned = Object.keys(permissions).map((key) =>
            Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)
        );

        const list: string[] = ([] as string[]).concat.apply([], Object.values(cleaned));

        if (isRootAdmin || (loggedInPermissions.length === 1 && loggedInPermissions[0] === '*')) {
            return list;
        }

        return list.filter((key) => loggedInPermissions.indexOf(key) >= 0);
    }, [isRootAdmin, permissions, loggedInPermissions]);

    const submit = (values: Values) => {
        setPropOverrides({ showSpinnerOverlay: true });
        clearFlashes('user:edit');

        createOrUpdateSubuser(
            uuid,
            {
                email: values.email,
                permissions: values.permissions,
                expiresAt: values.expiresAt ? new Date(values.expiresAt).toISOString() : null,
            },
            subuser
        )
            .then((subuser) => {
                appendSubuser(subuser);
                dismiss();
            })
            .catch((error) => {
                console.error(error);
                setPropOverrides(null);
                clearAndAddHttpError({ key: 'user:edit', error });

                if (ref.current) {
                    ref.current.scrollIntoView();
                }
            });
    };

    useEffect(
        () => () => {
            clearFlashes('user:edit');
        },
        []
    );

    return (
        <Formik
            onSubmit={submit}
            initialValues={
                {
                    email: subuser?.email || '',
                    permissions: subuser?.permissions || [],
                    expiresAt: subuser?.expiresAt && !subuser.isExpired ? toDatetimeLocalValue(subuser.expiresAt) : '',
                } as Values
            }
            validationSchema={object().shape({
                email: string()
                    .max(191, 'Email addresses must not exceed 191 characters.')
                    .email('A valid email address must be provided.')
                    .required('A valid email address must be provided.'),
                permissions: array().of(string()),
                expiresAt: string().test(
                    'is-future',
                    'The expiry date must be in the future.',
                    (value) => !value || new Date(value).getTime() > Date.now()
                ),
            })}
        >
            <Form>
                <div css={tw`flex justify-between`}>
                    <h2 css={tw`text-2xl`} ref={ref}>
                        {subuser
                            ? `${canEditUser ? 'Modify' : 'View'} permissions for ${subuser.email}`
                            : 'Create new subuser'}
                    </h2>
                    <div>
                        <Button type={'submit'} css={tw`w-full sm:w-auto`}>
                            {subuser ? 'Save' : 'Invite User'}
                        </Button>
                    </div>
                </div>
                <FlashMessageRender byKey={'user:edit'} css={tw`mt-4`} />
                {!isRootAdmin && loggedInPermissions[0] !== '*' && (
                    <div css={tw`mt-4 pl-4 py-2 border-l-4 border-cyan-400`}>
                        <p css={tw`text-sm text-neutral-300`}>
                            Only permissions which your account is currently assigned may be selected when creating or
                            modifying other users.
                        </p>
                    </div>
                )}
                {!subuser && (
                    <div css={tw`mt-6`}>
                        <Field
                            name={'email'}
                            label={'User Email'}
                            description={
                                'Enter the email address of the user you wish to invite as a subuser for this server.'
                            }
                        />
                    </div>
                )}
                <Can action={subuser ? 'user.update' : 'user.create'}>
                    <ExpiresAtField />
                </Can>
                <div css={tw`my-6`}>
                    {Object.keys(permissions)
                        .filter((key) => key !== 'websocket')
                        .map((key, index) => (
                            <PermissionTitleBox
                                key={`permission_${key}`}
                                title={key}
                                isEditable={canEditUser}
                                permissions={Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)}
                                css={index > 0 ? tw`mt-4` : undefined}
                            >
                                <p css={tw`text-sm text-neutral-400 mb-4`}>{permissions[key].description}</p>
                                {Object.keys(permissions[key].keys).map((pkey) => (
                                    <PermissionRow
                                        key={`permission_${key}.${pkey}`}
                                        permission={`${key}.${pkey}`}
                                        disabled={!canEditUser || editablePermissions.indexOf(`${key}.${pkey}`) < 0}
                                    />
                                ))}
                            </PermissionTitleBox>
                        ))}
                </div>
                <Can action={subuser ? 'user.update' : 'user.create'}>
                    <div css={tw`pb-6 flex justify-end`}>
                        <Button type={'submit'} css={tw`w-full sm:w-auto`}>
                            {subuser ? 'Save' : 'Invite User'}
                        </Button>
                    </div>
                </Can>
            </Form>
        </Formik>
    );
};

export default asModal<Props>({
    top: false,
})(EditSubuserModal);
