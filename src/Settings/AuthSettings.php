<?php

namespace Modules\Auth\Settings;

use Saucebase\Core\Settings\InstallSetting;
use Spatie\LaravelSettings\Settings;

class AuthSettings extends Settings
{
    /** @var list<string> */
    public array $enabled_socialite_providers;

    #[InstallSetting('Let people sign up?', hint: 'Off: only invited or admin-created accounts.')]
    public bool $registration_enabled;

    /** Whether signing in and registering open in a modal over the current page. */
    #[InstallSetting('Open sign-in and sign-up in a modal?')]
    public bool $modal_enabled;

    #[InstallSetting('Let people sign in with an emailed link?')]
    public bool $magic_link_enabled;

    public int $magic_link_expiry;

    public bool $login_notification_enabled;

    /** Whether users can register passkeys and sign in with them. */
    #[InstallSetting('Let people sign in with passkeys?')]
    public bool $passkeys_enabled;

    public static function group(): string
    {
        return 'auth';
    }
}
