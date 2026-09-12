<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Auth\Models\MagicLinkToken;
use Modules\Auth\Notifications\LoginNotification;
use Modules\Auth\Notifications\MagicLinkNotification;
use Modules\Auth\Settings\AuthSettings;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    /** Magic links ship switched off; every test here but the last needs them on. */
    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(AuthSettings::class);
        $settings->magic_link_enabled = true;
        $settings->save();
    }

    public function test_magic_link_page_renders(): void
    {
        $response = $this->get(route('magic-link.create'));

        $response->assertStatus(200);
    }

    public function test_authenticated_user_is_redirected_from_magic_link_page(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('magic-link.create'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_magic_link_sends_notification_for_existing_user(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->post(route('magic-link.store'), ['email' => $user->email]);

        Notification::assertSentTo($user, MagicLinkNotification::class);
    }

    public function test_magic_link_does_not_reveal_nonexistent_email(): void
    {
        Notification::fake();

        $response = $this->post(route('magic-link.store'), ['email' => 'nobody@example.com']);

        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_magic_link_validates_email_is_required(): void
    {
        $response = $this->post(route('magic-link.store'), []);

        $response->assertInvalid('email');
    }

    public function test_magic_link_validates_email_format(): void
    {
        $response = $this->post(route('magic-link.store'), ['email' => 'not-an-email']);

        $response->assertInvalid('email');
    }

    public function test_user_can_authenticate_with_valid_token(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->get(route('magic-link.authenticate', $plainToken));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_returning_user_receives_login_notification_after_magic_link_authentication(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->login_notification_enabled = true;
        $settings->save();

        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.11',
            'HTTP_USER_AGENT' => 'Magic Browser 1.0',
        ])->get(route('magic-link.authenticate', $plainToken));

        Notification::assertSentTo(
            $user,
            LoginNotification::class,
            fn (LoginNotification $notification): bool => $notification->ipAddress === '203.0.113.11'
                && $notification->userAgent === 'Magic Browser 1.0',
        );
    }

    public function test_authentication_fails_with_expired_token(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->get(route('magic-link.authenticate', $plainToken));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_authentication_fails_with_used_token(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
            'used_at' => now()->subMinutes(1),
        ]);

        $response = $this->get(route('magic-link.authenticate', $plainToken));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_authentication_fails_with_invalid_token(): void
    {
        $response = $this->get(route('magic-link.authenticate', 'invalid-token'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_token_is_marked_as_used_after_authentication(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        $token = MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('magic-link.authenticate', $plainToken));

        $this->assertNotNull($token->fresh()->used_at);
    }

    public function test_old_tokens_are_deleted_when_new_one_is_requested(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->post(route('magic-link.store'), ['email' => $user->email]);
        $this->post(route('magic-link.store'), ['email' => $user->email]);

        $this->assertDatabaseCount('magic_link_tokens', 1);
    }

    public function test_auth_settings_control_magic_link_expiry(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->magic_link_expiry = 30;
        $settings->save();

        $now = now()->startOfSecond();
        $this->travelTo($now);

        $user = $this->createUser();

        $this->post(route('magic-link.store'), ['email' => $user->email]);

        $token = MagicLinkToken::whereBelongsTo($user)->sole();

        $this->assertTrue($token->expires_at->equalTo($now->copy()->addMinutes(30)));
    }

    public function test_magic_link_notification_uses_the_configured_expiry(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->magic_link_expiry = 30;
        $settings->save();

        $user = $this->createUser();

        $this->post(route('magic-link.store'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            MagicLinkNotification::class,
            fn (MagicLinkNotification $notification): bool => in_array(
                'Click the button below to log in. This link expires in 30 minutes and can only be used once.',
                $notification->toMail($user)->introLines,
                true,
            ),
        );
    }

    public function test_intended_redirect_is_accepted_for_same_host(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $intended = urlencode(config('app.url').'/dashboard');

        $response = $this->get(route('magic-link.authenticate', $plainToken).'?intended='.$intended);

        $this->assertAuthenticated();
        $response->assertRedirect(config('app.url').'/dashboard');
    }

    public function test_open_redirect_is_rejected_for_external_url(): void
    {
        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $intended = urlencode('https://evil.com/steal');

        $response = $this->get(route('magic-link.authenticate', $plainToken).'?intended='.$intended);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_magic_link_is_disabled_by_auth_settings(): void
    {
        $settings = app(AuthSettings::class);
        $settings->magic_link_enabled = false;
        $settings->save();

        $this->get(route('magic-link.create'))->assertStatus(404);
        $this->post(route('magic-link.store'), ['email' => 'test@example.com'])->assertStatus(404);

        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('magic-link.authenticate', $plainToken))->assertStatus(404);
    }
}
