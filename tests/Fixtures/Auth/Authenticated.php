<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Auth;

/**
 * Test stub mirroring Azera\Auth\Event\Authenticated.
 */
final class Authenticated
{
    public function __construct(
        public readonly string $guardName,
        public readonly mixed $user,
    ) {}
}