<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { useDialog } from '@/composables/useDialog';
import type { User } from '@/types';
import { Form, router, usePage } from '@inertiajs/vue3';
import { Camera, Loader2, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import IconGithub from '~icons/simple-icons/github';
import IconGoogle from '~icons/simple-icons/google';

type SocialiteProvider = {
    name: string;
    label: string;
};

const props = defineProps<{
    user: User & {
        has_uploaded_avatar?: boolean;
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

const avatarFile = ref<File | null>(null);
const isUpdatingAvatar = ref(false);
const isRemovingAvatar = ref(false);
const isDisconnecting = ref<string | null>(null);

const avatarPreview = computed(() => {
    if (avatarFile.value) {
        return URL.createObjectURL(avatarFile.value);
    }

    return props.user?.avatar ?? null;
});

const hasUploadedAvatar = computed(
    () => props.user?.has_uploaded_avatar ?? false,
);

const userInitials = computed(() =>
    (props.user?.name ?? '')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2),
);

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        avatarFile.value = file;
        submitAvatarForm();
    }

    // Reset the input so picking the same file again still fires change.
    target.value = '';
};

const submitAvatarForm = () => {
    if (!avatarFile.value) return;

    isUpdatingAvatar.value = true;
    const formData = new FormData();
    formData.append('avatar', avatarFile.value);

    router.post(route('settings.profile.update-avatar'), formData, {
        preserveUrl: true,
        onFinish: () => {
            avatarFile.value = null;
            isUpdatingAvatar.value = false;
        },
    });
};

/**
 * Confirm through the app-level dialog rather than a nested one: a dialog
 * rendered inside the modal would compete with the modal's own focus trap.
 */
const removeAvatar = async () => {
    const confirmed = await confirm({
        title: trans('Remove Avatar'),
        description: trans(
            'Are you sure you want to remove your avatar? This action cannot be undone.',
        ),
        confirmLabel: trans('Remove'),
        cancelLabel: trans('Cancel'),
        variant: 'destructive',
        icon: Trash2,
        align: 'left',
    });

    if (!confirmed) return;

    isRemovingAvatar.value = true;

    router.delete(route('settings.profile.delete-avatar'), {
        preserveUrl: true,
        onFinish: () => {
            isRemovingAvatar.value = false;
        },
    });
};

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
    <div class="space-y-8" data-testid="settings-profile-panel">
        <div class="space-y-1.5">
            <h2 class="text-lg font-semibold">{{ $t('Profile') }}</h2>
            <p class="text-muted-foreground text-sm">
                {{ $t('Your personal information and account details') }}
            </p>
        </div>

        <!-- Avatar + identity -->
        <div class="flex flex-col gap-8 sm:flex-row sm:items-start">
            <div class="flex flex-col items-center gap-2">
                <div class="group relative">
                    <Avatar class="ring-border size-28 ring-2">
                        <AvatarImage
                            :src="avatarPreview ?? ''"
                            :alt="user?.name"
                        />
                        <AvatarFallback class="text-2xl">
                            {{ userInitials }}
                        </AvatarFallback>
                    </Avatar>

                    <div
                        v-if="isUpdatingAvatar || isRemovingAvatar"
                        data-testid="avatar-busy"
                        class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50"
                    >
                        <Loader2 class="size-7 animate-spin text-white" />
                    </div>

                    <input
                        id="avatar-upload"
                        type="file"
                        name="avatar"
                        accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                        class="hidden"
                        @change="handleAvatarChange"
                    />

                    <label
                        v-if="
                            !hasUploadedAvatar &&
                            !isUpdatingAvatar &&
                            !isRemovingAvatar
                        "
                        for="avatar-upload"
                        class="bg-primary text-primary-foreground absolute right-0 bottom-0 flex size-9 cursor-pointer items-center justify-center rounded-full shadow-md transition-transform hover:scale-110"
                    >
                        <Camera class="size-4" />
                    </label>

                    <button
                        v-if="
                            hasUploadedAvatar &&
                            !isUpdatingAvatar &&
                            !isRemovingAvatar
                        "
                        type="button"
                        data-testid="remove-avatar"
                        class="absolute right-0 bottom-0 flex size-9 items-center justify-center rounded-full bg-red-500 text-white shadow-md transition-transform hover:scale-110"
                        @click="removeAvatar"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <div
                    v-if="page.props.errors.avatar"
                    class="text-destructive text-xs"
                >
                    {{ page.props.errors.avatar }}
                </div>
            </div>

            <Form
                :action="route('settings.profile.update-info')"
                method="patch"
                class="flex-1 space-y-4"
                disable-while-processing
                preserve-url
            >
                <InputField
                    name="name"
                    type="text"
                    :label="$t('Name')"
                    :placeholder="$t('Enter your full name')"
                    autocomplete="name"
                    required
                    :model-value="user?.name"
                />

                <InputField
                    name="email"
                    type="email"
                    :label="$t('Email')"
                    :placeholder="$t('Enter your email address')"
                    autocomplete="email"
                    required
                    :model-value="user?.email"
                />

                <div class="flex justify-end pt-2">
                    <Button type="submit" data-testid="submit-profile-info">
                        {{ $t('Save') }}
                    </Button>
                </div>
            </Form>
        </div>

        <Separator />

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
