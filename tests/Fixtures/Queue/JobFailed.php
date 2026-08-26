<?php

declare(strict_types=1);

namespace Azera\Log\Tests\Fixtures\Queue;

/**
 * Test stub mirroring Azera\Queue\Event\JobFailed.
 */
final class JobFailed
{
    public function __construct(
        public readonly string $queue,
        public readonly ?string $jobId,
        public readonly ?string $jobClass,
        public readonly \Throwable $exception,
        public readonly int $attempt,
    ) {}
}