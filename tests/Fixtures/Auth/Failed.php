<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Auth;

/**
 * Test stub mirroring Azera\Auth\Event\Failed.
 */
final class Failed
{
    public function __construct(
        public readonly string $guardName,
        public readonly array $credentials,
        public readonly mixed $user = null,
    ) {}
}