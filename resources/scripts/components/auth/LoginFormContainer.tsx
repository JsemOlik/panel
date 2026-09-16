import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import { BrandIcon } from '@/components/elements/BrandLogo';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
    subtitle?: string;
};

// 4CAMPS brand palette for the authentication screens.
const colors = {
    background: '#050a17',
    primary: 'rgb(var(--color-primary-500))',
    primaryHover: 'rgb(var(--color-primary-500) / 0.9)',
    glow: 'rgb(var(--color-primary-500) / 0.1)',
    foreground: '#f4f7fa',
    muted: '#90a1b9',
};

const Wrapper = styled.div`
    ${tw`relative grid min-h-screen place-items-center overflow-hidden p-6`};
    background-color: ${colors.background};
    color: ${colors.foreground};
`;

const Glow = styled.div`
    ${tw`pointer-events-none absolute left-1/2 rounded-full`};
    top: -10rem;
    width: 36rem;
    height: 36rem;
    transform: translateX(-50%);
    background-color: ${colors.glow};
    filter: blur(64px);
`;

// The buttons and links are shared with the rest of the panel, so restyle them here
// with enough specificity to beat their default styles.
const Content = styled.div`
    ${tw`relative w-full`};
    max-width: 24rem;

    && button[type='submit'] {
        ${tw`h-10 px-6 py-0 rounded-lg border-0 text-sm font-medium normal-case tracking-normal`};
        background-color: ${colors.primary};
        color: #ffffff;

        &:hover:not(:disabled) {
            background-color: ${colors.primaryHover};
        }
    }

    && a,
    && [role='button'] {
        ${tw`text-xs no-underline normal-case tracking-normal`};
        color: ${colors.muted};

        &:hover {
            color: ${colors.foreground};
        }
    }
`;

const FooterLink = styled.a`
    ${tw`no-underline`};
    color: ${colors.muted};

    &:hover {
        color: ${colors.foreground};
    }
`;

export default forwardRef<HTMLFormElement, Props>(({ title, subtitle, ...props }, ref) => (
    <Wrapper>
        <Glow aria-hidden={'true'} />
        <Content>
            <div css={tw`flex flex-col items-center gap-4 text-center select-none`}>
                <BrandIcon aria-hidden={'true'} css={tw`block w-10 h-10 flex-shrink-0`} />
                {(title || subtitle) && (
                    <div>
                        {title && (
                            <h1 css={tw`text-2xl font-semibold`} style={{ color: colors.foreground }}>
                                {title}
                            </h1>
                        )}
                        {subtitle && (
                            <p css={tw`mt-1.5 text-sm`} style={{ color: colors.muted }}>
                                {subtitle}
                            </p>
                        )}
                    </div>
                )}
            </div>
            <FlashMessageRender css={tw`mt-8`} />
            <div css={tw`mt-8`}>
                <Form {...props} ref={ref}>
                    {props.children}
                </Form>
            </div>
            <p css={tw`mt-10 text-center text-xs`} style={{ color: colors.muted }}>
                <FooterLink href={'https://4camps.cz'} target={'_blank'} rel={'noopener noreferrer'}>
                    4CAMPS
                </FooterLink>
                &nbsp;&middot;&nbsp;&copy; Zvědavý medvěd, z. s.
                <br />
                &copy; 2015 - {new Date().getFullYear()}&nbsp;
                <FooterLink href={'https://pterodactyl.io'} target={'_blank'} rel={'noopener nofollow noreferrer'}>
                    Pterodactyl Software
                </FooterLink>
            </p>
        </Content>
    </Wrapper>
));
