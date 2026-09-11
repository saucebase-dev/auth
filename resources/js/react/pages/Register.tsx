import { useT } from '@/i18n';
import { Modal } from '@inertiaui/modal-react';
import RegisterForm from '../components/RegisterForm';
import AuthCardLayout from '../layouts/AuthCardLayout';

interface RegisterProps {
    modal?: boolean;
}

/** Registration, as a page or a modal. See `Login.tsx` for the reasoning. */
export default function Register({ modal }: RegisterProps) {
    const t = useT();

    if (!modal) {
        return (
            <AuthCardLayout
                title={t('Create your account')}
                description={t(
                    'Sign up for Saucebase to start building your SaaS',
                )}
            >
                <RegisterForm />
            </AuthCardLayout>
        );
    }

    return (
        <Modal maxWidth="md">
            <div className="space-y-4" data-testid="register-modal">
                <div className="space-y-1.5 text-center">
                    <h2 className="text-2xl font-semibold">
                        {t('Create your account')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t('Sign up for Saucebase to start building your SaaS')}
                    </p>
                </div>

                <RegisterForm modal />
            </div>
        </Modal>
    );
}
