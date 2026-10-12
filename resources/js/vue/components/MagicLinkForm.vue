<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form } from '@inertiajs/vue3';
import AuthLink from './AuthLink.vue';

defineProps<{
    /**
     * Whether this is the copy inside the auth modal.
     *
     * Only affects the way back to login — see `AuthLink`.
     */
    modal?: boolean;
}>();
</script>

<template>
    <Form
        :action="route('magic-link.store')"
        method="post"
        class="w-full space-y-3"
        data-testid="magic-link-form"
        disable-while-processing
        :reset-on-success="['email']"
    >
        <InputField
            name="email"
            type="email"
            :label="$t('Email')"
            :placeholder="$t('Enter your email')"
            required
            autocomplete="email"
            data-testid="magic-link-email"
        />

        <div
            class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:items-center sm:justify-between"
        >
            <AuthLink
                :modal="modal"
                :href="route('login')"
                class="mt-4 text-center text-sm text-gray-600 hover:text-gray-900 sm:mt-0 sm:text-left dark:text-gray-400 dark:hover:text-gray-100"
                data-testid="back-to-login-link"
            >
                {{ $t('Back to login') }}
            </AuthLink>
            <Button type="submit" data-testid="magic-link-submit">
                {{ $t('Send Magic Link') }}
            </Button>
        </div>
    </Form>
</template>
