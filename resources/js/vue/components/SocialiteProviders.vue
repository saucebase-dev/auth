<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { providerIcons } from './providerIcons';

type AuthProps = {
    last_social_provider?: string | null;
    socialite_providers?: Array<{ name: string; label: string }>;
};

const page = usePage();
const auth = computed(() => (page.props.auth as AuthProps) ?? {});
const providers = computed(() => auth.value.socialite_providers ?? []);
const lastUsed = computed(() => auth.value.last_social_provider);
</script>

<template>
    <div
        v-if="route().has('auth.socialite.redirect') && providers.length"
        class="mb-2 space-y-3"
        data-testid="socialite-providers"
    >
        <div v-for="{ name, label } in providers" :key="name" class="relative">
            <Button as-child variant="outline" class="w-full">
                <a
                    :href="route('auth.socialite.redirect', { provider: name })"
                    :data-testid="`socialite-provider-${name}`"
                >
                    <component
                        :is="providerIcons[name]"
                        v-if="providerIcons[name]"
                        class="h-5 w-5"
                    />
                    <span>
                        {{ $t('Connect with :Provider', { Provider: label }) }}
                    </span>
                </a>
            </Button>
            <span
                v-if="lastUsed === name"
                :data-testid="`last-used-badge-${name}`"
                class="bg-muted/80 text-muted-foreground absolute -top-2 -right-2 rounded-xl border px-2 py-0.5 text-xs drop-shadow-lg"
            >
                {{ $t('Last used') }}
            </span>
        </div>
        <div
            class="after:border-border relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t"
        >
            <span class="bg-card text-muted-foreground relative z-10 px-2">
                {{ $t('Or continue with email') }}
            </span>
        </div>
    </div>
</template>
