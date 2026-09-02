import type { SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement> & { size?: number };

function base(props: IconProps): SVGProps<SVGSVGElement> {
    const { size = 20, ...rest } = props;
    return {
        width: size,
        height: size,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 2,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        ...rest,
    };
}

export const IconPlus = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M12 5v14M5 12h14" />
    </svg>
);
export const IconMinus = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M5 12h14" />
    </svg>
);
export const IconArrowRight = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M5 12h14" />
        <path d="m12 5 7 7-7 7" />
    </svg>
);
export const IconArrowLeft = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M19 12H5" />
        <path d="m12 19-7-7 7-7" />
    </svg>
);
export const IconArrowDownRight = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M7 7l10 10" />
        <path d="M17 7v10H7" />
    </svg>
);
export const IconArrowUpRight = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M7 17 17 7" />
        <path d="M7 7h10v10" />
    </svg>
);
export const IconTransfer = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M7 7h13" />
        <path d="m18 10 3-3-3-3" />
        <path d="M17 17H4" />
        <path d="m6 14-3 3 3 3" />
    </svg>
);
export const IconWallet = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
        <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
        <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
    </svg>
);
export const IconBank = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m3 10 9-7 9 7" />
        <path d="M5 21h14" />
        <path d="M6 10v8M10 10v8M14 10v8M18 10v8" />
        <path d="M4 21h16" />
    </svg>
);
export const IconPiggy = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M19 11c0-3.9-3.1-7-7-7-2.1 0-4 1-5.3 2.5" />
        <path d="M5 11H3l1.5-2.5" />
        <path d="M19 11a4 4 0 0 1 0 8h-8a5 5 0 0 1-4-2H3l-.5-2.5" />
        <circle cx="8" cy="14" r="1" />
        <circle cx="14" cy="14" r="1" />
        <path d="M8 18v3M14 18v3" />
    </svg>
);
export const IconBell = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
    </svg>
);
export const IconDownload = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <path d="M7 10l5 5 5-5" />
        <path d="M12 15V3" />
    </svg>
);
export const IconUpload = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <path d="M17 8l-5-5-5 5" />
        <path d="M12 3v12" />
    </svg>
);
export const IconRefresh = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 12a9 9 0 1 1-2.6-6.4" />
        <path d="M21 3v6h-6" />
    </svg>
);
export const IconCard = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="2" y="5" width="20" height="14" rx="2" />
        <path d="M2 10h20" />
    </svg>
);
export const IconCash = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="2" y="6" width="20" height="12" rx="2" />
        <circle cx="12" cy="12" r="3" />
        <path d="M6 12h.01M18 12h.01" />
    </svg>
);
export const IconPiggyBank = IconPiggy;
export const IconSmartphone = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="6" y="2" width="12" height="20" rx="2" />
        <path d="M11 18h2" />
    </svg>
);
export const IconHome = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
        <path d="M9 22V12h6v10" />
    </svg>
);
export const IconGrid = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="3" y="3" width="7" height="7" rx="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" />
    </svg>
);
export const IconReceipt = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1-2-1Z" />
        <path d="M8 8h8M8 12h8M8 16h5" />
    </svg>
);
export const IconTarget = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="5" />
        <circle cx="12" cy="12" r="1" />
    </svg>
);
export const IconRepeat = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m17 2 4 4-4 4" />
        <path d="M3 11v-1a4 4 0 0 1 4-4h14" />
        <path d="m7 22-4-4 4-4" />
        <path d="M21 13v1a4 4 0 0 1-4 4H3" />
    </svg>
);
export const IconChart = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M3 3v18h18" />
        <path d="M7 16v-4M12 16V8M17 16v-7" />
    </svg>
);
export const IconSparkle = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M12 3v4M12 17v4M3 12h4M17 12h4" />
        <path d="M12 8.5 13.5 11 16 12.5 13.5 14 12 16.5 10.5 14 8 12.5 10.5 11Z" />
    </svg>
);
export const IconUser = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" />
    </svg>
);
export const IconShield = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5Z" />
        <path d="M9 12l2 2 4-4" />
    </svg>
);
export const IconSun = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </svg>
);
export const IconMoon = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
    </svg>
);
export const IconGlobe = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18" />
        <path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18Z" />
    </svg>
);
export const IconLogout = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <path d="m16 17 5-5-5-5" />
        <path d="M21 12H9" />
    </svg>
);
export const IconSearch = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="11" cy="11" r="7" />
        <path d="m21 21-4.3-4.3" />
    </svg>
);
export const IconFilter = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M3 6h18M6 12h12M10 18h4" />
    </svg>
);
export const IconTrash = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M3 6h18" />
        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
        <path d="M10 11v6M14 11v6" />
    </svg>
);
export const IconEdit = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M12 20h9" />
        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
    </svg>
);
export const IconCheck = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M20 6 9 17l-5-5" />
    </svg>
);
export const IconX = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M18 6 6 18M6 6l12 12" />
    </svg>
);
export const IconChevronDown = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m6 9 6 6 6-6" />
    </svg>
);
export const IconMenu = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
);
export const IconSend = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m22 2-7 20-4-9-9-4Z" />
        <path d="M22 2 11 13" />
    </svg>
);
export const IconInfo = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 16v-5M12 8h.01" />
    </svg>
);
export const IconCalendar = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <path d="M16 2v4M8 2v4M3 10h18" />
    </svg>
);
export const IconTrendingUp = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m23 6-9.5 9.5-5-5L1 18" />
        <path d="M17 6h6v6" />
    </svg>
);
export const IconTrendingDown = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m23 18-9.5-9.5-5 5L1 6" />
        <path d="M17 18h6v-6" />
    </svg>
);
export const IconBuilding = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="4" y="2" width="16" height="20" rx="2" />
        <path d="M9 22v-4h6v4" />
        <path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01" />
    </svg>
);
export const IconAlert = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m10.3 3.9 8.5 14.7a1.5 1.5 0 0 1-1.3 2.3H6.5a1.5 1.5 0 0 1-1.3-2.3L13.7 3.9a1.5 1.5 0 0 1 2.6 0Z" />
        <path d="M12 9v4M12 17h.01" />
    </svg>
);
export const IconChevronRight = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m9 18 6-6-6-6" />
    </svg>
);
export const IconChevronLeft = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="m15 18-6-6 6-6" />
    </svg>
);
export const IconMore = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="5" cy="12" r="1" />
        <circle cx="12" cy="12" r="1" />
        <circle cx="19" cy="12" r="1" />
    </svg>
);
export const IconClock = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
    </svg>
);
export const IconLock = (p: IconProps) => (
    <svg {...base(p)}>
        <rect x="4" y="11" width="16" height="10" rx="2" />
        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </svg>
);
export const IconPalette = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M12 22a10 10 0 1 1 10-10c0 2.2-1.8 3.5-3.5 3.5H16a2 2 0 0 0-1.5 3.3c.6.7.2 2.2-2.5 2.2Z" />
        <path d="M7 12h.01M10 8h.01M14 8h.01M17 12h.01" />
    </svg>
);
export const IconChat = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M21 12a8 8 0 0 1-8 8H4l2.2-3.3A8 8 0 1 1 21 12Z" />
        <path d="M8.5 12h.01M12 12h.01M15.5 12h.01" />
    </svg>
);
export const IconFlag = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M5 21V4" />
        <path d="M5 4h11l-2 3 2 3H5" />
    </svg>
);
export const IconSliders = (p: IconProps) => (
    <svg {...base(p)}>
        <path d="M4 6h16M4 12h16M4 18h16" />
        <circle cx="9" cy="6" r="2" />
        <circle cx="15" cy="12" r="2" />
    </svg>
);
export const IconSettings = (p: IconProps) => (
    <svg {...base(p)}>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
    </svg>
);
