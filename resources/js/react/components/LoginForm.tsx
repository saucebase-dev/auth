import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/i18n';
import { useForm, usePage } from '@inertiajs/react';
import { useModal } from '@inertiaui/modal-react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import AuthLink from './AuthLink';
import SocialiteProviders from './SocialiteProviders';

type AuthProps = {
    magic_link_enabled?: boolean;
    registration_enabled?: boolean;
};

interface LoginFormProps {
    /**
     * Whether this is the copy inside the auth modal.
     *
     * Changes two things: the sibling auth link swaps the modal in place rather
     * than navigating the page out from under it, and a success closes the modal
     * — success navigates the page *behind* it, which would otherwise leave the
     * modal sitting on top of wherever the visitor just landed.
     */
    modal?: boolean;
}

export default function LoginForm({ modal }: LoginFormProps) {
    const t = useT();
    const page = usePage();
    const auth = (page.props.auth as AuthProps) ?? {};
    const [email, setEmail] = useState('');
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const forgotUrl = route('password.request', { email });

    const currentModal = useModal();

    const handleSuccess = () => {
        if (modal) {
            currentModal?.close();
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('login'), {
            preserveScroll: true,
            onSuccess: handleSuccess,
        });
    };

    return (
        <>
            <SocialiteProviders />

            <form
                onSubmit={handleSubmit}
                className="space-y-3"
                data-testid="login-form"
            >
                <Field>
                    <Label htmlFor="email">{t('Email')}</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        placeholder={t('Enter your email')}
                        autoComplete="email"
                        required
                        data-testid="email"
                        value={data.email}
                        onChange={(e) => {
                            setData('email', e.target.value);
                            setEmail(e.target.value);
                        }}
                    />
                    {errors.email && (
                        <FieldError data-testid="email-error">
                            {errors.email}
                        </FieldError>
                    )}
                </Field>

                <Field>
                    <Label htmlFor="password">{t('Password')}</Label>
                    <div className="relative">
                        <Input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            placeholder={t('Enter your password')}
                            autoComplete="current-password"
                            required
                            data-testid="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                        <button
                            type="button"
                            data-testid="password-toggle"
                            aria-label={
                                showPassword
                                    ? t('Hide password')
                                    : t('Show password')
                            }
                            onClick={() => setShowPassword((v) => !v)}
                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                        >
                            {showPassword ? (
                                <EyeOff className="size-4" />
                            ) : (
                                <Eye className="size-4" />
                            )}
                        </button>
                    </div>
                    {errors.password && (
                        <FieldError data-testid="password-error">
                            {errors.password}
                        </FieldError>
                    )}
                </Field>

                <div className="flex items-center justify-between">
                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            name="remember"
                            data-testid="remember-me"
                            checked={data.remember}
                            onCheckedChange={(checked) =>
                                setData('remember', !!checked)
                            }
                        />
                        <FieldLabel className="font-normal">
                            {t('Remember-me')}
                        </FieldLabel>
                    </Field>

                    {route().has('password.request') && (
                        <AuthLink
                            modal={modal}
                            href={forgotUrl}
                            className="text-primary ml-auto inline-block text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline"
                            data-testid="forgot-password-link"
                        >
                            {t('Forgot your password?')}
                        </AuthLink>
                    )}
                </div>

                <Button
                    type="submit"
                    className="mt-3 w-full"
                    disabled={processing}
                    data-testid="login-button"
                >
                    {t('Log in')}
                </Button>

                <p className="mt-2 text-center text-sm">
                    {auth.magic_link_enabled && (
                        <AuthLink
                            modal={modal}
                            href={route('magic-link.create')}
                            className="text-primary font-medium underline-offset-4 hover:underline"
                            data-testid="magic-link-login-link"
                        >
                            {t('Login with magic link')}
                        </AuthLink>
                    )}
                </p>

                {auth.registration_enabled && (
                    <p className="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                        {t("Don't have an account?")}{' '}
                        <AuthLink
                            modal={modal}
                            href={route('register')}
                            className="text-primary font-medium underline-offset-4 hover:underline"
                            data-testid="sign-up-link"
                        >
                            {t('Sign up')}
                        </AuthLink>
                    </p>
                )}
            </form>
        </>
    );
}
