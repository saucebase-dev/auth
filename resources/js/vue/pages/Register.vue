<script setup lang="ts">
import { useSettings } from '@saucebase/vue/composables/useSettings';
import { Modal } from '@inertiaui/modal-vue';
import RegisterForm from '../components/RegisterForm.vue';
import AuthCardLayout from '../layouts/AuthCardLayout.vue';

const settings = useSettings();

/** Registration, as a page or a modal. See `Login.vue` for the reasoning. */
const props = defineProps<{
    modal?: boolean;
    /** Reached from an invitation: the address the account is for. */
    by_invitation?: { email: string } | null;
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
                        $t('Sign up for :app to get started', {
                            app: settings.general.site_name,
                        })
                    }}
                </p>
            </div>

            <RegisterForm modal :invited-email="props.by_invitation?.email" />
        </div>
    </Modal>

    <AuthCardLayout
        v-else
        :title="$t('Create your account')"
        :description="
            $t('Sign up for :app to get started', {
                app: settings.general.site_name,
            })
        "
    >
        <RegisterForm :invited-email="props.by_invitation?.email" />
    </AuthCardLayout>
</template>
