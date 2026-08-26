<?php

declare(strict_types=1);

namespace Azera\Log\Listener;

use Azera\Db\Event\QueryExecuted;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-14 listener that logs only slow queries.
 *
 * Logs a `warning` when a {@see QueryExecuted} event's
 * `durationMs` exceeds the configured threshold. Queries below the
 * threshold are silently ignored, so this listener is cheap to keep
 * registered in production.
 */
class SlowQueryHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private float $thresholdMs = 100.0,
        private string $level = LogLevel::WARNING,
    ) {}

    public function __invoke(QueryExecuted $event): void
    {
        if ($event->durationMs <= $this->thresholdMs) {
            return;
        }

        $this->logger->log($this->level, 'Slow query detected', [
            'sql'         => $event->sql,
            'duration_ms' => round($event->durationMs, 2),
            'params'      => $event->params,
        ]);
    }
}