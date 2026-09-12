<script setup lang="ts">
import AlertMessage from '@/components/AlertMessage.vue';
import { Modal } from '@inertiaui/modal-vue';
import ForgotPasswordForm from '../components/ForgotPasswordForm.vue';
import AuthCardLayout from '../layouts/AuthCardLayout.vue';

/** Password reset request, as a page or a modal. See `Login.vue` for the reasoning. */
defineProps<{
    status?: string;
    email?: string;
    modal?: boolean;
}>();
</script>

<template>
    <Modal v-if="modal" max-width="md">
        <div class="space-y-4" data-testid="forgot-password-modal">
            <div class="space-y-1.5 text-center">
                <h2 class="text-2xl font-semibold">
                    {{ $t('Forgot Password') }}
                </h2>
                <p class="text-muted-foreground text-sm">
                    {{
                        $t(
                            'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.',
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

            <ForgotPasswordForm :email="email" modal />
        </div>
    </Modal>

    <AuthCardLayout
        v-else
        :title="$t('Forgot Password')"
        :description="
            $t(
                'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.',
            )
        "
    >
        <ForgotPasswordForm :email="email" />
    </AuthCardLayout>
</template>
