import React, { createContext, useContext, useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';

export const PageFooter = () => (
    <ContentContainer css={tw`mb-4`}>
        <p css={tw`text-center text-neutral-500 text-xs`}>
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://pterodactyl.io'}
                target={'_blank'}
                css={tw`no-underline text-neutral-500 hover:text-neutral-300`}
            >
                Pterodactyl&reg;
            </a>
            &nbsp;&copy; 2015 - {new Date().getFullYear()}
        </p>
    </ContentContainer>
);

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
}

// Set by layouts that already provide the page container and footer (e.g. the account settings), so the
// pages rendered inside them only output their content.
export const NestedPageContext = createContext(false);

const PageContentBlock: React.FC<PageContentBlockProps> = ({ title, showFlashKey, className, children }) => {
    const nested = useContext(NestedPageContext);

    useEffect(() => {
        if (title) {
            document.title = title;
        }
    }, [title]);

    if (nested) {
        return (
            <div className={className}>
                {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                {children}
            </div>
        );
    }

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer css={tw`my-4 sm:my-10`} className={className}>
                    {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                    {children}
                </ContentContainer>
                <PageFooter />
            </>
        </CSSTransition>
    );
};

export default PageContentBlock;
