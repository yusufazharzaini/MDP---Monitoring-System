/**
 * Shared Inertia page props and domain types.
 */

export type BadgeVariant = 'success' | 'danger' | 'warning' | 'info' | 'neutral';

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    position: string | null;
    roles: string[];
    permissions: string[];
}

export interface KpiThreshold {
    name: string;
    target: number;
    warning: number | null;
    critical: number | null;
    unit: string;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
    warning: string | null;
}

export interface SharedPageProps {
    auth: { user: AuthUser | null };
    kpi: Record<string, KpiThreshold>;
    flash: FlashMessages;
    app: { name: string };
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** A Laravel length-aware paginator, as serialised by Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface SelectOption {
    value: string | number;
    label: string;
    variant?: BadgeVariant;
}
