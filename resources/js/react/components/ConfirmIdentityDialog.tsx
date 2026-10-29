import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { useHttp } from '@saucebase/react/hooks/useHttp';
import { useT } from '@saucebase/react/i18n';
import { KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

/**
 * Fresh proof before an account-changing action (adding or removing a passkey,
 * changing the email): the password, or one of the user's passkeys. Either marks the
 * session confirmed for `auth.password_timeout`, so the action runs straight after.
 */
export function ConfirmIdentityDialog({
    open,
    onOpenChange,
    onConfirmed,
    hasPasskeys = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirmed: () => void;
    /** Offer "Use a passkey" — only when the user has one to use. */
    hasPasskeys?: boolean;
}) {
    const t = useT();
    // Any failure besides a wrong password (throttled, expired session, offline)
    // shows under the field rather than leaving the dialog silent.
    const form = useHttp({ password: '' }, (message, http) =>
        http.setError('password', t(message)),
    );

    const done = () => {
        form.reset();
        onOpenChange(false);
        onConfirmed();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // JSON, not an Inertia visit: a visit reloads the settings panel, and that
        // reload races the passkey ceremony's challenge in the session.
        form.post(route('password.confirm.store'), { onSuccess: done });
    };

    const {
        verify,
        isLoading: isVerifying,
        errorInstance,
        isSupported,
    } = usePasskeyVerify({
        routes: {
            options: route('settings.passkeys.confirm.options'),
            submit: route('settings.passkeys.confirm'),
        },
        onSuccess: done,
    });

    const passkeyError =
        errorInstance?.name === 'UserCancelledError'
            ? t('Passkey confirmation was cancelled.')
            : errorInstance?.message;

    const busy = form.processing || isVerifying;

    // Same layout as the app's confirm dialog (DynamicDialog), plus the password.
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="overflow-hidden p-0 sm:max-w-sm"
                showCloseButton={false}
                data-testid="confirm-identity-dialog"
            >
                <form
                    id="confirm-identity-form"
                    className="bg-background flex flex-col items-center p-6 text-center"
                    onSubmit={submit}
                >
                    <div className="bg-primary/10 text-primary mb-4 flex size-14 items-center justify-center rounded-xl">
                        <ShieldCheck className="size-7" />
                    </div>
                    <DialogHeader className="sm:text-center">
                        <DialogTitle>{t("Confirm it's you")}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'This changes how you sign in. Confirm your password to continue.',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <Input
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        type="password"
                        name="password"
                        className="mt-4"
                        autoComplete="current-password"
                        aria-label={t('Password')}
                        placeholder={t('Password')}
                        disabled={busy}
                        data-testid="confirm-identity-password"
                    />
                    {form.errors.password && (
                        <FieldError
                            className="mt-2 self-start"
                            data-testid="confirm-identity-error"
                        >
                            {form.errors.password}
                        </FieldError>
                    )}

                    {hasPasskeys && isSupported && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-4 w-full"
                                disabled={busy}
                                data-testid="confirm-identity-passkey"
                                onClick={() => void verify()}
                            >
                                {isVerifying ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <KeyRound className="size-4" />
                                )}
                                {t('Use a passkey')}
                            </Button>
                            {passkeyError && (
                                <FieldError className="mt-2 self-start">
                                    {passkeyError}
                                </FieldError>
                            )}
                        </>
                    )}

                    <p className="text-muted-foreground mt-4 text-xs">
                        {t(
                            'No password? Sign out and use "Forgot password" to set one.',
                        )}
                    </p>
                </form>
                <div className="bg-muted/50 border-t p-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Button
                            variant="outline"
                            className="dark:hover:bg-accent dark:hover:text-accent-foreground w-full"
                            disabled={busy}
                            data-testid="confirm-identity-cancel"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            form="confirm-identity-form"
                            className="w-full"
                            disabled={!form.data.password || busy}
                            data-testid="confirm-identity-submit"
                        >
                            {form.processing && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {t('Confirm')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
