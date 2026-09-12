<?php

namespace Modules\Auth\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Filament\Pages\AuthenticationSettings;
use Modules\Auth\Settings\AuthSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_load_authentication_settings_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin);

        $this->get(AuthenticationSettings::getUrl(panel: 'admin'))
            ->assertOk();

        Livewire::test(AuthenticationSettings::class)
            ->assertFormFieldExists('enabled_socialite_providers')
            ->assertFormFieldExists('login_notification_enabled')
            ->assertSchemaStateSet([
                'enabled_socialite_providers' => [],
                'magic_link_enabled' => false,
                'magic_link_expiry' => 15,
                'login_notification_enabled' => false,
            ]);
    }

    public function test_administrator_can_save_authentication_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin);

        Livewire::test(AuthenticationSettings::class)
            ->fillForm([
                'enabled_socialite_providers' => ['google', 'github'],
                'magic_link_enabled' => false,
                'magic_link_expiry' => 30,
                'login_notification_enabled' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $settings = new AuthSettings;

        $this->assertSame(['google', 'github'], $settings->enabled_socialite_providers);
        $this->assertFalse($settings->magic_link_enabled);
        $this->assertSame(30, $settings->magic_link_expiry);
        $this->assertTrue($settings->login_notification_enabled);
    }

    public function test_administrator_can_disable_all_socialite_providers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        $settings = app(AuthSettings::class);
        $settings->enabled_socialite_providers = ['google'];
        $settings->save();

        $this->actingAs($admin);

        Livewire::test(AuthenticationSettings::class)
            ->fillForm([
                'enabled_socialite_providers' => [],
                'magic_link_enabled' => true,
                'magic_link_expiry' => 15,
                'login_notification_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame([], (new AuthSettings)->enabled_socialite_providers);
    }

    public function test_unknown_socialite_provider_does_not_change_authentication_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin);

        Livewire::test(AuthenticationSettings::class)
            ->fillForm([
                'enabled_socialite_providers' => ['unsupported'],
                'magic_link_enabled' => true,
                'magic_link_expiry' => 15,
                'login_notification_enabled' => false,
            ])
            ->call('save')
            ->assertHasFormErrors(['enabled_socialite_providers.0'])
            ->assertNotNotified();

        $this->assertSame([], (new AuthSettings)->enabled_socialite_providers);
    }

    #[DataProvider('invalidExpiryProvider')]
    public function test_invalid_expiry_does_not_change_authentication_settings(
        mixed $expiry,
        string $rule,
    ): void {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin);

        Livewire::test(AuthenticationSettings::class)
            ->fillForm([
                'magic_link_enabled' => true,
                'magic_link_expiry' => $expiry,
            ])
            ->call('save')
            ->assertHasFormErrors(['magic_link_expiry' => $rule])
            ->assertNotNotified();

        $settings = new AuthSettings;

        $this->assertFalse($settings->magic_link_enabled);
        $this->assertSame(15, $settings->magic_link_expiry);
    }

    /**
     * @return array<string, array{expiry: mixed, rule: string}>
     */
    public static function invalidExpiryProvider(): array
    {
        return [
            'expiry is required' => [
                'expiry' => null,
                'rule' => 'required',
            ],
            'expiry must be an integer' => [
                'expiry' => 'fifteen',
                'rule' => 'integer',
            ],
            'expiry must be positive' => [
                'expiry' => 0,
                'rule' => 'min',
            ],
        ];
    }

    public function test_regular_user_cannot_access_authentication_settings_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::USER);

        $this->actingAs($user)
            ->get(AuthenticationSettings::getUrl(panel: 'admin'))
            ->assertForbidden();
    }
}
