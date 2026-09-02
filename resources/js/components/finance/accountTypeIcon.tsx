import type { ReactElement } from 'react';
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconBank,
    IconCard,
    IconCash,
    IconPiggyBank,
    IconSmartphone,
    IconTransfer,
    IconWallet,
} from '../Icons';

export const accountTypeMeta: Record<
    string,
    { icon: (p: any) => ReactElement; label: string; hue: string }
> = {
    cash: { icon: IconCash, label: 'Cash', hue: '#2f9e6e' },
    bank: { icon: IconBank, label: 'Bank Account', hue: '#3a7bd5' },
    savings: {
        icon: IconPiggyBank,
        label: 'Savings Account',
        hue: '#7c63d6',
    },
    credit_card: {
        icon: IconCard,
        label: 'Credit Card',
        hue: '#c2701a',
    },
    digital_wallet: {
        icon: IconSmartphone,
        label: 'Digital Wallet',
        hue: '#2ea7b5',
    },
};

export function AccountTypeIcon({
    type,
    size = 22,
    className = '',
}: {
    type: string;
    size?: number;
    className?: string;
}) {
    const meta = accountTypeMeta[type];
    const Icon = meta?.icon ?? IconWallet;
    return <Icon size={size} className={className} />;
}

export function transactionTypeIcon(type: string) {
    switch (type) {
        case 'income':
            return {
                icon: IconArrowDownRight,
                color: 'text-mint',
                meta: 'income',
            };
        case 'expense':
            return {
                icon: IconArrowUpRight,
                color: 'text-coral',
                meta: 'expense',
            };
        default:
            return {
                icon: IconTransfer,
                color: 'text-accent',
                meta: 'transfer',
            };
    }
}
