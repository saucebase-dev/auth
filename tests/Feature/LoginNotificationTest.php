<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Modules\Auth\Notifications\LoginNotification;
use Tests\TestCase;

class LoginNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_notification_contains_security_context_and_recovery_action(): void
    {
        config([
            'app.name' => 'Saucebase',
            'app.timezone' => 'UTC',
        ]);

        $user = User::factory()->create(['name' => 'Ana']);
        $loggedInAt = CarbonImmutable::parse('2026-08-24 15:19:00', 'UTC');
        $notification = new LoginNotification(
            loggedInAt: $loggedInAt,
            ipAddress: '203.0.113.10',
            userAgent: 'Test Browser 1.0',
        );

        $mail = $notification->toMail($user);

        $this->assertSame('New sign-in to your Saucebase account', $mail->subject);
        $this->assertSame('Hello Ana,', $mail->greeting);
        $this->assertContains('We noticed a new sign-in to your Saucebase account.', $mail->introLines);
        $this->assertContains('App: Saucebase', $mail->introLines);
        $this->assertContains('Time: August 24, 2026 3:19 PM +00:00', $mail->introLines);
        $this->assertContains('IP address: 203.0.113.10', $mail->introLines);
        $this->assertContains('Device details: Test Browser 1.0', $mail->introLines);
        $this->assertContains('If this was you, no action is needed.', $mail->introLines);
        $this->assertSame('Reset your password', $mail->actionText);
        $this->assertSame(route('password.request'), $mail->actionUrl);
        $this->assertContains(
            "If you don't recognize this activity, reset your password immediately.",
            $mail->outroLines,
        );
        $this->assertSame([
            'logged_in_at' => '2026-08-24T15:19:00+00:00',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Test Browser 1.0',
        ], $notification->toArray($user));
    }

    public function test_login_notification_bounds_device_details_to_five_hundred_characters(): void
    {
        $user = User::factory()->create();
        $notification = new LoginNotification(
            loggedInAt: CarbonImmutable::parse('2026-08-24 15:19:00', 'UTC'),
            ipAddress: '203.0.113.10',
            userAgent: Str::repeat('A', 600),
        );

        $mail = $notification->toMail($user);

        $this->assertContains(
            'Device details: '.Str::repeat('A', 500),
            $mail->introLines,
        );
    }

    public function test_login_notification_uses_fallbacks_for_missing_request_metadata(): void
    {
        $user = User::factory()->create();
        $notification = new LoginNotification(
            loggedInAt: CarbonImmutable::parse('2026-08-24 15:19:00', 'UTC'),
            ipAddress: null,
            userAgent: null,
        );

        $mail = $notification->toMail($user);

        $this->assertContains('IP address: Unknown', $mail->introLines);
        $this->assertContains('Device details: Unknown', $mail->introLines);
    }

    public function test_login_notification_is_queued(): void
    {
        $notification = new LoginNotification(
            loggedInAt: CarbonImmutable::parse('2026-08-24 15:19:00', 'UTC'),
            ipAddress: null,
            userAgent: null,
        );

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_login_notification_uses_the_recipient_language(): void
    {
        config(['app.name' => 'Saucebase']);
        Lang::addLines([
            'auth.login-notification.subject' => 'NOVO :app',
            'auth.login-notification.greeting' => 'OLA :name',
            'auth.login-notification.action' => 'REDEFINIR',
        ], 'xx', 'auth');
        App::setLocale('xx');

        $user = User::factory()->create(['name' => 'Ana']);
        $notification = new LoginNotification(
            loggedInAt: CarbonImmutable::parse('2026-08-24 15:19:00', 'UTC'),
            ipAddress: '203.0.113.10',
            userAgent: 'Test Browser 1.0',
        );

        $mail = $notification->toMail($user);

        $this->assertSame('NOVO Saucebase', $mail->subject);
        $this->assertSame('OLA Ana', $mail->greeting);
        $this->assertSame('REDEFINIR', $mail->actionText);
    }
}
