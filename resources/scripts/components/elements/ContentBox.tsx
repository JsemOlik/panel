import React, { useContext } from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import tw from 'twin.macro';
import { NestedPageContext } from '@/components/elements/PageContentBlock';

type Props = Readonly<
    React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement> & {
        title?: string;
        borderColor?: string;
        showFlashes?: string | boolean;
        showLoadingOverlay?: boolean;
    }
>;

const ContentBox = ({ title, borderColor, showFlashes, showLoadingOverlay, children, ...props }: Props) => {
    // Within a layout that has its own page heading, match the size of its section headings.
    const nested = useContext(NestedPageContext);

    return (
        <div {...props}>
            {title &&
                (nested ? (
                    <h3 css={tw`text-neutral-50 mb-3 text-base font-semibold`}>{title}</h3>
                ) : (
                    <h2 css={tw`text-neutral-300 mb-4 px-4 text-2xl`}>{title}</h2>
                ))}
            {showFlashes && (
                <FlashMessageRender byKey={typeof showFlashes === 'string' ? showFlashes : undefined} css={tw`mb-4`} />
            )}
            <div css={[tw`bg-neutral-700 p-4 rounded-xl shadow-lg relative`, !!borderColor && tw`border-t-4`]}>
                <SpinnerOverlay visible={showLoadingOverlay || false} />
                {children}
            </div>
        </div>
    );
};

export default ContentBox;
