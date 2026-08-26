<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Log\Handler\RollingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class RollingFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/azera_rolling_test_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->dir);
    }

    public function test_writes_to_current_file(): void
    {
        $file   = $this->dir . '/app.log';
        $logger = new Logger('test');
        $logger->pushHandler(new RollingFileHandler(
            $file,
            options: ['rollingMode' => 'date', 'datePattern' => '-Y-m-d'],
        ));

        $logger->info('hello');

        self::assertFileExists($file);
        self::assertStringContainsString('hello', (string) file_get_contents($file));
    }

    public function test_size_rotation_archives_and_truncates(): void
    {
        $file   = $this->dir . '/size.log';
        $logger = new Logger('test');
        $logger->pushHandler(new RollingFileHandler(
            $file,
            options: [
                'rollingMode' => 'size',
                'maxFileSize' => 32,
                // tiny threshold so a few short writes trigger rotation
                'maxSizeRollBackups' => 5,
                'staticLogFileName'  => true,
            ],
        ));

        // Each formatted line is ~60 bytes; write 15 to guarantee at least one rotation.
        for ($i = 0; $i < 15; $i++) {
            $logger->warning('rotating test message ' . $i);
        }

        // The current file should exist and be small (truncated after rotation).
        self::assertFileExists($file);

        // At least one backup (.1) should exist.
        $backups = glob($file . '.1') ?: [];
        self::assertNotEmpty($backups, 'Expected at least one size-rotated backup.');
    }

    public function test_max_age_prunes_old_backups(): void
    {
        $file = $this->dir . '/aged.log';

        // Create an old backup file with a date suffix, dated 10 days ago.
        $oldDate   = date('Y-m-d', strtotime('-10 days'));
        $oldBackup = $file . '-' . $oldDate;
        file_put_contents($oldBackup, 'stale content');
        touch($oldBackup, strtotime('-10 days'));

        // Create the current file so the handler has something to open.
        file_put_contents($file, 'current');

        $logger = new Logger('test');
        $logger->pushHandler(new RollingFileHandler(
            $file,
            options: [
                'rollingMode' => 'date',
                'datePattern' => '-Y-m-d',
                'maxAge'      => 3,
            ],
        ));

        // Force a rotation by setting the current file's mtime to yesterday.
        touch($file, strtotime('-1 day'));
        $logger->info('trigger rotation');

        // The old backup should be pruned because it's older than maxAge (3 days).
        self::assertFileDoesNotExist($oldBackup, 'Old backup should have been pruned by maxAge.');
    }

    public function test_date_mode_appends_date_to_current_file(): void
    {
        $file   = $this->dir . '/dated.log';
        $logger = new Logger('test');
        $logger->pushHandler(new RollingFileHandler(
            $file,
            options: [
                'rollingMode'       => 'date',
                'datePattern'       => '-Y-m-d',
                'staticLogFileName' => false,
            ],
        ));

        $logger->info('dated entry');

        // With staticLogFileName=false, the current file carries the date suffix.
        $expected = $file . '-' . date('Y-m-d');
        self::assertFileExists($expected);
        self::assertStringContainsString('dated entry', (string) file_get_contents($expected));
    }

    public function test_respects_minimum_level(): void
    {
        $file   = $this->dir . '/level.log';
        $logger = new Logger('test');
        $logger->pushHandler(new RollingFileHandler(
            $file,
            level: Level::Error,
            options: ['rollingMode' => 'date'],
        ));

        $logger->info('discarded');
        $logger->error('written');

        $content = (string) file_get_contents($file);
        self::assertStringNotContainsString('discarded', $content);
        self::assertStringContainsString('written', $content);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}