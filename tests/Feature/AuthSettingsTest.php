<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Settings\AuthSettings;
use Tests\TestCase;

class AuthSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_has_authentication_defaults(): void
    {
        $settings = app(AuthSettings::class);

        $this->assertSame([], $settings->enabled_socialite_providers);
        $this->assertTrue($settings->registration_enabled);
        $this->assertFalse($settings->magic_link_enabled);
        $this->assertSame(15, $settings->magic_link_expiry);
        $this->assertFalse($settings->login_notification_enabled);
    }
}
