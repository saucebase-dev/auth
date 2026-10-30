/**
 * Whether the session's password confirmation still holds, from the server's
 * `password_confirmed_until` (unix seconds). Asks 30s early, since the browser's
 * clock may run behind the server's.
 */
export function isConfirmed(
    until: number | null | undefined,
    now = Date.now(),
): boolean {
    return until != null && now / 1000 < until - 30;
}

/** Until when a confirmation made now holds: the server's `password_timeout` on. */
export function confirmedFor(timeout: number, now = Date.now()): number {
    return now / 1000 + timeout;
}
