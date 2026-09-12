<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Modules\Auth\Notifications\LoginNotification;
use Modules\Auth\Settings\AuthSettings;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_login_page_receives_magic_link_availability_from_auth_settings(): void
    {
        $settings = app(AuthSettings::class);
        $settings->magic_link_enabled = false;
        $settings->save();

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('auth.magic_link_enabled', false)
            );
    }

    public function test_login_page_receives_only_enabled_socialite_providers(): void
    {
        $settings = app(AuthSettings::class);
        $settings->enabled_socialite_providers = ['github', 'unsupported'];
        $settings->save();

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('auth.socialite_providers', [
                    [
                        'name' => 'github',
                        'label' => 'GitHub',
                    ],
                ])
            );
    }

    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_returning_user_receives_login_notification_when_enabled(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->login_notification_enabled = true;
        $settings->save();

        $loggedInAt = now()->startOfSecond();
        $this->travelTo($loggedInAt);

        $user = $this->createUser();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Test Browser 1.0',
        ])->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Notification::assertSentTo(
            $user,
            LoginNotification::class,
            fn (LoginNotification $notification): bool => $notification->loggedInAt->equalTo($loggedInAt)
                && $notification->ipAddress === '203.0.113.10'
                && $notification->userAgent === 'Test Browser 1.0',
        );
    }

    public function test_returning_user_does_not_receive_login_notification_when_disabled(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Notification::assertNotSentTo($user, LoginNotification::class);
    }

    public function test_invalid_credentials_do_not_send_login_notification(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->login_notification_enabled = true;
        $settings->save();

        $user = $this->createUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        Notification::assertNotSentTo($user, LoginNotification::class);
    }

    public function test_login_with_wrong_password_returns_error(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('error');
    }

    public function test_login_validates_email_is_required(): void
    {
        $response = $this->post(route('login'), [
            'password' => 'password',
        ]);

        $response->assertInvalid('email');
    }

    public function test_login_validates_password_is_required(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
        ]);

        $response->assertInvalid('password');
    }

    public function test_login_validates_email_format(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertInvalid('email');
    }

    public function test_login_fires_lockout_event_after_five_failed_attempts(): void
    {
        Event::fake([Lockout::class]);

        $user = $this->createUser();

        // Make 5 failed attempts to build up the rate limiter
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt triggers the lockout
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        Event::assertDispatched(Lockout::class);
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = $this->createUser();

        $this->assertNull($user->last_login_at);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
    }

    /**
     * A GET logout is reachable from any other site's markup, so signing out has
     * to be something the app asked for.
     */
    public function test_logout_is_not_reachable_by_get(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/auth/logout')->assertStatus(405);

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_redirects_to_home(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
    }
}
