<?php

namespace Modules\Auth\Settings;

use Modules\Auth\Support\PasswordConfirmation;
use Saucebase\Core\Settings\SettingsSection;
use STS\FilamentImpersonate\ImpersonateManager;

/**
 * How the signed-in user proves who they are: their password, their passkeys and
 * their connected social logins.
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
            // Never the `credential` column: it is the stored public key and
            // signature counter, and nothing the panel needs.
            'passkeys' => $user->passkeys()
                ->latest()
                ->get(['id', 'name', 'credential', 'last_used_at', 'created_at'])
                ->map(fn ($passkey): array => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'authenticator' => $passkey->authenticator,
                    'last_used_at' => $passkey->last_used_at,
                    'created_at' => $passkey->created_at,
                ])
                ->all(),
            'can_add_passkeys' => ! app(ImpersonateManager::class)->isImpersonating(),
            ...PasswordConfirmation::props(),
        ];
    }
}
