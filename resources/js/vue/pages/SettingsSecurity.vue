<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import InputField from '@/components/ui/input/InputField.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { useDialog } from '@saucebase/vue/composables/useDialog';
import { useLocalization } from '@saucebase/vue/composables/useLocalization';
import { formatDateTime } from '@saucebase/core/lib/dates';
import { Form, router, usePage } from '@inertiajs/vue3';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { KeyRound, Loader2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
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

/**
 * How the signed-in user proves who they are: their password, their passkeys,
 * and the social accounts that can sign them in without one.
 *
 * Split out of the profile panel because these are credentials rather than a
 * description of a person — a different question to answer, in a different frame
 * of mind.
 */
const props = defineProps<{
    user: {
        has_password?: boolean;
        social_accounts?: Array<{
            provider: string;
            last_login_at: string;
            provider_avatar_url?: string;
        }>;
    };
    available_providers?: SocialiteProvider[];
    passkeys?: Passkey[];
    can_add_passkeys?: boolean;
}>();

const page = usePage();
const { language } = useLocalization();
const { confirm } = useDialog();

const isDisconnecting = ref<string | null>(null);

const getProviderIcon = (providerName: string) =>
    providerIcons[providerName.toLowerCase()];

const getConnectedAccount = (providerName: string) =>
    props.user?.social_accounts?.find(
        (account) => account.provider === providerName,
    );

const enabledProviders = computed<SocialiteProvider[]>(() => {
    const auth = page.props.auth as {
        socialite_providers?: SocialiteProvider[];
    };

    return auth.socialite_providers ?? [];
});

const enabledProviderNames = computed(
    () => new Set(enabledProviders.value.map((provider) => provider.name)),
);

const isProviderEnabled = (providerName: string): boolean =>
    enabledProviderNames.value.has(providerName);

/**
 * Providers currently enabled, plus any the user is still connected to even
 * though the provider has since been disabled — otherwise a user could never
 * disconnect an account belonging to a turned-off provider.
 */
const socialiteProviders = computed<SocialiteProvider[]>(() => {
    const configuredProviders = new Map(
        (props.available_providers ?? []).map((provider) => [
            provider.name,
            provider,
        ]),
    );
    const providers = new Map(
        enabledProviders.value.map((provider) => [provider.name, provider]),
    );

    for (const account of props.user.social_accounts ?? []) {
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
});

const passkeysEnabled = computed(
    () =>
        !!(page.props.auth as { passkeys_enabled?: boolean })?.passkeys_enabled,
);

/**
 * Shown while the feature is on, and also while the user still holds passkeys
 * after it was switched off — otherwise they could never remove them.
 */
const showPasskeys = computed(
    () => passkeysEnabled.value || (props.passkeys?.length ?? 0) > 0,
);

const passkeyName = ref('');
const isRemovingPasskey = ref<number | null>(null);

/**
 * The ceremony runs over `fetch`, outside Inertia, so nothing reloads the panel
 * by itself. A page reload does: the modal refreshes the active section after
 * any Inertia visit, and the reload also delivers the success toast.
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
        passkeyName.value = '';
        router.reload();
    },
});

/** Closing the browser's own prompt is a choice, not something to report. */
const addPasskeyErrorMessage = computed(() =>
    addPasskeyError.value?.name === 'UserCancelledError'
        ? null
        : addPasskeyError.value?.message,
);

const canAddPasskey = computed(
    () =>
        passkeysEnabled.value &&
        passkeysSupported.value &&
        props.can_add_passkeys !== false,
);

const addPasskey = () => {
    const name = passkeyName.value.trim();

    if (name === '') return;

    void register(name);
};

const removePasskey = async (passkey: Passkey) => {
    const confirmed = await confirm({
        title: trans('Remove Passkey'),
        description: trans(
            'You will no longer be able to sign in with ":name". If it is your only way in, you will need to reset your password to get back into your account.',
            { name: passkey.name },
        ),
        confirmLabel: trans('Remove'),
        cancelLabel: trans('Cancel'),
        variant: 'destructive',
        align: 'left',
    });

    if (!confirmed) return;

    isRemovingPasskey.value = passkey.id;

    router.delete(route('settings.passkeys.destroy', passkey.id), {
        preserveUrl: true,
        onFinish: () => {
            isRemovingPasskey.value = null;
        },
    });
};

const hasSocialiteProviders = computed(
    () =>
        route().has('auth.socialite.redirect') &&
        socialiteProviders.value.length > 0,
);

/**
 * Confirm through the app-level dialog rather than a nested one: a dialog
 * rendered inside the modal would compete with the modal's own focus trap.
 */
const initiateDisconnect = async (provider: string) => {
    const confirmed = await confirm({
        title: trans('Disconnect Social Account'),
        description: trans(
            'Are you sure you want to disconnect this social account? You can reconnect it anytime.',
        ),
        confirmLabel: trans('Disconnect'),
        cancelLabel: trans('Cancel'),
        variant: 'destructive',
        align: 'left',
    });

    if (!confirmed) return;

    isDisconnecting.value = provider;

    router.delete(route('auth.socialite.disconnect', provider), {
        preserveUrl: true,
        onFinish: () => {
            isDisconnecting.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-8" data-testid="settings-security-panel">
        <p class="text-muted-foreground text-sm">
            {{
                $t(
                    'Your password, passkeys and the accounts that can sign you in',
                )
            }}
        </p>

        <!-- Password -->
        <div class="space-y-4">
            <div class="space-y-1">
                <h3 class="font-medium">{{ $t('Update Password') }}</h3>
                <p class="text-muted-foreground text-sm">
                    {{
                        $t(
                            'Ensure your account is using a long, random password to stay secure.',
                        )
                    }}
                </p>
            </div>

            <Form
                :action="route('settings.profile.password.update')"
                method="put"
                class="space-y-4"
                disable-while-processing
                preserve-url
                :reset-on-success="true"
            >
                <InputField
                    name="current_password"
                    type="password"
                    :label="$t('Current Password')"
                    :placeholder="$t('Enter your current password')"
                    autocomplete="current-password"
                    required
                />

                <InputField
                    name="password"
                    type="password"
                    :label="$t('New Password')"
                    :placeholder="$t('Enter your new password')"
                    autocomplete="new-password"
                    required
                />

                <InputField
                    name="password_confirmation"
                    type="password"
                    :label="$t('Confirm Password')"
                    :placeholder="$t('Confirm your new password')"
                    autocomplete="new-password"
                    required
                />

                <div class="flex justify-end pt-2">
                    <Button type="submit" data-testid="submit-change-password">
                        {{ $t('Update Password') }}
                    </Button>
                </div>
            </Form>
        </div>

        <!-- Passkeys -->
        <template v-if="showPasskeys">
            <Separator />

            <div class="space-y-4" data-testid="passkeys-section">
                <div class="space-y-1">
                    <h3 class="font-medium">{{ $t('Passkeys') }}</h3>
                    <p class="text-muted-foreground text-sm">
                        {{
                            $t(
                                'Sign in with your fingerprint, face or screen lock instead of a password.',
                            )
                        }}
                    </p>
                </div>

                <div v-if="passkeys?.length" class="space-y-3">
                    <div
                        v-for="passkey in passkeys"
                        :key="passkey.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-4"
                        data-testid="passkey"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <KeyRound class="size-6 shrink-0" />
                            <div class="min-w-0">
                                <p
                                    class="truncate font-medium"
                                    data-testid="passkey-name"
                                >
                                    {{ passkey.name }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    <template v-if="passkey.authenticator">
                                        {{ passkey.authenticator }} ·
                                    </template>
                                    {{ $t('Last used') }}:
                                    {{
                                        passkey.last_used_at
                                            ? formatDateTime(
                                                  passkey.last_used_at,
                                                  language,
                                              )
                                            : $t('Never')
                                    }}
                                </p>
                            </div>
                        </div>

                        <Button
                            variant="destructive"
                            size="sm"
                            data-testid="remove-passkey"
                            :disabled="isRemovingPasskey === passkey.id"
                            @click="removePasskey(passkey)"
                        >
                            <Loader2
                                v-if="isRemovingPasskey === passkey.id"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{ $t('Remove') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-else
                    class="text-muted-foreground text-sm"
                    data-testid="passkeys-empty"
                >
                    {{ $t('No passkeys yet.') }}
                </p>

                <form
                    v-if="canAddPasskey"
                    class="space-y-2"
                    data-testid="add-passkey-form"
                    @submit.prevent="addPasskey"
                >
                    <div class="flex gap-2">
                        <Input
                            v-model="passkeyName"
                            name="passkey_name"
                            :aria-label="$t('Passkey name')"
                            :placeholder="
                                $t('Name this passkey, e.g. Work laptop')
                            "
                            maxlength="255"
                            required
                            data-testid="passkey-name-input"
                        />
                        <Button
                            type="submit"
                            :disabled="isAddingPasskey"
                            data-testid="add-passkey"
                        >
                            <Loader2
                                v-if="isAddingPasskey"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{ $t('Add passkey') }}
                        </Button>
                    </div>
                    <FieldError
                        v-if="addPasskeyErrorMessage"
                        data-testid="add-passkey-error"
                    >
                        {{ addPasskeyErrorMessage }}
                    </FieldError>
                </form>
            </div>
        </template>

        <!-- Connected accounts -->
        <template v-if="hasSocialiteProviders">
            <Separator />

            <div class="space-y-4">
                <div class="space-y-1">
                    <h3 class="font-medium">{{ $t('Connected Accounts') }}</h3>
                    <p class="text-muted-foreground text-sm">
                        {{ $t('Manage your connected social login providers') }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="provider in socialiteProviders"
                        :key="provider.name"
                        class="flex items-center justify-between rounded-lg border p-4"
                        :data-testid="`socialite-account-${provider.name}`"
                    >
                        <div class="flex items-center gap-4">
                            <component
                                :is="getProviderIcon(provider.name)"
                                v-if="getProviderIcon(provider.name)"
                                class="size-6"
                                :class="{
                                    'opacity-50': !getConnectedAccount(
                                        provider.name,
                                    ),
                                }"
                            />
                            <div>
                                <p class="font-medium">{{ provider.label }}</p>
                                <p
                                    v-if="getConnectedAccount(provider.name)"
                                    class="text-muted-foreground text-sm"
                                >
                                    {{ $t('Last login') }}:
                                    {{
                                        formatDateTime(
                                            getConnectedAccount(provider.name)
                                                ?.last_login_at,
                                            language,
                                        )
                                    }}
                                </p>
                                <p v-else class="text-muted-foreground text-sm">
                                    {{ $t('Not connected') }}
                                </p>
                            </div>
                        </div>

                        <Button
                            v-if="getConnectedAccount(provider.name)"
                            variant="destructive"
                            size="sm"
                            :data-testid="`disconnect-socialite-${provider.name}`"
                            :disabled="isDisconnecting === provider.name"
                            @click="initiateDisconnect(provider.name)"
                        >
                            <Loader2
                                v-if="isDisconnecting === provider.name"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{
                                isDisconnecting === provider.name
                                    ? $t('Disconnecting...')
                                    : $t('Disconnect')
                            }}
                        </Button>
                        <Button
                            v-else-if="isProviderEnabled(provider.name)"
                            as="a"
                            :href="
                                route('auth.socialite.redirect', provider.name)
                            "
                            variant="default"
                            size="sm"
                            :data-testid="`connect-socialite-${provider.name}`"
                        >
                            {{ $t('Connect') }}
                        </Button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
