<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { useDialog } from '@/composables/useDialog';
import { Form, router, usePage } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import IconGithub from '~icons/simple-icons/github';
import IconGoogle from '~icons/simple-icons/google';

type SocialiteProvider = {
    name: string;
    label: string;
};

/**
 * How the signed-in user proves who they are: their password, and the social
 * accounts that can sign them in without one.
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
}>();

const page = usePage();
const { confirm } = useDialog();

const isDisconnecting = ref<string | null>(null);

const providerIcons: Record<string, unknown> = {
    google: IconGoogle,
    github: IconGithub,
};

const getProviderIcon = (providerName: string) =>
    providerIcons[providerName.toLowerCase()];

const isProviderConnected = (providerName: string): boolean =>
    props.user?.social_accounts?.some(
        (account) => account.provider === providerName,
    ) ?? false;

const getConnectedAccount = (providerName: string) =>
    props.user?.social_accounts?.find(
        (account) => account.provider === providerName,
    );

const formatLastLogin = (date: string): string =>
    new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

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
        <div class="space-y-1.5">
            <h2 class="text-lg font-semibold">{{ $t('Security') }}</h2>
            <p class="text-muted-foreground text-sm">
                {{ $t('Your password and the accounts that can sign you in') }}
            </p>
        </div>

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

        <Separator />

        <!-- Connected accounts -->
        <template v-if="hasSocialiteProviders">
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
                                    'opacity-50': !isProviderConnected(
                                        provider.name,
                                    ),
                                }"
                            />
                            <div>
                                <p class="font-medium">{{ provider.label }}</p>
                                <p
                                    v-if="isProviderConnected(provider.name)"
                                    class="text-muted-foreground text-sm"
                                >
                                    {{ $t('Last login') }}:
                                    {{
                                        formatLastLogin(
                                            getConnectedAccount(provider.name)
                                                ?.last_login_at ?? '',
                                        )
                                    }}
                                </p>
                                <p v-else class="text-muted-foreground text-sm">
                                    {{ $t('Not connected') }}
                                </p>
                            </div>
                        </div>

                        <Button
                            v-if="isProviderConnected(provider.name)"
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
