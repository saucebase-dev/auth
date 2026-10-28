import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Form, router, usePage } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { formatDateTime } from '@saucebase/core/lib/dates';
import { useDialog } from '@saucebase/react/hooks/useDialog';
import { useT, useTranslation } from '@saucebase/react/i18n';
import { Eye, EyeOff, KeyRound, Loader2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { providerIcons } from '../components/providerIcons';

type SocialiteProvider = {
    name: string;
    label: string;
};

type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    last_used_at: string | null;
    created_at: string;
};

type SocialAccount = {
    provider: string;
    last_login_at: string;
    provider_avatar_url?: string;
};

interface SettingsSecurityProps {
    user: {
        has_password?: boolean;
        social_accounts?: SocialAccount[];
    };
    available_providers?: SocialiteProvider[];
    passkeys?: Passkey[];
    can_add_passkeys?: boolean;
}

/**
 * Vue reaches this through the shared `InputField`/`InputPassword` pair, which
 * the React stack has no counterpart for. Kept local rather than promoted to
 * core: three fields on one panel is not yet evidence of a shared component.
 */
function PasswordField({
    name,
    label,
    placeholder,
    autoComplete,
    error,
}: {
    name: string;
    label: string;
    placeholder: string;
    autoComplete: string;
    error?: string;
}) {
    const t = useT();
    const [visible, setVisible] = useState(false);

    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <div className="relative">
                <Input
                    id={name}
                    name={name}
                    type={visible ? 'text' : 'password'}
                    placeholder={placeholder}
                    autoComplete={autoComplete}
                    required
                    data-testid={name}
                    aria-invalid={!!error}
                    className="pr-10"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    data-testid={`${name}-toggle`}
                    aria-label={
                        visible ? t('Hide password') : t('Show password')
                    }
                    className="absolute inset-y-0 right-0 h-full cursor-pointer px-3 py-2 hover:bg-transparent"
                    onClick={() => setVisible((current) => !current)}
                >
                    {visible ? (
                        <EyeOff className="text-muted-foreground size-4" />
                    ) : (
                        <Eye className="text-muted-foreground size-4" />
                    )}
                </Button>
            </div>
            {error && (
                <FieldError data-testid={`${name}-error`} aria-live="polite">
                    {error}
                </FieldError>
            )}
        </Field>
    );
}

/**
 * How the signed-in user proves who they are: their password, their passkeys,
 * and the social accounts that can sign them in without one.
 *
 * Split out of the profile panel because these are credentials rather than a
 * description of a person — a different question to answer, in a different frame
 * of mind.
 */
export default function SettingsSecurity({
    user,
    available_providers,
    passkeys = [],
    can_add_passkeys,
}: SettingsSecurityProps) {
    const { t, locale } = useTranslation();
    const page = usePage();
    const { confirm } = useDialog();

    const [isDisconnecting, setIsDisconnecting] = useState<string | null>(null);
    const [passkeyName, setPasskeyName] = useState('');
    const [isRemovingPasskey, setIsRemovingPasskey] = useState<number | null>(
        null,
    );

    const passkeysEnabled = !!(
        page.props.auth as { passkeys_enabled?: boolean }
    )?.passkeys_enabled;

    /**
     * The ceremony runs over `fetch`, outside Inertia, so nothing reloads the
     * panel by itself. A page reload does: the modal refreshes the active
     * section after any Inertia visit, and the reload also delivers the toast.
     */
    const {
        register,
        isLoading: isAddingPasskey,
        errorInstance: addPasskeyError,
        isSupported: passkeysSupported,
    } = usePasskeyRegister({
        routes: {
            options: route('settings.passkeys.options'),
            submit: route('settings.passkeys.store'),
        },
        onSuccess: () => {
            setPasskeyName('');
            router.reload();
        },
    });

    // Closing the browser's own prompt is a choice, not something to report.
    const addPasskeyErrorMessage =
        addPasskeyError?.name === 'UserCancelledError'
            ? null
            : addPasskeyError?.message;

    /**
     * Shown while the feature is on, and also while the user still holds
     * passkeys after it was switched off — otherwise they could never remove
     * them.
     */
    const showPasskeys = passkeysEnabled || passkeys.length > 0;

    const canAddPasskey =
        passkeysEnabled && passkeysSupported && can_add_passkeys !== false;

    const addPasskey = (event: React.FormEvent) => {
        event.preventDefault();

        const name = passkeyName.trim();

        if (name === '') return;

        void register(name);
    };

    const removePasskey = async (passkey: Passkey) => {
        const confirmed = await confirm({
            title: t('Remove Passkey'),
            description: t(
                'You will no longer be able to sign in with ":name". If it is your only way in, you will need to reset your password to get back into your account.',
                { name: passkey.name },
            ),
            confirmLabel: t('Remove'),
            cancelLabel: t('Cancel'),
            variant: 'destructive',
            align: 'left',
        });

        if (!confirmed) return;

        setIsRemovingPasskey(passkey.id);

        router.delete(route('settings.passkeys.destroy', passkey.id), {
            preserveUrl: true,
            onFinish: () => setIsRemovingPasskey(null),
        });
    };

    const enabledProviders = useMemo<SocialiteProvider[]>(() => {
        const auth = page.props.auth as {
            socialite_providers?: SocialiteProvider[];
        };

        return auth?.socialite_providers ?? [];
    }, [page.props.auth]);

    const enabledProviderNames = useMemo(
        () => new Set(enabledProviders.map((provider) => provider.name)),
        [enabledProviders],
    );

    /**
     * Providers currently enabled, plus any the user is still connected to even
     * though the provider has since been disabled — otherwise a user could never
     * disconnect an account belonging to a turned-off provider.
     */
    const socialiteProviders = useMemo<SocialiteProvider[]>(() => {
        const configuredProviders = new Map(
            (available_providers ?? []).map((provider) => [
                provider.name,
                provider,
            ]),
        );
        const providers = new Map(
            enabledProviders.map((provider) => [provider.name, provider]),
        );

        for (const account of user.social_accounts ?? []) {
            if (!providers.has(account.provider)) {
                providers.set(
                    account.provider,
                    configuredProviders.get(account.provider) ?? {
                        name: account.provider,
                        label: account.provider,
                    },
                );
            }
        }

        return [...providers.values()];
    }, [available_providers, enabledProviders, user.social_accounts]);

    const hasSocialiteProviders =
        route().has('auth.socialite.redirect') && socialiteProviders.length > 0;

    const getConnectedAccount = (providerName: string) =>
        user?.social_accounts?.find(
            (account) => account.provider === providerName,
        );

    /**
     * Confirm through the app-level dialog rather than a nested one: a dialog
     * rendered inside the modal would compete with the modal's own focus trap.
     */
    const initiateDisconnect = async (provider: string) => {
        const confirmed = await confirm({
            title: t('Disconnect Social Account'),
            description: t(
                'Are you sure you want to disconnect this social account? You can reconnect it anytime.',
            ),
            confirmLabel: t('Disconnect'),
            cancelLabel: t('Cancel'),
            variant: 'destructive',
            align: 'left',
        });

        if (!confirmed) return;

        setIsDisconnecting(provider);

        router.delete(route('auth.socialite.disconnect', provider), {
            preserveUrl: true,
            onFinish: () => setIsDisconnecting(null),
        });
    };

    return (
        <div className="space-y-8" data-testid="settings-security-panel">
            <p className="text-muted-foreground text-sm">
                {t(
                    'Your password, passkeys and the accounts that can sign you in',
                )}
            </p>

            {/* Password */}
            <div className="space-y-4">
                <div className="space-y-1">
                    <h3 className="font-medium">{t('Update Password')}</h3>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Ensure your account is using a long, random password to stay secure.',
                        )}
                    </p>
                </div>

                <Form
                    action={route('settings.profile.password.update')}
                    method="put"
                    className="space-y-4"
                    disableWhileProcessing
                    resetOnSuccess
                    options={{ preserveUrl: true }}
                >
                    {({ errors }) => (
                        <>
                            <PasswordField
                                name="current_password"
                                label={t('Current Password')}
                                placeholder={t('Enter your current password')}
                                autoComplete="current-password"
                                error={errors.current_password}
                            />

                            <PasswordField
                                name="password"
                                label={t('New Password')}
                                placeholder={t('Enter your new password')}
                                autoComplete="new-password"
                                error={errors.password}
                            />

                            <PasswordField
                                name="password_confirmation"
                                label={t('Confirm Password')}
                                placeholder={t('Confirm your new password')}
                                autoComplete="new-password"
                                error={errors.password_confirmation}
                            />

                            <div className="flex justify-end pt-2">
                                <Button
                                    type="submit"
                                    data-testid="submit-change-password"
                                >
                                    {t('Update Password')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {/* Passkeys */}
            {showPasskeys && (
                <>
                    <Separator />

                    <div className="space-y-4" data-testid="passkeys-section">
                        <div className="space-y-1">
                            <h3 className="font-medium">{t('Passkeys')}</h3>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Sign in with your fingerprint, face or screen lock instead of a password.',
                                )}
                            </p>
                        </div>

                        {passkeys.length > 0 ? (
                            <div className="space-y-3">
                                {passkeys.map((passkey) => (
                                    <div
                                        key={passkey.id}
                                        className="flex items-center justify-between gap-4 rounded-lg border p-4"
                                        data-testid="passkey"
                                    >
                                        <div className="flex min-w-0 items-center gap-4">
                                            <KeyRound className="size-6 shrink-0" />
                                            <div className="min-w-0">
                                                <p
                                                    className="truncate font-medium"
                                                    data-testid="passkey-name"
                                                >
                                                    {passkey.name}
                                                </p>
                                                <p className="text-muted-foreground text-sm">
                                                    {passkey.authenticator &&
                                                        `${passkey.authenticator} · `}
                                                    {t('Last used')}:{' '}
                                                    {passkey.last_used_at
                                                        ? formatDateTime(
                                                              passkey.last_used_at,
                                                              locale,
                                                          )
                                                        : t('Never')}
                                                </p>
                                            </div>
                                        </div>

                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            data-testid="remove-passkey"
                                            disabled={
                                                isRemovingPasskey === passkey.id
                                            }
                                            onClick={() =>
                                                removePasskey(passkey)
                                            }
                                        >
                                            {isRemovingPasskey ===
                                                passkey.id && (
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                            )}
                                            {t('Remove')}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p
                                className="text-muted-foreground text-sm"
                                data-testid="passkeys-empty"
                            >
                                {t('No passkeys yet.')}
                            </p>
                        )}

                        {canAddPasskey && (
                            <form
                                className="space-y-2"
                                data-testid="add-passkey-form"
                                onSubmit={addPasskey}
                            >
                                <div className="flex gap-2">
                                    <Input
                                        name="passkey_name"
                                        aria-label={t('Passkey name')}
                                        placeholder={t(
                                            'Name this passkey, e.g. Work laptop',
                                        )}
                                        maxLength={255}
                                        required
                                        data-testid="passkey-name-input"
                                        value={passkeyName}
                                        onChange={(event) =>
                                            setPasskeyName(event.target.value)
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        disabled={isAddingPasskey}
                                        data-testid="add-passkey"
                                    >
                                        {isAddingPasskey && (
                                            <Loader2 className="mr-2 size-4 animate-spin" />
                                        )}
                                        {t('Add passkey')}
                                    </Button>
                                </div>
                                {addPasskeyErrorMessage && (
                                    <FieldError data-testid="add-passkey-error">
                                        {addPasskeyErrorMessage}
                                    </FieldError>
                                )}
                            </form>
                        )}
                    </div>
                </>
            )}

            {/* Connected accounts */}
            {hasSocialiteProviders && (
                <>
                    <Separator />

                    <div className="space-y-4">
                        <div className="space-y-1">
                            <h3 className="font-medium">
                                {t('Connected Accounts')}
                            </h3>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Manage your connected social login providers',
                                )}
                            </p>
                        </div>

                        <div className="space-y-3">
                            {socialiteProviders.map((provider) => {
                                const ProviderIcon =
                                    providerIcons[provider.name.toLowerCase()];
                                const connected = !!getConnectedAccount(
                                    provider.name,
                                );

                                return (
                                    <div
                                        key={provider.name}
                                        className="flex items-center justify-between rounded-lg border p-4"
                                        data-testid={`socialite-account-${provider.name}`}
                                    >
                                        <div className="flex items-center gap-4">
                                            {ProviderIcon && (
                                                <ProviderIcon
                                                    className={
                                                        connected
                                                            ? 'size-6'
                                                            : 'size-6 opacity-50'
                                                    }
                                                />
                                            )}
                                            <div>
                                                <p className="font-medium">
                                                    {provider.label}
                                                </p>
                                                {connected ? (
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('Last login')}:{' '}
                                                        {formatDateTime(
                                                            getConnectedAccount(
                                                                provider.name,
                                                            )?.last_login_at,
                                                            locale,
                                                        )}
                                                    </p>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('Not connected')}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {connected ? (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                data-testid={`disconnect-socialite-${provider.name}`}
                                                disabled={
                                                    isDisconnecting ===
                                                    provider.name
                                                }
                                                onClick={() =>
                                                    initiateDisconnect(
                                                        provider.name,
                                                    )
                                                }
                                            >
                                                {isDisconnecting ===
                                                    provider.name && (
                                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                                )}
                                                {isDisconnecting ===
                                                provider.name
                                                    ? t('Disconnecting...')
                                                    : t('Disconnect')}
                                            </Button>
                                        ) : (
                                            enabledProviderNames.has(
                                                provider.name,
                                            ) && (
                                                <Button
                                                    asChild
                                                    variant="default"
                                                    size="sm"
                                                    data-testid={`connect-socialite-${provider.name}`}
                                                >
                                                    <a
                                                        href={route(
                                                            'auth.socialite.redirect',
                                                            provider.name,
                                                        )}
                                                    >
                                                        {t('Connect')}
                                                    </a>
                                                </Button>
                                            )
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
