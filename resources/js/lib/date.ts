/**
 * Today's date as a local-timezone YYYY-MM-DD string.
 *
 * The HTML <input type="date"> expects the value in YYYY-MM-DD. Building it
 * from the local calendar (not `new Date().toISOString()`, which is UTC) keeps
 * the default date aligned with the user's own "today".
 */
export function todayLocal(date: Date = new Date()): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}
