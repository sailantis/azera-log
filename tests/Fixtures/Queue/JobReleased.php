<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Queue;

/**
 * Test stub mirroring Azera\Queue\Event\JobReleased.
 */
final class JobReleased
{
    public function __construct(
        public readonly string $queue,
        public readonly ?string $jobId,
        public readonly int $availableAt,
    ) {}
}