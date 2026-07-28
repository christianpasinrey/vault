/** How far ahead an expiry date starts being worth a warning. */
export const EXPIRY_WARNING_DAYS = 30;

export interface ExpiryStatus {
    days: number;
    expired: boolean;
    soon: boolean;
}

/**
 * Reads the `expires_at` field of an item.
 *
 * Returns null for anything that is not a usable date, because a warning built
 * on a misread string is worse than no warning at all.
 */
export function expiryStatus(value: string | undefined, now = new Date()): ExpiryStatus | null {
    if (value === undefined || value.trim() === '') return null;

    const target = new Date(value);
    if (Number.isNaN(target.getTime())) return null;

    const days = Math.ceil((target.getTime() - now.getTime()) / 86_400_000);

    return { days, expired: days < 0, soon: days >= 0 && days <= EXPIRY_WARNING_DAYS };
}

export function expiryMessage(status: ExpiryStatus): string {
    if (status.expired) return `Expired ${Math.abs(status.days)} days ago`;
    if (status.days === 0) return 'Expires today';

    return `Expires in ${status.days} days`;
}
