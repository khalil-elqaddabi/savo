import type { SVGProps } from 'react';

export const IconSpinner = (p: SVGProps<SVGSVGElement> & { size?: number }) => (
    <svg
        width={p.size ?? 20}
        height={p.size ?? 20}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        className={p.className}
    >
        <circle cx="12" cy="12" r="9" strokeOpacity="0.25" />
        <path d="M21 12a9 9 0 0 0-9-9" strokeLinecap="round" />
    </svg>
);

export { IconCheck, IconChevronDown, IconPlus, IconX } from '../Icons';
