import AlertMessage from '@/components/AlertMessage';
import { useT } from '@/i18n';
import { Modal } from '@inertiaui/modal-react';
import ForgotPasswordForm from '../components/ForgotPasswordForm';
import AuthCardLayout from '../layouts/AuthCardLayout';

interface ForgotPasswordProps {
    status?: string;
    email?: string;
    modal?: boolean;
}

const description =
    'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.';

/** Password reset request, as a page or a modal. See `Login.tsx` for the reasoning. */
export default function ForgotPassword({
    status,
    email,
    modal,
}: ForgotPasswordProps) {
    const t = useT();

    if (!modal) {
        return (
            <AuthCardLayout
                title={t('Forgot Password')}
                description={t(description)}
            >
                <ForgotPasswordForm email={email} />
            </AuthCardLayout>
        );
    }

    return (
        <Modal maxWidth="md">
            <div className="space-y-4" data-testid="forgot-password-modal">
                <div className="space-y-1.5 text-center">
                    <h2 className="text-2xl font-semibold">
                        {t('Forgot Password')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t(description)}
                    </p>
                </div>

                {/* The page frame shows this from the shared props; inside the
                    modal it arrives as the modal's own prop. */}
                <AlertMessage
                    message={status}
                    variant="success"
                    data-testid="alert"
                />

                <ForgotPasswordForm email={email} modal />
            </div>
        </Modal>
    );
}
