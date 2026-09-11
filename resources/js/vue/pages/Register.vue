<script setup lang="ts">
import { Modal } from '@inertiaui/modal-vue';
import RegisterForm from '../components/RegisterForm.vue';
import AuthCardLayout from '../layouts/AuthCardLayout.vue';

/** Registration, as a page or a modal. See `Login.vue` for the reasoning. */
defineProps<{
    modal?: boolean;
}>();
</script>

<template>
    <Modal v-if="modal" max-width="md">
        <div class="space-y-4" data-testid="register-modal">
            <div class="space-y-1.5 text-center">
                <h2 class="text-2xl font-semibold">
                    {{ $t('Create your account') }}
                </h2>
                <p class="text-muted-foreground text-sm">
                    {{
                        $t('Sign up for Saucebase to start building your SaaS')
                    }}
                </p>
            </div>

            <RegisterForm modal />
        </div>
    </Modal>

    <AuthCardLayout
        v-else
        :title="$t('Create your account')"
        :description="$t('Sign up for Saucebase to start building your SaaS')"
    >
        <RegisterForm />
    </AuthCardLayout>
</template>
