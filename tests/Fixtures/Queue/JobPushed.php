<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Queue;

/**
 * Test stub mirroring Azera\Queue\Event\JobPushed.
 * Used when azera-queue is not installed.
 */
final class JobPushed
{
    public function __construct(
        public readonly string $queue,
        public readonly ?string $jobId,
        public readonly ?string $jobClass = null,
    ) {}
}