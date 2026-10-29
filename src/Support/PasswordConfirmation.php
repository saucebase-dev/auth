<?php

namespace Modules\Auth\Support;

/**
 * Whether the session proved who it is recently: the password, or one of the user's
 * passkeys, within `auth.password_timeout`. The same clock Laravel's
 * `password.confirm` middleware reads, for checks a route middleware can't express
 * (changing the email, but not the name) and for telling the panels up front.
 */
class PasswordConfirmation
{
    public static function isRecent(): bool
    {
        return time() <= (static::until() ?? 0);
    }

    /** When the confirmation runs out, or null when there is none. */
    public static function until(): ?int
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return $confirmedAt > 0 ? $confirmedAt + static::timeout() : null;
    }

    public static function timeout(): int
    {
        return (int) config('auth.password_timeout', 10800);
    }

    /** For a panel: until when, not whether, so one left open asks again. */
    public static function props(): array
    {
        return [
            'password_confirmed_until' => static::until(),
            'password_timeout' => static::timeout(),
        ];
    }
}
