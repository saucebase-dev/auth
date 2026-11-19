<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Notifications\PasswordChangedNotification;
use STS\FilamentImpersonate\ImpersonateManager;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $response = $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->getAuthPassword()));
    }

    /** Signed up through a provider: there is no current password to give, so none is asked. */
    public function test_a_user_without_a_password_can_set_one(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $user->forceFill(['password' => null])->save();

        $this->actingAs($user)->put(route('settings.profile.password.update'), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->getAuthPassword()));
    }

    /** Like adding a passkey: a password set while impersonating would outlive the impersonation. */
    public function test_a_first_password_cannot_be_set_while_impersonating(): void
    {
        $user = $this->createUser();
        $user->forceFill(['password' => null])->save();

        $this->actingAs($user)
            ->withSession([ImpersonateManager::SESSION_KEY => $this->createUser()->id])
            ->put(route('settings.profile.password.update'), ['password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])
            ->assertForbidden();

        $this->assertNull($user->fresh()->password);
    }

    /** Having one, it is still the proof asked for. */
    public function test_a_user_with_a_password_must_give_it(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->put(route('settings.profile.password.update'), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_password_changed_notification_is_sent(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_update_fails_with_wrong_current_password(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertInvalid('current_password');
    }

    public function test_update_fails_when_password_not_confirmed(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertInvalid('password');
    }

    public function test_guest_cannot_update_password(): void
    {
        $response = $this->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_update_password_from_the_settings_route(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $response = $this->actingAs($user)->put(route('settings.profile.password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->getAuthPassword()));
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_settings_route_rejects_a_wrong_current_password(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->put(route('settings.profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertInvalid('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->getAuthPassword()));
    }
}
