<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Auth;

/**
 * Test stub mirroring Azera\Auth\Event\PasswordResetRequested.
 */
final class PasswordResetRequested
{
    public function __construct(
        public readonly mixed $user,
        public readonly string $token,
    ) {}
}