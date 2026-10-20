<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;
use Modules\Auth\Notifications\LoginNotification;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\Facades\Home;
use Tests\TestCase;

class SocialiteCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function enableGithub(): AuthSettings
    {
        $settings = app(AuthSettings::class);
        $settings->enabled_socialite_providers = ['github'];
        $settings->save();

        return $settings;
    }

    private function makeSocialiteUser(
        string $id = 'provider-123',
        string $email = 'socialuser@example.com',
        string $name = 'Social User',
    ): SocialiteUser {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = $id;
        $socialiteUser->email = $email;
        $socialiteUser->name = $name;
        $socialiteUser->token = 'access-token';
        $socialiteUser->refreshToken = 'refresh-token';
        $socialiteUser->avatar = 'https://example.com/avatar.jpg';

        return $socialiteUser;
    }

    private function mockSocialiteDriver(SocialiteUser $socialiteUser): void
    {
        $abstractUser = $socialiteUser;

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn(
                \Mockery::mock(AbstractProvider::class, function (MockInterface $mock) use ($abstractUser) {
                    $mock->shouldReceive('user')->andReturn($abstractUser);
                }),
            );
    }

    public function test_social_callback_does_not_create_user_when_registration_is_disabled(): void
    {
        $settings = $this->enableGithub();
        $settings->registration_enabled = false;
        $settings->save();

        $this->mockSocialiteDriver($this->makeSocialiteUser());

        $response = $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'socialuser@example.com']);
    }

    /** A new account is a sign-up, so the application can send it somewhere else. */
    public function test_social_callback_lands_on_home_with_the_reason(): void
    {
        $this->enableGithub();
        Home::using(fn (Request $request, string $reason): string => url("/landing/{$reason}"));

        $this->mockSocialiteDriver($this->makeSocialiteUser());
        $this->get(route('auth.socialite.callback', ['provider' => 'github']))
            ->assertRedirect(url('/landing/registered'));

        Auth::logout();

        $this->get(route('auth.socialite.callback', ['provider' => 'github']))
            ->assertRedirect(url('/landing/login'));
    }

    public function test_social_callback_still_logs_in_existing_user_when_registration_is_disabled(): void
    {
        $settings = $this->enableGithub();
        $settings->registration_enabled = false;
        $settings->save();

        $user = $this->createUser();
        $this->mockSocialiteDriver($this->makeSocialiteUser(email: $user->email));

        $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_sets_last_social_provider_cookie(): void
    {
        $this->enableGithub();

        $socialiteUser = $this->makeSocialiteUser();
        $this->mockSocialiteDriver($socialiteUser);

        $response = $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $response->assertRedirect();
        $response->assertCookie('last_social_provider', 'github');
    }

    public function test_returning_user_receives_login_notification_after_social_authentication(): void
    {
        Notification::fake();

        $settings = $this->enableGithub();
        $settings->login_notification_enabled = true;
        $settings->save();

        $user = $this->createUser();
        $socialiteUser = $this->makeSocialiteUser(email: $user->email);
        $this->mockSocialiteDriver($socialiteUser);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.12',
            'HTTP_USER_AGENT' => 'Social Browser 1.0',
        ])->get(route('auth.socialite.callback', ['provider' => 'github']));

        Notification::assertSentTo(
            $user,
            LoginNotification::class,
            fn (LoginNotification $notification): bool => $notification->ipAddress === '203.0.113.12'
                && $notification->userAgent === 'Social Browser 1.0',
        );
    }

    public function test_first_time_social_signup_does_not_send_login_notification(): void
    {
        Notification::fake();

        $settings = $this->enableGithub();
        $settings->login_notification_enabled = true;
        $settings->save();

        $socialiteUser = $this->makeSocialiteUser();
        $this->mockSocialiteDriver($socialiteUser);

        $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $user = User::where('email', $socialiteUser->email)->sole();

        Notification::assertNotSentTo($user, LoginNotification::class);
    }

    /**
     * Signing in with a provider is not proof of the address: nothing here has
     * asked the person to confirm it, so the account starts unverified.
     */
    public function test_social_signup_does_not_mark_the_email_verified(): void
    {
        $this->enableGithub();

        $socialiteUser = $this->makeSocialiteUser();
        $this->mockSocialiteDriver($socialiteUser);

        $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertDatabaseHas('users', [
            'email' => $socialiteUser->email,
            'email_verified_at' => null,
        ]);
    }

    public function test_callback_does_not_set_cookie_during_account_linking(): void
    {
        $this->enableGithub();

        $user = $this->createUser();
        $socialiteUser = $this->makeSocialiteUser(email: $user->email);
        $this->mockSocialiteDriver($socialiteUser);

        $response = $this->actingAs($user)
            ->get(route('auth.socialite.callback', ['provider' => 'github']));

        $response->assertRedirect();
        $response->assertCookieMissing('last_social_provider');
    }

    public function test_a_signed_in_user_links_the_provider_account(): void
    {
        $this->enableGithub();
        $user = $this->createUser();
        $this->mockSocialiteDriver($this->makeSocialiteUser(id: 'gh-42', email: $user->email));

        $this->actingAs($user)->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'gh-42',
            'provider_refresh_token' => 'refresh-token',
        ]);
        $this->assertSame(trans('auth::socialite.account_connected', ['provider' => 'Github']), session('toast')['message']);
    }

    public function test_linking_an_account_owned_by_someone_else_is_refused_with_its_reason(): void
    {
        $this->enableGithub();
        $owner = $this->createUser();
        $owner->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'gh-42']);
        $user = $this->createUser();
        $this->mockSocialiteDriver($this->makeSocialiteUser(id: 'gh-42', email: $user->email));

        $this->actingAs($user)->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertSame(trans('auth::socialite.account_already_linked', ['provider' => 'Github']), session('toast')['message']);
    }

    public function test_an_unexpected_linking_failure_is_reported_and_shown_generically(): void
    {
        Exceptions::fake();
        $this->enableGithub();
        Socialite::shouldReceive('driver')->with('github')->andThrow(new \RuntimeException('provider down'));

        $this->actingAs($this->createUser())->get(route('auth.socialite.callback', ['provider' => 'github']));

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'provider down');
        $this->assertSame(trans('auth::socialite.error'), session('toast')['message']);
    }

    public function test_a_provider_user_without_an_email_is_refused(): void
    {
        $this->enableGithub();
        $this->mockSocialiteDriver($this->makeSocialiteUser(email: ''));

        $this->get(route('auth.socialite.callback', ['provider' => 'github']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(trans('auth::socialite.invalid_user'), session('toast')['message']);
    }

    public function test_a_provider_user_without_an_email_cannot_be_linked(): void
    {
        $this->enableGithub();
        $user = $this->createUser();
        $this->mockSocialiteDriver($this->makeSocialiteUser(email: 'not-an-email'));

        $this->actingAs($user)->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertSame(trans('auth::socialite.invalid_user'), session('toast')['message']);
    }

    public function test_a_new_social_user_is_signed_in_on_a_fresh_session_and_welcomed(): void
    {
        $this->enableGithub();
        $this->mockSocialiteDriver($this->makeSocialiteUser(name: 'Jane Social'));
        $this->startSession();
        $sessionBefore = session()->getId();

        $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertAuthenticatedAs(User::where('email', 'socialuser@example.com')->firstOrFail());
        $this->assertNotSame($sessionBefore, session()->getId(), 'Session regenerated against fixation');
        $this->assertSame(__('auth::auth.welcome', ['name' => 'Jane Social']), session('toast')['message']);
    }

    public function test_a_new_social_user_stores_the_refresh_token(): void
    {
        $this->enableGithub();
        $this->mockSocialiteDriver($this->makeSocialiteUser(id: 'gh-7'));

        $this->get(route('auth.socialite.callback', ['provider' => 'github']));

        $this->assertDatabaseHas('social_accounts', ['provider_id' => 'gh-7', 'provider_refresh_token' => 'refresh-token']);
    }
}
