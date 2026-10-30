<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound, Loader2 } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    /** Read when the ceremony starts, so it follows the remember-me box. */
    remember?: boolean;
}>();

/**
 * Signs in with a passkey instead of the email and password above it.
 *
 * The client talks to the server with `fetch`, not Inertia, and the session is
 * regenerated on success — so the redirect is a full page load rather than an
 * Inertia visit. That also takes the auth modal down with the page behind it.
 */
const { verify, isLoading, errorInstance, isSupported } = usePasskeyVerify({
    remember: () => props.remember ?? false,
    routes: {
        options: route('auth.passkeys.options'),
        submit: route('auth.passkeys.login'),
    },
    onSuccess: (response) => {
        window.location.assign(response.redirect ?? '/');
    },
});

/** Closing the browser's own prompt is a choice, not something to report. */
const error = computed(() =>
    errorInstance.value?.name === 'UserCancelledError'
        ? null
        : errorInstance.value?.message,
);
</script>

<template>
    <div v-if="isSupported" class="space-y-2">
        <Button
            type="button"
            variant="outline"
            class="w-full"
            data-testid="passkey-login-button"
            :disabled="isLoading"
            @click="verify"
        >
            <Loader2 v-if="isLoading" class="size-4 animate-spin" />
            <KeyRound v-else class="size-4" />
            {{ $t('Sign in with a passkey') }}
        </Button>
        <FieldError v-if="error" data-testid="passkey-login-error">
            {{ error }}
        </FieldError>
    </div>
</template>
