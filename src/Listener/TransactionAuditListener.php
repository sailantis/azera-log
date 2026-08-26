<?php

declare(strict_types=1);

namespace Azera\Log\Listener;

use Azera\Db\Event\TransactionCommitted;
use Azera\Db\Event\TransactionRolledBack;
use Azera\Db\Event\TransactionStarted;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-14 listener that traces transaction lifecycle events.
 *
 * Logs `debug` for begin/commit and `warning` for rollback, useful for
 * audit trails. Register for the three transaction events:
 *
 * <code>
 * $dispatcher->listen(TransactionStarted::class, $listener);
 * $dispatcher->listen(TransactionCommitted::class, $listener);
 * $dispatcher->listen(TransactionRolledBack::class, $listener);
 * </code>
 */
class TransactionAuditListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(object $event): void
    {
        $level  = $event instanceof TransactionRolledBack ? LogLevel::WARNING : LogLevel::DEBUG;
        $action = match (true) {
            $event instanceof TransactionStarted    => 'Transaction started',
            $event instanceof TransactionCommitted  => 'Transaction committed',
            $event instanceof TransactionRolledBack => 'Transaction rolled back',
            default                                 => 'Transaction event'
        };

        $context = [];
        if (property_exists($event, 'level')) {
            $context['level'] = $event->level;
        }

        $this->logger->log($level, $action, $context);
    }
}