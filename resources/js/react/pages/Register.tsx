import { Modal } from '@inertiaui/modal-react';
import { useSettings } from '@saucebase/react/hooks/useSettings';
import { useT } from '@saucebase/react/i18n';
import RegisterForm from '../components/RegisterForm';
import AuthCardLayout from '../layouts/AuthCardLayout';

interface RegisterProps {
    modal?: boolean;
    /** Reached from an invitation: the address the account is for. */
    by_invitation?: { email: string } | null;
}

/** Registration, as a page or a modal. See `Login.tsx` for the reasoning. */
export default function Register({ modal, by_invitation }: RegisterProps) {
    const invitedEmail = by_invitation?.email;
    const t = useT();
    const settings = useSettings();

    if (!modal) {
        return (
            <AuthCardLayout
                title={t('Create your account')}
                description={t('Sign up for :app to get started', {
                    app: settings.general.site_name,
                })}
            >
                <RegisterForm invitedEmail={invitedEmail} />
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
                        {t('Sign up for :app to get started', {
                            app: settings.general.site_name,
                        })}
                    </p>
                </div>

                <RegisterForm modal invitedEmail={invitedEmail} />
            </div>
        </Modal>
    );
}
