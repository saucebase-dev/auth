<script setup lang="ts">
import AlertMessage from '@/components/AlertMessage.vue';
import { Modal } from '@inertiaui/modal-vue';
import LoginForm from '../components/LoginForm.vue';
import AuthCardLayout from '../layouts/AuthCardLayout.vue';

/**
 * Signing in, as a page or as a modal over whatever the visitor was reading.
 *
 * One component for both: the frame differs, the form does not. The controller
 * decides which frame by passing `modal`, and only does so when the site has
 * modal sign-in switched on and the caller explicitly asked for one — a typed or
 * bookmarked URL always lands on the page.
 */
defineProps<{
    status?: string;
    error?: string;
    modal?: boolean;
}>();
</script>

<template>
    <Modal v-if="modal" max-width="md">
        <div class="space-y-4" data-testid="login-modal">
            <div class="space-y-1.5 text-center">
                <h2 class="text-2xl font-semibold">{{ $t('Welcome back') }}</h2>
                <p class="text-muted-foreground text-sm">
                    {{ $t('Login to your Saucebase account to continue') }}
                </p>
            </div>

            <!-- The page frame shows these from the shared props; inside the
                 modal they arrive as the modal's own props. -->
            <AlertMessage
                :message="status || error"
                :variant="status ? 'success' : 'error'"
                data-testid="alert"
            />

            <LoginForm modal />
        </div>
    </Modal>

    <AuthCardLayout
        v-else
        :title="$t('Welcome back')"
        :description="$t('Login to your Saucebase account to continue')"
    >
        <LoginForm />
    </AuthCardLayout>
</template>
