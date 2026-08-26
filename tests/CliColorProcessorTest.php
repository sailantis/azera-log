<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Log\Processor\AnsiColorProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class CliColorProcessorTest extends TestCase
{
    private function record(Level $level, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: $level,
            message: 'test',
            context: $context,
        );
    }

    public function test_injects_color_and_reset_into_extra(): void
    {
        $processor = new AnsiColorProcessor();
        $record    = $this->record(Level::Warning);

        $result = $processor($record);

        self::assertArrayHasKey('color', $result->extra);
        self::assertArrayHasKey('reset', $result->extra);
        self::assertSame("\033[0m", $result->extra['reset']);
        self::assertNotEmpty($result->extra['color']);
    }

    public function test_no_color_empties_keys(): void
    {
        $processor = new AnsiColorProcessor(noColor: true);
        $record    = $this->record(Level::Error);

        $result = $processor($record);

        self::assertSame('', $result->extra['color']);
        self::assertSame('', $result->extra['reset']);
    }

    public function test_warning_uses_yellow_bold(): void
    {
        $processor = new AnsiColorProcessor();
        $record    = $this->record(Level::Warning);

        $result = $processor($record);

        self::assertSame("\033[93m", $result->extra['color']);
    }

    public function test_preserves_existing_context_and_extra(): void
    {
        $processor = new AnsiColorProcessor();
        $record    = $this->record(Level::Info, ['user_id' => 42]);

        $result = $processor($record);

        self::assertSame(42, $result->context['user_id']);
        self::assertArrayHasKey('color', $result->extra);
    }
}