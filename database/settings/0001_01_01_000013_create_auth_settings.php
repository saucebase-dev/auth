<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'auth.registration_enabled' => true,
            'auth.modal_enabled' => true,
            'auth.magic_link_enabled' => false,
            'auth.magic_link_expiry' => 15,
            'auth.login_notification_enabled' => false,
            'auth.enabled_socialite_providers' => [],
        ];

        foreach ($defaults as $property => $value) {
            if (! $this->migrator->exists($property)) {
                $this->migrator->add($property, $value);
            }
        }
    }
};
