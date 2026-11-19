<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\RegistrationInvite;

class EnsureRegistrationEnabled
{
    public function __construct(private readonly AuthSettings $settings) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // Closed sign-up still lets an invited address in; RegisterRequest holds it to that address.
        abort_unless($this->settings->registration_enabled || RegistrationInvite::email() !== null, 404);

        return $next($request);
    }
}
