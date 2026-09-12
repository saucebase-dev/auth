import { Link } from '@inertiajs/react';
import { useModal, useModalStack } from '@inertiaui/modal-react';
import { type ReactNode } from 'react';

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

    /**
     * Each auth screen is its own route, so a plain modal link to a sibling opens
     * a second modal on top of this one — hop between sign-in and sign-up and
     * they pile up without limit. This replaces rather than stacks.
     *
     * Closing the bottom modal tears the whole stack down and restores the URL
     * behind it, so the sibling has to wait for that to finish: opened any
     * sooner it is swept away by the same teardown.
     */
    const openSibling = () => {
        const open = () => modalStack.visitModal(href, { navigate: true });

        if (!currentModal) {
            open();
            return;
        }

        // Polled from the handler, not from an effect: this component goes away
        // with the modal it lives in, and the wait has to outlive it. `length()`
        // reads the live ref — `stack` is render-time state and never changes
        // inside this closure.
        const whenEmpty = () => {
            if (modalStack.length() === 0) {
                open();
                return;
            }

            requestAnimationFrame(whenEmpty);
        };

        requestAnimationFrame(whenEmpty);
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
