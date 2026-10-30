<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { useHttp } from '@saucebase/vue/composables/useHttp';
import { KeyRound, Loader2, ShieldCheck } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

/**
 * Fresh proof before an account-changing action (adding or removing a passkey,
 * changing the email): the password, or one of the user's passkeys. Either marks the
 * session confirmed for `auth.password_timeout`, so the action runs straight after.
 */
defineProps<{
    /** Offer "Use a passkey" — only when the user has one to use. */
    hasPasskeys?: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ confirmed: [] }>();

// Any failure besides a wrong password (throttled, expired session, offline) shows
// under the field rather than leaving the dialog silent.
const form = useHttp({ password: '' }, (message, http) =>
    http.setError('password', trans(message)),
);

const done = () => {
    form.reset();
    open.value = false;
    emit('confirmed');
};

const submit = () => {
    // JSON, not an Inertia visit: a visit reloads the settings panel, and that
    // reload races the passkey ceremony's challenge in the session.
    form.post(route('password.confirm.store'), { onSuccess: done });
};

const {
    verify,
    isLoading: isVerifying,
    errorInstance,
    isSupported,
} = usePasskeyVerify({
    routes: {
        options: route('settings.passkeys.confirm.options'),
        submit: route('settings.passkeys.confirm'),
    },
    onSuccess: done,
});

const passkeyError = computed(() =>
    errorInstance.value?.name === 'UserCancelledError'
        ? trans('Passkey confirmation was cancelled.')
        : errorInstance.value?.message,
);

const busy = computed(() => form.processing || isVerifying.value);
</script>

<template>
    <!-- Same layout as the app's confirm dialog (DynamicDialog), plus the password. -->
    <Dialog v-model:open="open">
        <DialogContent
            class="overflow-hidden p-0 sm:max-w-sm"
            :show-close-button="false"
            data-testid="confirm-identity-dialog"
        >
            <form
                id="confirm-identity-form"
                class="bg-background flex flex-col items-center p-6 text-center"
                @submit.prevent="submit"
            >
                <div
                    class="bg-primary/10 text-primary mb-4 flex size-14 items-center justify-center rounded-xl"
                >
                    <ShieldCheck class="size-7" />
                </div>
                <DialogHeader class="sm:text-center">
                    <DialogTitle>{{ $t("Confirm it's you") }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'This changes how you sign in. Confirm your password to continue.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <Input
                    v-model="form.password"
                    type="password"
                    name="password"
                    class="mt-4"
                    autocomplete="current-password"
                    :aria-label="$t('Password')"
                    :placeholder="$t('Password')"
                    :disabled="busy"
                    data-testid="confirm-identity-password"
                />
                <FieldError
                    v-if="form.errors.password"
                    class="mt-2 self-start"
                    data-testid="confirm-identity-error"
                >
                    {{ form.errors.password }}
                </FieldError>

                <template v-if="hasPasskeys && isSupported">
                    <Button
                        type="button"
                        variant="outline"
                        class="mt-4 w-full"
                        :disabled="busy"
                        data-testid="confirm-identity-passkey"
                        @click="verify"
                    >
                        <Loader2
                            v-if="isVerifying"
                            class="size-4 animate-spin"
                        />
                        <KeyRound v-else class="size-4" />
                        {{ $t('Use a passkey') }}
                    </Button>
                    <FieldError v-if="passkeyError" class="mt-2 self-start">
                        {{ passkeyError }}
                    </FieldError>
                </template>

                <p class="text-muted-foreground mt-4 text-xs">
                    {{
                        $t(
                            'No password? Sign out and use "Forgot password" to set one.',
                        )
                    }}
                </p>
            </form>
            <div class="bg-muted/50 border-t p-4">
                <div class="grid grid-cols-2 gap-4">
                    <Button
                        variant="outline"
                        class="dark:hover:bg-accent dark:hover:text-accent-foreground w-full"
                        :disabled="busy"
                        data-testid="confirm-identity-cancel"
                        @click="open = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        form="confirm-identity-form"
                        class="w-full"
                        :disabled="!form.password || busy"
                        data-testid="confirm-identity-submit"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        {{ $t('Confirm') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
