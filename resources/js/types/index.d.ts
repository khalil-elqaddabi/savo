export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    locale?: string;
    theme?: string;
    currency?: string;
    two_factor_enabled?: boolean;
    has_google_link?: boolean;
}

export interface LocaleOption {
    code: string;
    name: string;
    dir: string;
}

export interface AppInfo {
    name: string;
    locale: string;
    dir: string;
    isRtl: boolean;
    theme: string;
    supportedLocales: LocaleOption[];
    currency: string;
    aiEnabled: boolean;
}

export interface AccountOption {
    id: number;
    name: string;
    type: string;
    balance: string;
}

export interface CategoryOption {
    id: number;
    name: string;
    type: 'income' | 'expense';
    icon?: string | null;
    color?: string | null;
}

export interface SharedProps {
    auth: { user: User | null };
    app: AppInfo;
    translations: Record<string, string>;
    flash: {
        success?: string | null;
        error?: string | null;
        status?: string | null;
    };
    ziggy: Record<string, unknown>;
    [key: string]: unknown;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & SharedProps;
