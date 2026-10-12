import { Link, router } from '@inertiajs/react';
import { useModal, useModalStack } from '@inertiaui/modal-react';
import { useRef, type ReactNode } from 'react';

interface AuthLinkProps {
    href: string;
    /** Whether the screen holding this link is itself the auth modal. */
    modal?: boolean;
    className?: string;
    children: ReactNode;
    'data-testid'?: string;
}

export default function AuthLink({
    href,
    modal,
    className,
    children,
    ...rest
}: AuthLinkProps) {
    const currentModal = useModal();
    const modalStack = useModalStack();
    /** A second click during the swap would open a second sibling. */
    const swapping = useRef(false);

    /**
     * Each auth screen is its own route, so a plain modal link to a sibling opens
     * a second modal on top of this one — hop between sign-in and sign-up and
     * they pile up without limit. This replaces rather than stacks.
     *
     * Closing the modal navigates back to the page behind it, and only once
     * that navigation lands is the sibling opened. Opened sooner, it takes the
     * closing modal's URL as the page behind it and is torn down by that same
     * navigation, so a form that later fails comes back as a full page.
     */
    const openSibling = () => {
        const open = () => modalStack.visitModal(href, { navigate: true });

        if (!currentModal) {
            open();
            return;
        }

        if (swapping.current) {
            return;
        }
        swapping.current = true;

        // ponytail: assumes the modal changed the URL (every auth modal opens
        // with `navigate: true`); one that did not would never navigate back.
        // Registered from the handler, not from an effect: this component goes
        // away with the modal it lives in, and the listener has to outlive it.
        const stopListening = router.on('navigate', () => {
            stopListening();
            open();
        });

        currentModal.close();
    };

    if (!modal) {
        return (
            <Link href={href} className={className} {...rest}>
                {children}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={`cursor-pointer ${className ?? ''}`}
            onClick={openSibling}
            {...rest}
        >
            {children}
        </button>
    );
}
