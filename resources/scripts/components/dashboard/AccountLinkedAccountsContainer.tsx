import React, { useEffect, useState } from 'react';
import { useHistory, useLocation } from 'react-router';
import { format } from 'date-fns';
import { LinkIcon } from '@heroicons/react/outline';
import PageContentBlock from '@/components/elements/PageContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/ui/button';
import { LinkedOAuthProvider, unlinkOAuthProvider, useLinkedOAuthProviders } from '@/api/account/oauth';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { oauthErrorMessage, oauthRedirectUrl } from '@/lib/oauth';

export default () => {
    const history = useHistory();
    const location = useLocation();
    const { addFlash } = useFlash();
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('account:oauth');
    const [unlinking, setUnlinking] = useState<LinkedOAuthProvider | null>(null);
    const [redirecting, setRedirecting] = useState(false);

    const { data, isValidating, error, mutate } = useLinkedOAuthProviders({
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        // Only replace the flashes when loading fails, so the result of linking a provider stays visible.
        if (error) clearAndAddHttpError(error);
    }, [error]);

    // Linking a provider returns to this page with the result.
    useEffect(() => {
        const params = new URLSearchParams(location.search);
        const failure = params.get('oauth_error');

        if (params.get('oauth') === 'linked') {
            addFlash({ key: 'account:oauth', type: 'success', title: 'Success', message: 'The account was linked.' });
        } else if (failure) {
            addFlash({ key: 'account:oauth', type: 'error', title: 'Error', message: oauthErrorMessage(failure) });
        }

        if (params.has('oauth') || failure) {
            history.replace(location.pathname);
        }
    }, []);

    const onUnlink = () => {
        if (!unlinking) return;

        clearFlashes();
        unlinkOAuthProvider(unlinking.id)
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setUnlinking(null));
    };

    const linkedCount = data?.providers.filter((provider) => provider.linked).length ?? 0;

    return (
        <PageContentBlock title={'Linked Accounts'}>
            <Dialog.Confirm
                open={!!unlinking}
                title={'Unlink account'}
                confirm={'Unlink'}
                onConfirmed={onUnlink}
                onClose={() => setUnlinking(null)}
            >
                You will no longer be able to sign in with {unlinking?.name} until you link it again.
            </Dialog.Confirm>
            <FlashMessageRender byKey={'account:oauth'} className={'mb-4'} />
            <div className={'relative'}>
                <SpinnerOverlay visible={!data && isValidating} />
                {!data ? (
                    <p className={'text-sm text-neutral-400'}>Loading...</p>
                ) : !data.providers.length ? (
                    <p className={'text-sm text-neutral-400'}>There are no services you can link your account with.</p>
                ) : (
                    <ul className={'divide-y divide-neutral-600 rounded-xl border border-neutral-600 bg-neutral-700'}>
                        {data.providers.map((provider) => {
                            // Without password login, the last linked provider is the only way to sign in.
                            const locked = !data.passwordLogin && provider.linked && linkedCount <= 1;

                            return (
                                <li key={provider.id} className={'flex items-center gap-4 p-4'}>
                                    <span
                                        aria-hidden={'true'}
                                        style={{ backgroundColor: provider.color }}
                                        className={'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg'}
                                    >
                                        {provider.logo ? (
                                            <img src={provider.logo} alt={''} className={'h-5 w-5 object-contain'} />
                                        ) : (
                                            <LinkIcon className={'h-5 w-5 text-white'} />
                                        )}
                                    </span>
                                    <div className={'min-w-0 flex-1'}>
                                        <p className={'truncate text-sm font-medium text-neutral-50'}>
                                            {provider.name}
                                        </p>
                                        <p className={'mt-0.5 truncate text-xs text-neutral-400'}>
                                            {provider.linked
                                                ? [
                                                      provider.email,
                                                      provider.linkedAt &&
                                                          `linked ${format(provider.linkedAt, 'MMM do, yyyy')}`,
                                                  ]
                                                      .filter(Boolean)
                                                      .join(' · ')
                                                : 'Not linked'}
                                        </p>
                                    </div>
                                    {provider.linked ? (
                                        <Button
                                            variant={'outline'}
                                            size={'sm'}
                                            disabled={locked}
                                            title={
                                                locked
                                                    ? 'This is the only way to sign in while password login is disabled.'
                                                    : undefined
                                            }
                                            onClick={() => setUnlinking(provider)}
                                        >
                                            Unlink
                                        </Button>
                                    ) : (
                                        <Button
                                            size={'sm'}
                                            disabled={redirecting}
                                            onClick={() => {
                                                setRedirecting(true);
                                                window.location.href = oauthRedirectUrl(provider.id, true);
                                            }}
                                        >
                                            Link
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </PageContentBlock>
    );
};
