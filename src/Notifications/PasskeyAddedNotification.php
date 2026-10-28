<?php

namespace Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Saucebase\Core\Settings\SettingsSection;

/**
 * Tells the owner a passkey was added to their account.
 *
 * A passkey is a credential that outlives the session that created it, so one
 * planted from a hijacked session would otherwise be a silent, standing way in.
 * Always sent; there is no setting to turn it off.
 */
class PasskeyAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $passkeyName,
    ) {}

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

        return (new MailMessage)
            ->subject(__('auth::auth.passkey-added-notification.subject', ['app' => $appName]))
            ->greeting(__('auth::auth.passkey-added-notification.greeting', ['name' => $notifiable->name]))
            ->line(__('auth::auth.passkey-added-notification.notice', ['app' => $appName, 'passkey' => $this->passkeyName]))
            ->line(__('auth::auth.passkey-added-notification.recognized'))
            ->action(__('auth::auth.passkey-added-notification.action'), SettingsSection::url('security'))
            ->line(__('auth::auth.passkey-added-notification.unrecognized'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'passkey_name' => $this->passkeyName,
        ];
    }
}
