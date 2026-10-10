import { Button } from '@/components/ui/button';
import { Link, useForm } from '@inertiajs/react';
import { useT } from '@saucebase/react/i18n';
import AuthCardLayout from '../layouts/AuthCardLayout';

export default function VerifyEmail() {
    const t = useT();
    const { post, processing } = useForm({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthCardLayout title={t('We are glad you signed up!')}>
            <form
                onSubmit={handleSubmit}
                className="max-w-md min-w-sm space-y-3 text-center"
                data-testid="verify-email-form"
            >
                <p className="mb-3 leading-relaxed text-gray-600 dark:text-gray-400">
                    {t(
                        'Before getting started, could you verify your email address by clicking on the link we just emailed to you?',
                    )}
                </p>
                <p className="mb-10 leading-relaxed text-gray-600 dark:text-gray-400">
                    {t(
                        'If you did not receive the email, you can click the button below to request another.',
                    )}
                </p>
                <Button type="submit" className="w-full" disabled={processing}>
                    {t('Resend Verification Email')}
                </Button>

                <p className="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-primary cursor-pointer font-medium underline-offset-4 hover:underline"
                        data-testid="logout-link"
                    >
                        {t('Log Out')}
                    </Link>
                </p>
            </form>
        </AuthCardLayout>
    );
}
