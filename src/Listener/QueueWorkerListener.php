<?php

declare(strict_types=1);

namespace Azera\Log\Listener;

use Azera\Queue\Event\JobFailed;
use Azera\Queue\Event\JobProcessed;
use Azera\Queue\Event\JobPushed;
use Azera\Queue\Event\JobReleased;
use Azera\Queue\Event\JobReserved;
use Azera\Queue\Event\JobRetried;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-14 listener that logs queue job lifecycle events.
 *
 * Mirrors the {@see DbQueryLogger} pattern for the `azera-queue`
 * package. By default it logs:
 *
 *  - {@see JobPushed}    — debug  — "Job pushed"
 *  - {@see JobReserved}  — debug  — "Job reserved"
 *  - {@see JobProcessed} — info   — "Job processed"
 *  - {@see JobReleased}  — debug  — "Job released"
 *  - {@see JobRetried}   — warning — "Job retried"
 *  - {@see JobFailed}    — error  — "Job failed"
 *
 * Register with the framework's `EventDispatcher`:
 *
 * <code>
 * $dispatcher->listen(JobPushed::class, $listener);
 * $dispatcher->listen(JobReserved::class, $listener);
 * $dispatcher->listen(JobProcessed::class, $listener);
 * $dispatcher->listen(JobReleased::class, $listener);
 * $dispatcher->listen(JobRetried::class, $listener);
 * $dispatcher->listen(JobFailed::class, $listener);
 * </code>
 *
 * All log records include the queue name and job id (when available);
 * processed/retried records include the duration and attempt; failed
 * records include the exception.
 */
class QueueWorkerListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(object $event): void
    {
        match (true) {
            $event instanceof JobPushed => $this->logger->log(
                LogLevel::DEBUG,
                'Job pushed',
                array_filter([
                    'queue'     => $event->queue,
                    'job_id'    => $event->jobId,
                    'job_class' => $event->jobClass,
                ], fn($v) => $v !== null),
            ),

            $event instanceof JobReserved => $this->logger->log(
                LogLevel::DEBUG,
                'Job reserved',
                array_filter([
                    'queue'     => $event->queue,
                    'job_id'    => $event->jobId,
                    'job_class' => $event->jobClass,
                    'attempt'   => $event->attempt,
                ], fn($v) => $v !== null),
            ),

            $event instanceof JobProcessed => $this->logger->log(
                LogLevel::INFO,
                'Job processed',
                array_filter([
                    'queue'       => $event->queue,
                    'job_id'      => $event->jobId,
                    'job_class'   => $event->jobClass,
                    'attempt'     => $event->attempt,
                    'duration_ms' => round($event->durationMs, 2),
                ], fn($v) => $v !== null),
            ),

            $event instanceof JobReleased => $this->logger->log(
                LogLevel::DEBUG,
                'Job released',
                array_filter([
                    'queue'        => $event->queue,
                    'job_id'       => $event->jobId,
                    'available_at' => $event->availableAt,
                ], fn($v) => $v !== null),
            ),

            $event instanceof JobRetried => $this->logger->log(
                LogLevel::WARNING,
                'Job retried',
                array_filter([
                    'queue'         => $event->queue,
                    'job_id'        => $event->jobId,
                    'job_class'     => $event->jobClass,
                    'attempt'       => $event->attempt,
                    'delay_seconds' => $event->delaySeconds,
                    'error'         => $event->exception->getMessage(),
                ], fn($v) => $v !== null),
            ),

            $event instanceof JobFailed => $this->logger->log(
                LogLevel::ERROR,
                'Job failed',
                array_filter([
                    'queue'     => $event->queue,
                    'job_id'    => $event->jobId,
                    'job_class' => $event->jobClass,
                    'attempt'   => $event->attempt,
                    'error'     => $event->exception->getMessage(),
                    'exception' => $event->exception,
                ], fn($v) => $v !== null),
            ),

            default => null
        };
    }
}