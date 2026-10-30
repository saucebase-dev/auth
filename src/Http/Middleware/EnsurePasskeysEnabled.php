<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Settings\AuthSettings;

class EnsurePasskeysEnabled
{
    public function __construct(private readonly AuthSettings $settings) {}

    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($this->settings->passkeys_enabled, 404);

        return $next($request);
    }
}
