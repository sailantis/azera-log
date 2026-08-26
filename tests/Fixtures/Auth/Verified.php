<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Auth;

/**
 * Test stub mirroring Azera\Auth\Event\Verified.
 */
final class Verified
{
    public function __construct(
        public readonly mixed $user,
    ) {}
}