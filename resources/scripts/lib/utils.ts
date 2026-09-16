import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

// Combines class names, resolving conflicting Tailwind classes in favour of the last one.
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
