/**
 * Pure, framework-free helpers for the client-side App Lock.
 *
 * Kept free of browser/React APIs so the exact same functions can be unit
 * tested with Node's built-in test runner (zero extra dependencies) and used
 * by the React provider. The PIN is never handled here as plain text beyond
 * hashing, and never logged or exposed.
 */

export interface StoredLock {
    salt: string;
    hash: string;
}

export async function sha256Hex(input: string): Promise<string> {
    const data = new TextEncoder().encode(input);
    const digest = await crypto.subtle.digest('SHA-256', data);
    return Array.from(new Uint8Array(digest))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

export function randomHex(bytes: number): string {
    const arr = new Uint8Array(bytes);
    crypto.getRandomValues(arr);
    return Array.from(arr)
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

/** Hash a PIN together with a salt. */
export async function hashPin(salt: string, pin: string): Promise<string> {
    return sha256Hex(salt + pin);
}

/** Verify a PIN against a stored salted hash (pure, deterministic given salt). */
export async function verifyPin(
    stored: StoredLock,
    pin: string,
): Promise<boolean> {
    return (await hashPin(stored.salt, pin)) === stored.hash;
}

/** Build a salted hash entry for a PIN, using a fresh random salt. */
export async function createEntry(pin: string): Promise<StoredLock> {
    const salt = randomHex(16);
    return { salt, hash: await hashPin(salt, pin) };
}
