<?php

namespace Modules\Auth\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Saucebase\Core\Support\Markdown;

class LoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ?string $userAgent;

    public function __construct(
        public CarbonInterface $loggedInAt,
        public ?string $ipAddress,
        ?string $userAgent,
    ) {
        $this->userAgent = $userAgent === null
            ? null
            : Str::limit($userAgent, 500, '');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name');
        $unknown = __('auth::auth.login-notification.unknown');
        $loggedInAt = $this->loggedInAt
            ->copy()
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->locale(app()->getLocale())
            ->isoFormat('LLL Z');

        return (new MailMessage)
            ->subject(__('auth::auth.login-notification.subject', ['app' => $appName]))
            ->greeting(__('auth::auth.login-notification.greeting', ['name' => $notifiable->name]))
            ->line(__('auth::auth.login-notification.notice', ['app' => $appName]))
            ->line(__('auth::auth.login-notification.app', ['app' => $appName]))
            ->line(__('auth::auth.login-notification.time', ['time' => $loggedInAt]))
            ->line(__('auth::auth.login-notification.ip-address', [
                'ip' => $this->ipAddress ?? $unknown,
            ]))
            ->line(__('auth::auth.login-notification.device-details', [
                'device' => $this->userAgent !== null ? Markdown::escape($this->userAgent) : $unknown,
            ]))
            ->line(__('auth::auth.login-notification.recognized'))
            ->action(__('auth::auth.login-notification.action'), route('password.request'))
            ->line(__('auth::auth.login-notification.unrecognized'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'logged_in_at' => $this->loggedInAt->toIso8601String(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
