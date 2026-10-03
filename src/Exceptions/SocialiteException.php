<?php

namespace Modules\Auth\Exceptions;

use Exception;

class SocialiteException extends Exception
{
    public static function invalidSocialUser(): self
    {
        return new self(trans('auth::socialite.invalid_user'));
    }

    public static function cannotDisconnectOnlyMethod(): self
    {
        return new self(trans('auth::socialite.cannot_disconnect_only_method'));
    }

    public static function providerNotConnected(string $provider): self
    {
        return new self(trans('auth::socialite.not_connected', ['Provider' => $provider]));
    }

    public static function accountAlreadyLinked(string $provider): self
    {
        return new self(trans('auth::socialite.account_already_linked', ['provider' => ucfirst($provider)]));
    }

    public static function registrationDisabled(): self
    {
        return new self(trans('auth::socialite.registration_disabled'));
    }

    public static function unsupportedProvider(string $provider): self
    {
        return new self(trans('auth::socialite.unsupported_provider', ['provider' => ucfirst($provider)]));
    }
}
