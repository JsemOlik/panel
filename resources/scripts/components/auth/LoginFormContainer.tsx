import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
    subtitle?: string;
};

// 4CAMPS brand palette for the authentication screens.
const colors = {
    background: '#050a14',
    primary: '#8a4cf5',
    primaryHover: 'rgba(138, 76, 245, 0.9)',
    glow: 'rgba(138, 76, 245, 0.1)',
    foreground: '#f8fafc',
    muted: '#94a3b8',
    input: 'rgba(255, 255, 255, 0.04)',
    border: 'rgba(255, 255, 255, 0.12)',
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

// The form fields and buttons are shared with the rest of the panel, so restyle them here
// with enough specificity to beat their default (light/blue) styles.
const Content = styled.div`
    ${tw`relative w-full`};
    max-width: 24rem;

    && label {
        ${tw`text-sm normal-case font-medium mb-2`};
        color: ${colors.foreground};
    }

    && input:not([type='checkbox']):not([type='radio']) {
        ${tw`h-10 px-3 py-2 border rounded-md text-sm shadow-none`};
        background-color: ${colors.input};
        border-color: ${colors.border};
        color: ${colors.foreground};

        &:not(:disabled):not(:read-only):focus {
            ${tw`shadow-none`};
            border-color: ${colors.primary};
            box-shadow: 0 0 0 3px rgba(138, 76, 245, 0.3);
        }
    }

    && .input-help {
        ${tw`mt-1.5 text-xs`};
        color: ${colors.muted};

        &.error {
            color: #f87171;
        }
    }

    && button[type='submit'] {
        ${tw`h-10 px-6 py-0 rounded-md border-0 text-sm font-medium normal-case tracking-normal`};
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
                <img
                    src={'/assets/svgs/4camps-ptero-icon.svg'}
                    alt={''}
                    aria-hidden={'true'}
                    css={tw`block w-10 h-10 flex-shrink-0`}
                />
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
