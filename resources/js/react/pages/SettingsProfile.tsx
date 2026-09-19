import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useDialog } from '@/hooks/useDialog';
import { useT } from '@/i18n';
import type { User } from '@/types';
import { Form, router, usePage } from '@inertiajs/react';
import { Camera, Loader2, Trash2 } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';

interface SettingsProfileProps {
    user: User & {
        has_uploaded_avatar?: boolean;
    };
}

export default function SettingsProfile({ user }: SettingsProfileProps) {
    const t = useT();
    const page = usePage();
    const { confirm } = useDialog();

    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [isUpdatingAvatar, setIsUpdatingAvatar] = useState(false);
    const [isRemovingAvatar, setIsRemovingAvatar] = useState(false);

    const hasUploadedAvatar = user?.has_uploaded_avatar ?? false;
    const avatarBusy = isUpdatingAvatar || isRemovingAvatar;

    const userInitials = (user?.name ?? '')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const target = event.target;
        const file = target.files?.[0];

        if (file) {
            setAvatarPreview(URL.createObjectURL(file));
            setIsUpdatingAvatar(true);

            const formData = new FormData();
            formData.append('avatar', file);

            router.post(route('settings.profile.update-avatar'), formData, {
                preserveUrl: true,
                onFinish: () => {
                    setAvatarPreview(null);
                    setIsUpdatingAvatar(false);
                },
            });
        }

        // Reset the input so picking the same file again still fires change.
        target.value = '';
    };

    /**
     * Confirm through the app-level dialog rather than a nested one: a dialog
     * rendered inside the modal would compete with the modal's own focus trap.
     */
    const removeAvatar = async () => {
        const confirmed = await confirm({
            title: t('Remove Avatar'),
            description: t(
                'Are you sure you want to remove your avatar? This action cannot be undone.',
            ),
            confirmLabel: t('Remove'),
            cancelLabel: t('Cancel'),
            variant: 'destructive',
            icon: Trash2,
            align: 'left',
        });

        if (!confirmed) return;

        setIsRemovingAvatar(true);

        router.delete(route('settings.profile.delete-avatar'), {
            preserveUrl: true,
            onFinish: () => setIsRemovingAvatar(false),
        });
    };

    const avatarError = (page.props.errors as Record<string, string>)?.avatar;

    return (
        <div className="space-y-8" data-testid="settings-profile-panel">
            <p className="text-muted-foreground text-sm">
                {t('Your personal information and account details')}
            </p>

            {/* Avatar + identity */}
            <div className="flex flex-col gap-8 sm:flex-row sm:items-start">
                <div className="flex flex-col items-center gap-2">
                    <div className="group relative">
                        <Avatar className="ring-border size-28 ring-2">
                            <AvatarImage
                                src={avatarPreview ?? user?.avatar ?? ''}
                                alt={user?.name}
                            />
                            <AvatarFallback className="text-2xl">
                                {userInitials}
                            </AvatarFallback>
                        </Avatar>

                        {avatarBusy && (
                            <div
                                data-testid="avatar-busy"
                                className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50"
                            >
                                <Loader2 className="size-7 animate-spin text-white" />
                            </div>
                        )}

                        <input
                            id="avatar-upload"
                            type="file"
                            name="avatar"
                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                            className="hidden"
                            onChange={handleAvatarChange}
                        />

                        {!hasUploadedAvatar && !avatarBusy && (
                            <label
                                htmlFor="avatar-upload"
                                className="bg-primary text-primary-foreground absolute right-0 bottom-0 flex size-9 cursor-pointer items-center justify-center rounded-full shadow-md transition-transform hover:scale-110"
                            >
                                <Camera className="size-4" />
                            </label>
                        )}

                        {hasUploadedAvatar && !avatarBusy && (
                            <button
                                type="button"
                                data-testid="remove-avatar"
                                className="absolute right-0 bottom-0 flex size-9 cursor-pointer items-center justify-center rounded-full bg-red-500 text-white shadow-md transition-transform hover:scale-110"
                                onClick={removeAvatar}
                            >
                                <Trash2 className="size-4" />
                            </button>
                        )}
                    </div>

                    {avatarError && (
                        <div className="text-destructive text-xs">
                            {avatarError}
                        </div>
                    )}
                </div>

                <Form
                    action={route('settings.profile.update-info')}
                    method="patch"
                    className="flex-1 space-y-4"
                    disableWhileProcessing
                    options={{ preserveUrl: true }}
                >
                    {({ errors }) => (
                        <>
                            <Field data-invalid={!!errors.name}>
                                <FieldLabel htmlFor="name">
                                    {t('Name')}
                                </FieldLabel>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    placeholder={t('Enter your full name')}
                                    autoComplete="name"
                                    required
                                    data-testid="name"
                                    aria-invalid={!!errors.name}
                                    defaultValue={user?.name}
                                />
                                {errors.name && (
                                    <FieldError
                                        data-testid="name-error"
                                        aria-live="polite"
                                    >
                                        {errors.name}
                                    </FieldError>
                                )}
                            </Field>

                            <Field data-invalid={!!errors.email}>
                                <FieldLabel htmlFor="email">
                                    {t('Email')}
                                </FieldLabel>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    placeholder={t('Enter your email address')}
                                    autoComplete="email"
                                    required
                                    data-testid="email"
                                    aria-invalid={!!errors.email}
                                    defaultValue={user?.email}
                                />
                                {errors.email && (
                                    <FieldError
                                        data-testid="email-error"
                                        aria-live="polite"
                                    >
                                        {errors.email}
                                    </FieldError>
                                )}
                            </Field>

                            <div className="flex justify-end pt-2">
                                <Button
                                    type="submit"
                                    data-testid="submit-profile-info"
                                >
                                    {t('Save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
