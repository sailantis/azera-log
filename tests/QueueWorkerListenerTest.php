<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Log\Listener\QueueWorkerListener;
use Azera\Queue\Event\JobFailed;
use Azera\Queue\Event\JobProcessed;
use Azera\Queue\Event\JobPushed;
use Azera\Queue\Event\JobReleased;
use Azera\Queue\Event\JobReserved;
use Azera\Queue\Event\JobRetried;
use PHPUnit\Framework\TestCase;

final class QueueWorkerListenerTest extends TestCase
{
    private RecordingLogger $logger;

    private QueueWorkerListener $listener;

    protected function setUp(): void
    {
        $this->logger   = new RecordingLogger();
        $this->listener = new QueueWorkerListener($this->logger);
    }

    public function test_job_pushed_logs_debug(): void
    {
        ($this->listener)(new JobPushed('emails', 'job-1', 'App\Jobs\SendEmail'));

        self::assertCount(1, $this->logger->records);
        self::assertSame('debug', $this->logger->records[0][0]);
        self::assertSame('Job pushed', $this->logger->records[0][1]);
        self::assertSame('emails', $this->logger->records[0][2]['queue']);
        self::assertSame('job-1', $this->logger->records[0][2]['job_id']);
        self::assertSame('App\Jobs\SendEmail', $this->logger->records[0][2]['job_class']);
    }

    public function test_job_reserved_logs_debug(): void
    {
        ($this->listener)(new JobReserved('emails', 'job-1', 'App\Jobs\SendEmail', 2));

        self::assertSame('debug', $this->logger->records[0][0]);
        self::assertSame('Job reserved', $this->logger->records[0][1]);
        self::assertSame(2, $this->logger->records[0][2]['attempt']);
    }

    public function test_job_processed_logs_info_with_duration(): void
    {
        ($this->listener)(new JobProcessed('emails', 'job-1', 'App\Jobs\SendEmail', 1, 42.5));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('Job processed', $this->logger->records[0][1]);
        self::assertSame(42.5, $this->logger->records[0][2]['duration_ms']);
    }

    public function test_job_released_logs_debug(): void
    {
        ($this->listener)(new JobReleased('emails', 'job-1', 1700000000));

        self::assertSame('debug', $this->logger->records[0][0]);
        self::assertSame('Job released', $this->logger->records[0][1]);
        self::assertSame(1700000000, $this->logger->records[0][2]['available_at']);
    }

    public function test_job_retried_logs_warning_with_error(): void
    {
        $exception = new \RuntimeException('connection timeout');

        ($this->listener)(new JobRetried('emails', 'job-1', 'App\Jobs\SendEmail', $exception, 2, 30));

        self::assertSame('warning', $this->logger->records[0][0]);
        self::assertSame('Job retried', $this->logger->records[0][1]);
        self::assertSame('connection timeout', $this->logger->records[0][2]['error']);
        self::assertSame(30, $this->logger->records[0][2]['delay_seconds']);
        self::assertSame(2, $this->logger->records[0][2]['attempt']);
    }

    public function test_job_failed_logs_error_with_exception(): void
    {
        $exception = new \RuntimeException('boom');

        ($this->listener)(new JobFailed('emails', 'job-1', 'App\Jobs\SendEmail', $exception, 3));

        self::assertSame('error', $this->logger->records[0][0]);
        self::assertSame('Job failed', $this->logger->records[0][1]);
        self::assertSame('boom', $this->logger->records[0][2]['error']);
        self::assertSame(3, $this->logger->records[0][2]['attempt']);
    }

    public function test_null_job_id_omitted_from_context(): void
    {
        ($this->listener)(new JobPushed('emails', null));

        self::assertArrayNotHasKey('job_id', $this->logger->records[0][2]);
        self::assertArrayNotHasKey('job_class', $this->logger->records[0][2]);
    }

    public function test_unknown_event_does_nothing(): void
    {
        ($this->listener)(new \stdClass());

        self::assertSame([], $this->logger->records);
    }
}