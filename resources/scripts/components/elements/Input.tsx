import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';

// Text inputs and textareas live in @/components/ui/input and @/components/ui/textarea;
// this component is only used for native checkboxes and radio buttons.
const checkboxStyle = css`
    ${tw`bg-neutral-500 cursor-pointer appearance-none inline-block align-middle select-none flex-shrink-0 w-4 h-4 text-primary-400 border border-neutral-300 rounded-sm`};
    color-adjust: exact;
    background-origin: border-box;
    transition: all 75ms linear, box-shadow 25ms linear;

    &:checked {
        ${tw`border-transparent bg-no-repeat bg-center`};
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M5.707 7.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L7 8.586 5.707 7.293z'/%3e%3c/svg%3e");
        background-color: currentColor;
        background-size: 100% 100%;
    }

    &:focus {
        ${tw`outline-none border-primary-300`};
        box-shadow: 0 0 0 1px rgb(var(--color-primary-500) / 0.35);
    }
`;

const Input = styled.input`
    &[type='checkbox'],
    &[type='radio'] {
        ${checkboxStyle};

        &[type='radio'] {
            ${tw`rounded-full`};
        }
    }
`;

export default Input;
