<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Db\Database;
use Azera\Db\Event\QueryExecuted;
use Azera\Log\Listener\SlowQueryHandler;
use PHPUnit\Framework\TestCase;

final class SlowQueryHandlerTest extends TestCase
{
    public function test_logs_when_above_threshold(): void
    {
        $logger = new class implements \Psr\Log\LoggerInterface
        {
            public array $records = [];
            public function emergency($m, array $c = []): void
            {
                $this->records[] = ['emergency', $m, $c];
            }
            public function alert($m, array $c = []): void
            {
                $this->records[] = ['alert', $m, $c];
            }
            public function critical($m, array $c = []): void
            {
                $this->records[] = ['critical', $m, $c];
            }
            public function error($m, array $c = []): void
            {
                $this->records[] = ['error', $m, $c];
            }
            public function warning($m, array $c = []): void
            {
                $this->records[] = ['warning', $m, $c];
            }
            public function notice($m, array $c = []): void
            {
                $this->records[] = ['notice', $m, $c];
            }
            public function info($m, array $c = []): void
            {
                $this->records[] = ['info', $m, $c];
            }
            public function debug($m, array $c = []): void
            {
                $this->records[] = ['debug', $m, $c];
            }
            public function log($l, $m, array $c = []): void
            {
                $this->records[] = [$l, $m, $c];
            }
        };

        $db       = $this->createStub(Database::class);
        $listener = new SlowQueryHandler($logger, thresholdMs: 100.0);

        $event = new QueryExecuted($db, 'SELECT * FROM users WHERE id = ?', [42], 150.0);
        $listener($event);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertSame('Slow query detected', $logger->records[0][1]);
        self::assertSame(150.0, $logger->records[0][2]['duration_ms']);
    }

    public function test_silent_when_below_threshold(): void
    {
        $logger = new class implements \Psr\Log\LoggerInterface
        {
            public array $records = [];
            public function emergency($m, array $c = []): void
            {
                $this->records[] = ['emergency', $m, $c];
            }
            public function alert($m, array $c = []): void
            {
                $this->records[] = ['alert', $m, $c];
            }
            public function critical($m, array $c = []): void
            {
                $this->records[] = ['critical', $m, $c];
            }
            public function error($m, array $c = []): void
            {
                $this->records[] = ['error', $m, $c];
            }
            public function warning($m, array $c = []): void
            {
                $this->records[] = ['warning', $m, $c];
            }
            public function notice($m, array $c = []): void
            {
                $this->records[] = ['notice', $m, $c];
            }
            public function info($m, array $c = []): void
            {
                $this->records[] = ['info', $m, $c];
            }
            public function debug($m, array $c = []): void
            {
                $this->records[] = ['debug', $m, $c];
            }
            public function log($l, $m, array $c = []): void
            {
                $this->records[] = [$l, $m, $c];
            }
        };

        $db       = $this->createStub(Database::class);
        $listener = new SlowQueryHandler($logger, thresholdMs: 100.0);

        $event = new QueryExecuted($db, 'SELECT 1', null, 5.0);
        $listener($event);

        self::assertCount(0, $logger->records);
    }
}