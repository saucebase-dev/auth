<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Modules\Auth\Notifications\LoginNotification;
use Modules\Auth\Notifications\PasskeyAddedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Text a user or an attacker controls must not become a link in a security email:
 * a planted passkey name or a forged User-Agent would otherwise put a phishing
 * link inside the very alert that warns the owner.
 */
class NotificationMarkdownInjectionTest extends TestCase
{
    use RefreshDatabase;

    private const string PAYLOAD = '[Secure your account](https://evil.example) ![x](https://evil.example/p.png)';

    /**
     * @return array<string, array{0: callable(): Notification}>
     */
    public static function notifications(): array
    {
        return [
            'passkey name' => [fn (): Notification => new PasskeyAddedNotification(self::PAYLOAD)],
            'login user agent' => [fn (): Notification => new LoginNotification(CarbonImmutable::now(), '203.0.113.10', self::PAYLOAD)],
        ];
    }

    #[DataProvider('notifications')]
    public function test_user_controlled_text_renders_as_plain_text(callable $make): void
    {
        $html = (string) $make()->toMail(User::factory()->create())->render();

        $this->assertStringNotContainsString('evil.example"', $html);
        $this->assertStringNotContainsString('<img src="https://evil', $html);
        $this->assertStringContainsString('Secure your account', $html);
    }

    public function test_passkey_added_notification_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new PasskeyAddedNotification('Work laptop'));
    }
}
