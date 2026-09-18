import React, { useEffect, useRef, useState } from 'react';
import tw from 'twin.macro';
import styled, { keyframes } from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExclamationTriangle, faSync } from '@fortawesome/free-solid-svg-icons';
import getWallboard, { WallboardStatus } from '@/api/wallboard/getWallboard';
import AreaWallboardCard from '@/components/wallboard/AreaWallboardCard';
import Spinner from '@/components/elements/Spinner';
import { applyThemePreference, getThemePreference } from '@/lib/theme';
import { httpErrorToHuman } from '@/api/http';

// Refresh cadence while the tab is visible. Matches the ~20s Wings-side cache TTL the backend
// aggregator shares with the per-server "current usage" widget (see WallboardStatusService) —
// polling meaningfully faster than that would just re-request the same cached response.
const POLL_INTERVAL_MS = 15_000;

// While the tab is backgrounded (a spare monitor that got minimized, a browser that throttled a
// hidden tab, ...) there is nobody looking at the board, so there is no reason to keep polling at
// full speed. Still poll occasionally so the very first paint after it's shown again isn't stale.
const BACKGROUND_POLL_INTERVAL_MS = 60_000;

// Exponential backoff for failed polls (panel unreachable, network blip, ...), capped so a long
// outage never turns into runaway retries — worst case one request every 60s until recovery.
const BACKOFF_START_MS = 5_000;
const BACKOFF_MAX_MS = 60_000;

// A slow, subtle drift of the whole grid to reduce the risk of static-image burn-in on a display
// left showing the same layout for weeks — cheap (pure CSS, no JS/state) and imperceptible to a
// glance, but keeps individual pixels from being lit identically for a very long time.
const drift = keyframes`
    0% { transform: translate(0, 0); }
    25% { transform: translate(3px, -2px); }
    50% { transform: translate(-2px, 2px); }
    75% { transform: translate(2px, 3px); }
    100% { transform: translate(0, 0); }
`;

const Page = styled.div`
    ${tw`min-h-screen w-full bg-neutral-900 text-neutral-50 p-4 lg:p-6`};
`;

const Drifting = styled.div`
    animation: ${drift} 90s ease-in-out infinite;
`;

const ReconnectBanner = styled.div`
    ${tw`fixed top-0 inset-x-0 z-50 bg-yellow-500 text-yellow-950 text-sm lg:text-base font-semibold py-2 px-4 flex items-center justify-center gap-2`};
`;

const formatClock = (date: Date): string => date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

export default () => {
    const [data, setData] = useState<WallboardStatus | null>(null);
    const [lastSuccessAt, setLastSuccessAt] = useState<Date | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [now, setNow] = useState(new Date());

    // Refs, not state — these drive setTimeout scheduling and must never trigger a re-render
    // themselves, or the polling loop would restart every time it fires.
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>();
    const backoffRef = useRef(BACKOFF_START_MS);
    const hiddenRef = useRef(document.hidden);
    const mountedRef = useRef(true);

    useEffect(() => {
        // A wallboard is read on a TV/monitor in a dim room — force dark regardless of whatever
        // this browser profile happens to have saved (or never set up at all), without touching
        // that saved preference so the rest of the app is unaffected if this tab is ever reused.
        const previous = getThemePreference();
        applyThemePreference('dark');

        return () => {
            applyThemePreference(previous);
        };
    }, []);

    useEffect(() => {
        mountedRef.current = true;

        const scheduleNext = (delay: number) => {
            timeoutRef.current = setTimeout(poll, delay);
        };

        function poll() {
            getWallboard()
                .then((result) => {
                    if (!mountedRef.current) return;

                    setData(result);
                    setError(null);
                    setLastSuccessAt(new Date());
                    backoffRef.current = BACKOFF_START_MS;

                    scheduleNext(hiddenRef.current ? BACKGROUND_POLL_INTERVAL_MS : POLL_INTERVAL_MS);
                })
                .catch((err) => {
                    if (!mountedRef.current) return;

                    // eslint-disable-next-line no-console
                    console.error(err);
                    setError(httpErrorToHuman(err));

                    const delay = Math.min(backoffRef.current, BACKOFF_MAX_MS);
                    backoffRef.current = Math.min(backoffRef.current * 2, BACKOFF_MAX_MS);
                    scheduleNext(delay);
                });
        }

        poll();

        const onVisibilityChange = () => {
            hiddenRef.current = document.hidden;

            // Coming back into view: poll immediately rather than waiting out whatever interval
            // was scheduled while backgrounded, so the board doesn't sit stale after someone
            // walks back up to it.
            if (!document.hidden) {
                if (timeoutRef.current) clearTimeout(timeoutRef.current);
                poll();
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);

        // A visible on-screen clock also doubles as a live "this page is not frozen" indicator —
        // if the clock stops moving, the tab itself has hung, independent of API connectivity.
        const clockInterval = setInterval(() => setNow(new Date()), 30_000);

        return () => {
            mountedRef.current = false;
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
            clearInterval(clockInterval);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, []);

    return (
        <Page>
            {error && (
                <ReconnectBanner>
                    <FontAwesomeIcon icon={faExclamationTriangle} />
                    Lost connection to the panel — retrying
                    {lastSuccessAt && <>&nbsp;(showing data as of {formatClock(lastSuccessAt)})</>}
                </ReconnectBanner>
            )}

            <div css={tw`flex items-center justify-between mb-4 lg:mb-6`}>
                <h1 css={tw`text-2xl lg:text-3xl font-bold`}>Wallboard</h1>
                <div css={tw`flex items-center gap-3 text-neutral-400 text-sm lg:text-base`}>
                    {!error && <FontAwesomeIcon icon={faSync} css={tw`opacity-50`} />}
                    <span>{formatClock(now)}</span>
                </div>
            </div>

            {!data ? (
                <Spinner centered size={'large'} />
            ) : data.areas.length === 0 ? (
                <p css={tw`text-neutral-400`}>No areas to show.</p>
            ) : (
                <Drifting css={tw`grid grid-cols-1 lg:grid-cols-2 gap-4`}>
                    {data.areas.map((area) => (
                        <AreaWallboardCard key={area.uuid} area={area} />
                    ))}
                </Drifting>
            )}
        </Page>
    );
};
