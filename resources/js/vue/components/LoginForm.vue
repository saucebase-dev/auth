<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import InputField from '@/components/ui/input/InputField.vue';
import { Form } from '@inertiajs/vue3';
import { useModal } from '@inertiaui/modal-vue';
import { computed, ref } from 'vue';
import AuthLink from './AuthLink.vue';
import SocialiteProviders from './SocialiteProviders.vue';

const props = defineProps<{
    /** Whether this is the copy inside the auth modal. */
    modal?: boolean;
}>();

const currentModal = useModal();

/**
 * Success navigates the page *behind* the modal, so the modal has to be told to
 * go: left alone it stays sitting on top of wherever the visitor just landed.
 */
function handleSuccess(): void {
    if (props.modal) {
        currentModal?.close();
    }
}

const emailRef = ref('');

// compute forgot password url so the link updates as user types
const forgotUrl = computed(() =>
    route('password.request', { email: emailRef.value }),
);
</script>

<template>
    <SocialiteProviders />

    <Form
        :action="route('login')"
        :on-success="handleSuccess"
        method="post"
        class="space-y-3"
        data-testid="login-form"
        disable-while-processing
        :reset-on-error="['password']"
    >
        <!-- Email -->
        <InputField
            name="email"
            type="email"
            :label="$t('Email')"
            :placeholder="$t('Enter your email')"
            autocomplete="email"
            required
            v-model="emailRef"
        />

        <!-- Password -->
        <InputField
            name="password"
            type="password"
            :label="$t('Password')"
            :placeholder="$t('Enter your password')"
            autocomplete="current-password"
            required
        />

        <div class="flex items-center justify-between">
            <!-- Remember-me -->
            <div>
                <Field>
                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            name="remember"
                            data-testid="remember-me"
                        />
                        <FieldLabel
                            for="remember"
                            data-testid="remember-me-label"
                            class="font-normal"
                        >
                            {{ $t('Remember-me') }}
                        </FieldLabel>
                    </Field>
                </Field>
            </div>

            <!-- Forgot password link -->
            <AuthLink
                v-if="route().has('password.request')"
                :modal="modal"
                :href="forgotUrl"
                class="text-primary ml-auto inline-block text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline"
                data-testid="forgot-password-link"
                :data-invalid="false"
            >
                {{ $t('Forgot your password?') }}
            </AuthLink>
        </div>

        <Button type="submit" class="mt-3 w-full" data-testid="login-button">
            {{ $t('Log in') }}
        </Button>

        <p class="mt-2 text-center text-sm">
            <AuthLink
                v-if="$page.props.auth.magic_link_enabled"
                :modal="modal"
                :href="route('magic-link.create')"
                class="text-primary font-medium underline-offset-4 hover:underline"
                data-testid="magic-link-login-link"
            >
                {{ $t('Login with magic link') }}
            </AuthLink>
        </p>

        <p
            v-if="$page.props.auth.registration_enabled"
            class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400"
        >
            {{ $t("Don't have an account?") }}
            <AuthLink
                :modal="modal"
                :href="route('register')"
                class="text-primary font-medium underline-offset-4 hover:underline"
                data-testid="sign-up-link"
            >
                {{ $t('Sign up') }}
            </AuthLink>
        </p>
    </Form>
</template>
