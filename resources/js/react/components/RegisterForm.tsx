import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/i18n';
import { Link, useForm } from '@inertiajs/react';
import { useModal } from '@inertiaui/modal-react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import AuthLink from './AuthLink';
import SocialiteProviders from './SocialiteProviders';

interface RegisterFormProps {
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

export default function RegisterForm({ modal }: RegisterFormProps) {
    const t = useT();
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const currentModal = useModal();

    const handleSuccess = () => {
        if (modal) {
            currentModal?.close();
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('register'), {
            preserveScroll: true,
            onSuccess: handleSuccess,
        });
    };

    const canSubmit =
        data.name.trim() !== '' &&
        data.email.trim() !== '' &&
        data.password !== '' &&
        data.terms;

    return (
        <>
            <SocialiteProviders />

            <form
                onSubmit={handleSubmit}
                className="space-y-3"
                data-testid="register-form"
            >
                <Field>
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        placeholder={t('Enter your full name')}
                        autoComplete="name"
                        data-testid="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && (
                        <FieldError data-testid="name-error">
                            {errors.name}
                        </FieldError>
                    )}
                </Field>

                <Field>
                    <Label htmlFor="email">{t('Email')}</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        placeholder={t('Enter your email')}
                        autoComplete="email"
                        data-testid="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
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
                            autoComplete="new-password"
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

                <Field
                    orientation="horizontal"
                    className="mt-6 items-start"
                    data-invalid={!!errors.terms}
                >
                    <Checkbox
                        id="terms"
                        name="terms"
                        data-testid="terms-checkbox"
                        checked={data.terms}
                        onCheckedChange={(checked) =>
                            setData('terms', !!checked)
                        }
                        aria-invalid={!!errors.terms}
                    />
                    <FieldLabel
                        htmlFor="terms"
                        className="text-sm leading-snug font-normal"
                    >
                        {t('I agree to the')}{' '}
                        <Link
                            href={route('terms')}
                            className="text-primary font-medium underline-offset-4 hover:underline"
                            data-testid="terms-link"
                        >
                            {t('Terms of Service')}
                        </Link>{' '}
                        {t('and the')}{' '}
                        <Link
                            href={route('privacy')}
                            className="text-primary font-medium underline-offset-4 hover:underline"
                            data-testid="privacy-link"
                        >
                            {t('Privacy Policy')}
                        </Link>
                    </FieldLabel>
                </Field>
                {errors.terms && (
                    <FieldError data-testid="terms-error">
                        {errors.terms}
                    </FieldError>
                )}

                <Button
                    type="submit"
                    className="mt-3 w-full"
                    disabled={processing || !canSubmit}
                    data-testid="register-button"
                >
                    {t('Register')}
                </Button>

                <p className="mt-4 text-center text-sm text-gray-600 dark:text-gray-400">
                    {t('Already registered?')}{' '}
                    <AuthLink
                        modal={modal}
                        href={route('login')}
                        className="text-primary font-medium underline-offset-4 hover:underline"
                        data-testid="login-link"
                    >
                        {t('Log in')}
                    </AuthLink>
                </p>
            </form>
        </>
    );
}
