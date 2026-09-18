import React from 'react';
import tw from 'twin.macro';
import { faLayerGroup } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { WallboardArea } from '@/api/wallboard/getWallboard';
import ServerWallboardTile from '@/components/wallboard/ServerWallboardTile';

export default ({ area }: { area: WallboardArea }) => {
    const members = area.members.filter((member) => member.role === 'member');
    const proxy = area.members.find((member) => member.role === 'proxy');

    return (
        <section css={tw`bg-neutral-800 border border-neutral-600 rounded-xl p-4 lg:p-5`}>
            <div css={tw`flex items-center gap-2 mb-3`}>
                <FontAwesomeIcon icon={faLayerGroup} css={tw`text-neutral-400`} />
                <h2 css={tw`text-lg lg:text-xl font-bold text-neutral-50 break-words`}>{area.name}</h2>
            </div>

            <div css={tw`grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3`}>
                {members.map((member) => (
                    <ServerWallboardTile key={member.uuid} server={member} />
                ))}
            </div>

            {proxy && (
                <div css={tw`mt-3 pt-3 border-t border-neutral-600`}>
                    <ServerWallboardTile server={proxy} />
                </div>
            )}

            {area.members.length === 0 && <p css={tw`text-sm text-neutral-400`}>No servers assigned.</p>}
        </section>
    );
};
