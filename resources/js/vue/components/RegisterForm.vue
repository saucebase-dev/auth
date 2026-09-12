<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { useModal } from '@inertiaui/modal-vue';
import { computed, ref } from 'vue';
import AuthLink from './AuthLink.vue';
import SocialiteProviders from './SocialiteProviders.vue';

const props = defineProps<{
    /**
     * Whether this is the copy inside the auth modal. Only affects the links out
     * of this form — see `AuthLink`.
     */
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

const page = usePage();
const termsError = computed(() => page.props.errors?.terms);

const nameRef = ref('');
const emailRef = ref('');
const passwordRef = ref('');
const termsRef = ref(false);

const canSubmit = computed(
    () =>
        !!nameRef.value.trim() &&
        !!emailRef.value.trim() &&
        !!passwordRef.value &&
        termsRef.value,
);
</script>

<template>
    <SocialiteProviders />

    <Form
        :action="route('register')"
        :on-success="handleSuccess"
        method="post"
        class="space-y-3"
        data-testid="register-form"
        disable-while-processing
        :reset-on-error="['password']"
    >
        <!-- Name -->
        <InputField
            name="name"
            type="text"
            :label="$t('Name')"
            :placeholder="$t('Enter your full name')"
            autocomplete="name"
            v-model="nameRef"
        />

        <!-- Email -->
        <InputField
            name="email"
            type="email"
            :label="$t('Email')"
            :placeholder="$t('Enter your email')"
            autocomplete="email"
            v-model="emailRef"
        />

        <!-- Password -->
        <InputField
            name="password"
            type="password"
            :label="$t('Password')"
            :placeholder="$t('Enter your password')"
            autocomplete="new-password"
            required
            v-model="passwordRef"
        />

        <!-- Terms & Privacy -->
        <Field
            orientation="horizontal"
            class="mt-6 items-start"
            :data-invalid="!!termsError"
        >
            <Checkbox
                id="terms"
                name="terms"
                data-testid="terms-checkbox"
                :aria-invalid="!!termsError"
                v-model="termsRef"
            />
            <FieldLabel for="terms" class="text-sm leading-snug font-normal">
                {{ $t('I agree to the') }}
                <Link
                    :href="route('terms')"
                    class="text-primary font-medium underline-offset-4 hover:underline"
                    data-testid="terms-link"
                >
                    {{ $t('Terms of Service') }}
                </Link>
                {{ $t('and the') }}
                <Link
                    :href="route('privacy')"
                    class="text-primary font-medium underline-offset-4 hover:underline"
                    data-testid="privacy-link"
                >
                    {{ $t('Privacy Policy') }}
                </Link>
            </FieldLabel>
        </Field>
        <FieldError v-if="termsError" data-testid="terms-error">
            {{ termsError }}
        </FieldError>

        <Button
            type="submit"
            class="mt-3 w-full"
            data-testid="register-button"
            :disabled="!canSubmit"
        >
            {{ $t('Register') }}
        </Button>

        <p class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Already registered?') }}
            <AuthLink
                :modal="modal"
                :href="route('login')"
                class="text-primary font-medium underline-offset-4 hover:underline"
                data-testid="login-link"
            >
                {{ $t('Log in') }}
            </AuthLink>
        </p>
    </Form>
</template>
