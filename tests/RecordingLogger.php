<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Psr\Log\AbstractLogger;

/**
 * A recording PSR-3 logger that stores every log call for assertions.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var array{0:string, 1:string, 2:array<mixed>}[] */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}