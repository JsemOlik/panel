import React from 'react';
import { Route, Switch } from 'react-router-dom';
import AppLayout from '@/components/AppLayout';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import AreaListContainer from '@/components/areas/AreaListContainer';
import AreaDetailContainer from '@/components/areas/AreaDetailContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import AccountLayout from '@/components/dashboard/AccountLayout';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';
import routes from '@/routers/routes';
import DashboardSidebar from '@/components/dashboard/DashboardSidebar';

export default () => {
    const location = useLocation();

    return (
        <AppLayout sidebar={(collapsed) => <DashboardSidebar collapsed={collapsed} />}>
            <TransitionRouter>
                <React.Suspense fallback={<Spinner centered />}>
                    <Switch location={location}>
                        <Route path={'/'} exact>
                            <DashboardContainer />
                        </Route>
                        <Route path={'/areas'} exact>
                            <AreaListContainer />
                        </Route>
                        <Route path={'/areas/:uuid'} exact>
                            <AreaDetailContainer />
                        </Route>
                        <Route path={'/account'}>
                            <AccountLayout>
                                <Switch location={location}>
                                    {routes.account.map(({ path, component: Component }) => (
                                        <Route key={path} path={`/account/${path}`.replace('//', '/')} exact>
                                            <Component />
                                        </Route>
                                    ))}
                                    <Route path={'*'}>
                                        <NotFound />
                                    </Route>
                                </Switch>
                            </AccountLayout>
                        </Route>
                        <Route path={'*'}>
                            <NotFound />
                        </Route>
                    </Switch>
                </React.Suspense>
            </TransitionRouter>
        </AppLayout>
    );
};
