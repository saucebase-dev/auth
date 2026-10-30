import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { useT } from '@saucebase/react/i18n';
import { KeyRound, Loader2 } from 'lucide-react';

interface PasskeyLoginButtonProps {
    /** Read when the ceremony starts, so it follows the remember-me box. */
    remember?: boolean;
}

/**
 * Signs in with a passkey instead of the email and password above it.
 *
 * The client talks to the server with `fetch`, not Inertia, and the session is
 * regenerated on success — so the redirect is a full page load rather than an
 * Inertia visit. That also takes the auth modal down with the page behind it.
 */
export default function PasskeyLoginButton({
    remember,
}: PasskeyLoginButtonProps) {
    const t = useT();

    const { verify, isLoading, errorInstance, isSupported } = usePasskeyVerify({
        remember: remember ?? false,
        routes: {
            options: route('auth.passkeys.options'),
            submit: route('auth.passkeys.login'),
        },
        onSuccess: (response) => {
            window.location.assign(response.redirect ?? '/');
        },
    });

    if (!isSupported) {
        return null;
    }

    // Closing the browser's own prompt is a choice, not something to report.
    const error =
        errorInstance?.name === 'UserCancelledError'
            ? null
            : errorInstance?.message;

    return (
        <div className="space-y-2">
            <Button
                type="button"
                variant="outline"
                className="w-full"
                data-testid="passkey-login-button"
                disabled={isLoading}
                onClick={verify}
            >
                {isLoading ? (
                    <Loader2 className="size-4 animate-spin" />
                ) : (
                    <KeyRound className="size-4" />
                )}
                {t('Sign in with a passkey')}
            </Button>
            {error && (
                <FieldError data-testid="passkey-login-error">
                    {error}
                </FieldError>
            )}
        </div>
    );
}
