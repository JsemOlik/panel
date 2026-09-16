// The primary colors users can pick from under Account > Appearance, in the order they are shown.
// Each color only needs its base (500) shade as a 6-digit hex value; the lighter and darker shades
// are generated from it. Changing `defaultPrimaryColor` changes the color for everyone who hasn't
// picked one.
export interface PrimaryColor {
    id: string;
    name: string;
    value: string;
}

export const primaryColors: PrimaryColor[] = [
    { id: 'blue', name: 'Blue', value: '#155dfc' },
    { id: 'indigo', name: 'Indigo', value: '#4f39f6' },
    { id: 'violet', name: 'Violet', value: '#7f22fe' },
    { id: 'purple', name: 'Purple', value: '#8a4cf5' },
    { id: 'fuchsia', name: 'Fuchsia', value: '#c800de' },
    { id: 'pink', name: 'Pink', value: '#e60076' },
    { id: 'rose', name: 'Rose', value: '#ec003f' },
    { id: 'red', name: 'Red', value: '#e7000b' },
    { id: 'orange', name: 'Orange', value: '#f54900' },
    { id: 'amber', name: 'Amber', value: '#fe9a00' },
    { id: 'green', name: 'Green', value: '#00a63e' },
    { id: 'emerald', name: 'Emerald', value: '#009966' },
    { id: 'teal', name: 'Teal', value: '#009689' },
    { id: 'cyan', name: 'Cyan', value: '#0092b8' },
    { id: 'sky', name: 'Sky', value: '#0084d1' },
    { id: 'slate', name: 'Slate', value: '#45556c' },
];

export const defaultPrimaryColor = 'purple';
