<?php

namespace Modules\Auth\Settings;

use Spatie\LaravelSettings\Settings;

class AuthSettings extends Settings
{
    /** @var list<string> */
    public array $enabled_socialite_providers;

    public bool $registration_enabled;

    /** Whether signing in and registering open in a modal over the current page. */
    public bool $modal_enabled;

    public bool $magic_link_enabled;

    public int $magic_link_expiry;

    public bool $login_notification_enabled;

    public static function group(): string
    {
        return 'auth';
    }
}
