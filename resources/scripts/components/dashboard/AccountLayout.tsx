import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import ContentContainer from '@/components/elements/ContentContainer';
import { NestedPageContext, PageFooter } from '@/components/elements/PageContentBlock';
import routes from '@/routers/routes';
import { cn } from '@/lib/utils';

const toPath = (path: string) => `/account/${path}`.replace('//', '/').replace(/\/$/, '') || '/account';

// Wraps the account pages with a heading and a navigation listing each of them.
export default ({ children }: { children: React.ReactNode }) => {
    const location = useLocation();
    const pages = routes.account.filter((route) => !!route.name);
    const pathname = location.pathname.replace(/\/$/, '');
    const current = pages.find((route) => toPath(route.path) === pathname);

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer css={tw`my-6 sm:my-10`}>
                    <div>
                        <h1 className={'text-2xl font-semibold text-neutral-50 sm:text-3xl'}>Settings</h1>
                        <p className={'mt-1 text-sm text-neutral-400 sm:text-base'}>
                            Update your account details and make the Panel your own.
                        </p>
                    </div>
                    <div className={'mt-6 flex flex-col gap-6 sm:mt-10 md:flex-row md:gap-10'}>
                        <nav
                            aria-label={'Settings'}
                            className={
                                '-mx-4 shrink-0 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:w-56 md:px-0 [&::-webkit-scrollbar]:hidden'
                            }
                        >
                            <ul className={'flex gap-1 md:flex-col'}>
                                {pages.map(({ path, name, icon: Icon, exact }) => (
                                    <li key={path}>
                                        <NavLink
                                            to={toPath(path)}
                                            exact={exact}
                                            className={cn(
                                                'flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-neutral-400 no-underline transition-colors hover:bg-neutral-600/60 hover:text-neutral-50 md:py-2.5',
                                                '[&.active]:bg-neutral-600 [&.active]:text-neutral-50'
                                            )}
                                        >
                                            {Icon && <Icon className={'h-5 w-5 shrink-0'} aria-hidden={'true'} />}
                                            {name}
                                        </NavLink>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                        <div className={'min-w-0 flex-1'}>
                            {current && (
                                <div className={'mb-6'}>
                                    <h2 className={'text-xl font-semibold text-neutral-50'}>{current.name}</h2>
                                    {current.description && (
                                        <p className={'mt-1 text-sm text-neutral-400'}>{current.description}</p>
                                    )}
                                </div>
                            )}
                            <NestedPageContext.Provider value={true}>{children}</NestedPageContext.Provider>
                        </div>
                    </div>
                </ContentContainer>
                <PageFooter />
            </>
        </CSSTransition>
    );
};
