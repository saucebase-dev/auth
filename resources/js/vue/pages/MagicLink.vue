<script setup lang="ts">
import AlertMessage from '@/components/AlertMessage.vue';
import { Modal } from '@inertiaui/modal-vue';
import MagicLinkForm from '../components/MagicLinkForm.vue';
import AuthCardLayout from '../layouts/AuthCardLayout.vue';

/** Requesting a magic link, as a page or a modal. See `Login.vue` for the reasoning. */
defineProps<{
    status?: string;
    modal?: boolean;
}>();
</script>

<template>
    <Modal v-if="modal" max-width="md">
        <div class="space-y-4" data-testid="magic-link-modal">
            <div class="space-y-1.5 text-center">
                <h2 class="text-2xl font-semibold">
                    {{ $t('Magic Link Login') }}
                </h2>
                <p class="text-muted-foreground text-sm">
                    {{
                        $t(
                            'Enter your email to receive a secure, one-time login link.',
                        )
                    }}
                </p>
            </div>

            <!-- The page frame shows this from the shared props; inside the
                 modal it arrives as the modal's own prop. -->
            <AlertMessage
                :message="status"
                variant="success"
                data-testid="alert"
            />

            <MagicLinkForm modal />
        </div>
    </Modal>

    <AuthCardLayout
        v-else
        :title="$t('Magic Link Login')"
        :description="
            $t('Enter your email to receive a secure, one-time login link.')
        "
    >
        <MagicLinkForm />
    </AuthCardLayout>
</template>
