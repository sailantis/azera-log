<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Queue;

/**
 * Test stub mirroring Azera\Queue\Event\JobProcessed.
 */
final class JobProcessed
{
    public function __construct(
        public readonly string $queue,
        public readonly ?string $jobId,
        public readonly ?string $jobClass = null,
        public readonly int $attempt = 1,
        public readonly float $durationMs = 0.0,
    ) {}
}