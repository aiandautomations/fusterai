import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import DOMPurify from 'dompurify';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function sanitizeHtml(html: string): string {
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: [
            'p',
            'br',
            'b',
            'i',
            'u',
            'em',
            'strong',
            'a',
            'ul',
            'ol',
            'li',
            'blockquote',
            'code',
            'pre',
            'h1',
            'h2',
            'h3',
            'span',
            'div',
        ],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class'],
        ALLOW_DATA_ATTR: false,
    });
}

export function getInitials(name?: string | null) {
    if (!name) return '?';

    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return initials || '?';
}
