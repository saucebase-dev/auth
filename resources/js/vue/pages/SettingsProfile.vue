<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { useDialog } from '@/composables/useDialog';
import type { User } from '@/types';
import { Form, router, usePage } from '@inertiajs/vue3';
import { Camera, Loader2, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps<{
    user: User & {
        has_uploaded_avatar?: boolean;
    };
}>();

const page = usePage();
const { confirm } = useDialog();

const avatarFile = ref<File | null>(null);
const isUpdatingAvatar = ref(false);
const isRemovingAvatar = ref(false);

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
    </div>
</template>
