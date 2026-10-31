/**
 * What to tell the user when adding a passkey fails, or null to say nothing.
 *
 * Closing the browser's own prompt is a choice, not something to report. The
 * package's "already registered" message is English only and says nothing about
 * what to do next: the browser refuses a second passkey on the same authenticator.
 */
export function passkeyErrorMessage(
    error: { name: string; message: string } | null | undefined,
    t: (key: string) => string,
): string | null {
    if (!error || error.name === 'UserCancelledError') {
        return null;
    }

    if (error.name === 'PasskeyExistsError') {
        return t(
            'This device already has a passkey. Add one from another device or a password manager.',
        );
    }

    return error.message;
}
