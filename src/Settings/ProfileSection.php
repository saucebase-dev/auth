<?php

namespace Modules\Auth\Settings;

use App\Settings\SettingsSection;

/**
 * The signed-in user's own account: name, email, avatar, password, connected logins.
 *
 * First in the sidebar, and the section the modal falls back to when the fragment
 * names none — every installation has an account, whatever else is installed.
 */
class ProfileSection extends SettingsSection
{
    public function slug(): string
    {
        return 'profile';
    }

    public function title(): string
    {
        return __('Profile');
    }

    public function icon(): ?string
    {
        return 'profile';
    }

    public function order(): int
    {
        return 10;
    }

    public function component(): string
    {
        return 'Auth::SettingsProfile';
    }

    /**
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $user = auth()->user();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'last_login_at' => $user->last_login_at,
                'has_uploaded_avatar' => $user->hasMedia('avatars'),
                'has_password' => ! empty($user->password),
                'social_accounts' => $user->connected_providers,
            ],
            'available_providers' => config('services.socialite_providers', []),
        ];
    }
}
