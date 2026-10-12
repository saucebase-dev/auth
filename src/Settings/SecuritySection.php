<?php

namespace Modules\Auth\Settings;

use Saucebase\Core\Settings\SettingsSection;

/**
 * How the signed-in user proves who they are: their password and their
 * connected social logins.
 *
 * Separate from Profile because these are not descriptions of a person, they are
 * the credentials that let somebody in — a different question, asked in a
 * different frame of mind. Sits immediately after Profile: both are about the
 * account itself, before anything a module contributes about an organisation.
 */
class SecuritySection extends SettingsSection
{
    public function slug(): string
    {
        return 'security';
    }

    public function title(): string
    {
        return __('Security');
    }

    public function icon(): ?string
    {
        return 'security';
    }

    /** Straight after Profile (10), ahead of anything a module adds. */
    public function order(): int
    {
        return 15;
    }

    public function component(): string
    {
        return 'Auth::SettingsSecurity';
    }

    /**
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $user = auth()->user();

        return [
            'user' => [
                'has_password' => ! empty($user->password),
                'social_accounts' => $user->connected_providers,
            ],
            'available_providers' => config('services.socialite_providers', []),
        ];
    }
}
