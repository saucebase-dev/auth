<?php

namespace Modules\Auth\Tests\Unit;

use Modules\Auth\Exceptions\AuthException;
use Tests\TestCase;

/**
 * The lockout message offers both units; a translation may use either.
 */
class AuthExceptionTest extends TestCase
{
    public function test_the_lockout_message_rounds_minutes_up(): void
    {
        app('translator')->addLines(['auth.throttle' => ':seconds|:minutes'], 'en', 'auth');

        $this->assertSame('61|2', AuthException::throttle(61)->getMessage());
        $this->assertSame('60|1', AuthException::throttle()->getMessage());
    }
}
