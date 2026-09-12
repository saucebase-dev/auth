import AlertMessage from '@/components/AlertMessage';
import { useT } from '@/i18n';
import { Modal } from '@inertiaui/modal-react';
import MagicLinkForm from '../components/MagicLinkForm';
import AuthCardLayout from '../layouts/AuthCardLayout';

interface MagicLinkProps {
    status?: string;
    modal?: boolean;
}

/** Requesting a magic link, as a page or a modal. See `Login.tsx` for the reasoning. */
export default function MagicLink({ status, modal }: MagicLinkProps) {
    const t = useT();

    if (!modal) {
        return (
            <AuthCardLayout
                title={t('Magic Link Login')}
                description={t(
                    'Enter your email to receive a secure, one-time login link.',
                )}
            >
                <MagicLinkForm />
            </AuthCardLayout>
        );
    }

    return (
        <Modal maxWidth="md">
            <div className="space-y-4" data-testid="magic-link-modal">
                <div className="space-y-1.5 text-center">
                    <h2 className="text-2xl font-semibold">
                        {t('Magic Link Login')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Enter your email to receive a secure, one-time login link.',
                        )}
                    </p>
                </div>

                {/* The page frame shows this from the shared props; inside the
                    modal it arrives as the modal's own prop. */}
                <AlertMessage
                    message={status}
                    variant="success"
                    data-testid="alert"
                />

                <MagicLinkForm modal />
            </div>
        </Modal>
    );
}
