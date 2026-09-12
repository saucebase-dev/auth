<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useModal, useModalStack, visitModal } from '@inertiaui/modal-vue';
import { watch } from 'vue';

const props = defineProps<{
    href: string;
    /** Whether the screen holding this link is itself the auth modal. */
    modal?: boolean;
}>();

const currentModal = useModal();
const { stack } = useModalStack();

/**
 * Each auth screen is its own route, so a plain modal link to a sibling opens a
 * second modal on top of this one — hop between sign-in and sign-up and they
 * pile up without limit. This replaces rather than stacks.
 *
 * Closing the bottom modal tears the whole stack down and restores the URL
 * behind it, so the sibling has to wait for that to finish: opened any sooner it
 * is swept away by the same teardown.
 */
function openSibling(): void {
    const open = () => visitModal(props.href, { navigate: true });

    if (!currentModal) {
        open();
        return;
    }

    // Watched from the handler, not from setup: this component goes away with
    // the modal it lives in, and the watcher has to outlive it.
    const stopWatching = watch(stack, (modals) => {
        if (modals.length === 0) {
            stopWatching();
            open();
        }
    });

    currentModal.close();
}
</script>

<template>
    <button
        v-if="modal"
        type="button"
        class="cursor-pointer"
        @click="openSibling"
    >
        <slot />
    </button>

    <Link v-else :href="href">
        <slot />
    </Link>
</template>
