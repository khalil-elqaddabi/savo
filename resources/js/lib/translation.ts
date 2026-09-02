import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

type Dictionary = Record<string, string>;

/**
 * Latest translation dictionary snapshot.
 *
 * React hooks may only be called during render. `useTrans()` therefore reads
 * the shared dictionary via `usePage()` at render time and also keeps a module
 * snapshot so that the returned `t` (and the standalone `translate`) work
 * correctly when invoked from event handlers / timers, where calling `usePage()`
 * would throw an "Invalid hook call".
 */
let dictionary: Dictionary = {};

/**
 * Read the translation dictionary shared from the server.
 *
 * NOTE: calls the `usePage()` React hook, so it may only be used during render.
 */
export function translations(): Dictionary {
    const dict = usePage<PageProps>().props.translations ?? {};
    dictionary = dict;
    return dict;
}

function render(
    dict: Dictionary,
    key: string,
    replace: Record<string, string | number> = {},
): string {
    let out = dict[key] ?? key;

    for (const [k, v] of Object.entries(replace)) {
        out = out.replaceAll(`{${k}}`, String(v));
    }

    return out;
}

/**
 * Translate a key against the most recently rendered dictionary.
 * Safe to call from event handlers (does not use React hooks).
 */
export function translate(
    key: string,
    replace: Record<string, string | number> = {},
): string {
    return render(dictionary, key, replace);
}

/**
 * React hook returning a `t` function. The dictionary is captured at render
 * time, so the returned function is safe to call from event handlers.
 */
export function useTrans() {
    const dict = translations();
    return (key: string, replace: Record<string, string | number> = {}) =>
        render(dict, key, replace);
}
