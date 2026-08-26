<?php

declare(strict_types=1);

namespace Azera\Log\Listener;

use Azera\Db\Event\DatabaseOperationFailed;
use Azera\Db\Event\QueryExecuted;
use Azera\Db\Event\ReconnectFailed;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-14 listener that logs database query lifecycle events.
 *
 * Replaces the inline closures the framework docs show (`docs/10-LOGGING.md`)
 * with a one-line wiring. By default it logs every `QueryExecuted` at
 * `debug` and `DatabaseOperationFailed`/`ReconnectFailed` at `error`.
 *
 * Register with the framework's `EventDispatcher`:
 *
 * <code>
 * $dispatcher->listen(QueryExecuted::class, $dbQueryLogger);
 * $dispatcher->listen(DatabaseOperationFailed::class, $dbQueryLogger);
 * </code>
 */
class DbQueryLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private string $queryLevel = LogLevel::DEBUG,
        private string $errorLevel = LogLevel::ERROR,
        private bool $logParams = true,
    ) {}

    public function __invoke(object $event): void
    {
        if ($event instanceof QueryExecuted) {
            $this->logger->log(
                $this->queryLevel,
                'Query executed',
                array_filter([
                    'sql'         => $event->sql,
                    'params'      => $this->logParams ? $event->params : null,
                    'duration_ms' => round($event->durationMs, 2),
                ], fn($v) => $v !== null),
            );
            return;
        }

        if ($event instanceof DatabaseOperationFailed) {
            $this->logger->log($this->errorLevel, 'Database operation failed', array_filter([
                'operation' => $event->operation,
                'sql'       => $event->sql,
                'params'    => $this->logParams ? $event->params : null,
                'error'     => $event->exception->getMessage(),
            ], fn($v) => $v !== null));
            return;
        }

        if ($event instanceof ReconnectFailed) {
            $this->logger->log($this->errorLevel, 'Database reconnect failed', [
                'attempt' => $event->attempt,
                'error'   => $event->exception->getMessage(),
            ]);
            return;
        }
    }
}