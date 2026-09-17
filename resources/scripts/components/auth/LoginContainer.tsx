import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import { Button } from '@/components/ui/button';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import OAuthProviderButton from '@/components/auth/OAuthProviderButton';
import { oauthErrorMessage, oauthRedirectUrl } from '@/lib/oauth';

interface Values {
    username: string;
    password: string;
}

const LoginContainer = ({ history, location }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');
    const [redirecting, setRedirecting] = useState(false);

    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);
    const { passwordLogin, providers } = useStoreState((state) => state.settings.data!.auth);

    useEffect(() => {
        clearFlashes();

        // An OAuth login returns here with the two-factor token, or the reason it failed.
        const params = new URLSearchParams(location.search);
        const checkpoint = params.get('checkpoint');
        const error = params.get('oauth_error');

        if (checkpoint) {
            history.replace('/auth/login/checkpoint', { token: checkpoint });
        } else if (error) {
            addFlash({ type: 'error', title: 'Chyba', message: oauthErrorMessage(error) });
            // Drop the query without a router navigation, which would clear the message again.
            window.history.replaceState(window.history.state, '', location.pathname);
        }
    }, []);

    const onProviderClick = (id: string) => {
        setRedirecting(true);
        window.location.href = oauthRedirectUrl(id);
    };

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // If there is no token in the state yet, request the token and then abort this submit request
        // since it will be re-submitted when the recaptcha data is returned by the component.
        if (recaptchaEnabled && !token) {
            ref.current!.execute().catch((error) => {
                console.error(error);

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        login({ ...values, recaptchaData: token })
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';
                    return;
                }

                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);

                setToken('');
                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ username: '', password: '' }}
            validationSchema={object().shape({
                username: string().required('Zadej uživatelské jméno nebo e-mail.'),
                password: string().required('Zadej své heslo.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <LoginFormContainer title={'Vítej v Pteru!'} subtitle={'Všechny 4CAMPS servery na jednom místě.'}>
                    {providers.length > 0 && (
                        <div className={'flex flex-col gap-3'}>
                            {providers.map((provider) => (
                                <OAuthProviderButton
                                    key={provider.id}
                                    provider={provider}
                                    label={`Pokračovat přes ${provider.name}`}
                                    disabled={redirecting || isSubmitting}
                                    onClick={() => onProviderClick(provider.id)}
                                />
                            ))}
                        </div>
                    )}
                    {providers.length > 0 && passwordLogin && (
                        <div className={'my-6 flex items-center gap-3 text-xs text-neutral-400'} aria-hidden={'true'}>
                            <span className={'h-px flex-1 bg-neutral-600'} />
                            nebo
                            <span className={'h-px flex-1 bg-neutral-600'} />
                        </div>
                    )}
                    {!passwordLogin && providers.length === 0 && (
                        <p className={'text-center text-sm text-neutral-400'}>
                            Přihlášení je momentálně vypnuté. Kontaktuj prosím administrátora.
                        </p>
                    )}
                    {passwordLogin && (
                        <>
                            <Field
                                type={'text'}
                                label={'Uživatelské jméno nebo e-mail'}
                                name={'username'}
                                disabled={isSubmitting}
                            />
                            <div css={tw`mt-6`}>
                                <Field type={'password'} label={'Heslo'} name={'password'} disabled={isSubmitting} />
                            </div>
                            <div css={tw`mt-6`}>
                                <Button
                                    size={'lg'}
                                    className={'w-full'}
                                    type={'submit'}
                                    isLoading={isSubmitting}
                                    disabled={isSubmitting}
                                >
                                    Přihlásit se
                                </Button>
                            </div>
                            {recaptchaEnabled && (
                                <Reaptcha
                                    ref={ref}
                                    size={'invisible'}
                                    sitekey={siteKey || '_invalid_key'}
                                    onVerify={(response) => {
                                        setToken(response);
                                        submitForm();
                                    }}
                                    onExpire={() => {
                                        setSubmitting(false);
                                        setToken('');
                                    }}
                                />
                            )}
                            <div css={tw`mt-6 text-center`}>
                                <Link
                                    to={'/auth/password'}
                                    css={tw`text-xs text-neutral-500 tracking-wide no-underline uppercase hover:text-neutral-600`}
                                >
                                    Zapomněl jsi heslo?
                                </Link>
                            </div>
                        </>
                    )}
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default LoginContainer;
