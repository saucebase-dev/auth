<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useModal, visitModal } from '@inertiaui/modal-vue';

const props = defineProps<{
    href: string;
    /** Whether the screen holding this link is itself the auth modal. */
    modal?: boolean;
}>();

const currentModal = useModal();

/** A second click during the swap would open a second sibling. */
let swapping = false;

/**
 * Each auth screen is its own route, so a plain modal link to a sibling opens a
 * second modal on top of this one — hop between sign-in and sign-up and they
 * pile up without limit. This replaces rather than stacks.
 *
 * Closing the modal navigates back to the page behind it, and only once that
 * navigation lands is the sibling opened. Opened sooner, it takes the closing
 * modal's URL as the page behind it and is torn down by that same navigation,
 * so a form that later fails comes back as a full page.
 */
function openSibling(): void {
    const open = () => visitModal(props.href, { navigate: true });

    if (!currentModal) {
        open();
        return;
    }

    if (swapping) {
        return;
    }
    swapping = true;

    // ponytail: assumes the modal changed the URL (every auth modal opens with
    // `navigate: true`); one that did not would never navigate back.
    // Registered from the handler, not from setup: this component goes away
    // with the modal it lives in, and the listener has to outlive it.
    const stopListening = router.on('navigate', () => {
        stopListening();
        open();
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
