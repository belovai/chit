<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Exceptions\AiException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiExceptionTest extends TestCase
{
    #[Test]
    public function a_retryable_failure_never_counts_against_the_credential(): void
    {
        $exception = AiException::retryable('overloaded');

        $this->assertTrue($exception->isRetryable());
        $this->assertFalse($exception->isAuthFailure());
    }

    #[Test]
    public function a_permanent_failure_is_not_an_auth_failure_by_itself(): void
    {
        // A refusal or an unparseable response is permanent, but the key is
        // fine: only the adapter may say otherwise, and it did not.
        $exception = AiException::permanent('authentication_error: looks like one, is not');

        $this->assertFalse($exception->isRetryable());
        $this->assertFalse($exception->isAuthFailure());
    }

    #[Test]
    public function an_auth_failure_is_permanent_and_flagged(): void
    {
        $exception = AiException::authFailure('authentication_error: invalid x-api-key');

        $this->assertFalse($exception->isRetryable());
        $this->assertTrue($exception->isAuthFailure());
    }
}
