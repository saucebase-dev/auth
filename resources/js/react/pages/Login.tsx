import AlertMessage from '@/components/AlertMessage';
import { Modal } from '@inertiaui/modal-react';
import { useSettings } from '@saucebase/react/hooks/useSettings';
import { useT } from '@saucebase/react/i18n';
import LoginForm from '../components/LoginForm';
import AuthCardLayout from '../layouts/AuthCardLayout';

interface LoginProps {
    status?: string;
    error?: string;
    modal?: boolean;
}

/**
 * Signing in, as a page or as a modal over whatever the visitor was reading.
 *
 * One component for both: the frame differs, the form does not. The controller
 * decides which frame by passing `modal`, and only does so when the site has
 * modal sign-in switched on and the caller explicitly asked for one — a typed or
 * bookmarked URL always lands on the page.
 */
export default function Login({ status, error, modal }: LoginProps) {
    const t = useT();
    const settings = useSettings();

    if (!modal) {
        return (
            <AuthCardLayout
                title={t('Welcome back')}
                description={t('Login to your :app account to continue', {
                    app: settings.general.site_name,
                })}
            >
                <LoginForm />
            </AuthCardLayout>
        );
    }

    return (
        <Modal maxWidth="md">
            <div className="space-y-4" data-testid="login-modal">
                <div className="space-y-1.5 text-center">
                    <h2 className="text-2xl font-semibold">
                        {t('Welcome back')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t('Login to your :app account to continue', {
                            app: settings.general.site_name,
                        })}
                    </p>
                </div>

                {/* The page frame shows these from the shared props; inside the
                    modal they arrive as the modal's own props. */}
                <AlertMessage
                    message={status || error}
                    variant={status ? 'success' : 'error'}
                    data-testid="alert"
                />

                <LoginForm modal />
            </div>
        </Modal>
    );
}
