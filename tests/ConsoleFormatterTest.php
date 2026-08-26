<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Log\Formatter\ConsoleFormatter;
use Azera\Log\Processor\AnsiColorProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class ConsoleFormatterTest extends TestCase
{
    private function record(Level $level = Level::Warning, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: $level,
            message: 'disk nearly full',
            context: $context,
        );
    }

    public function test_explicit_processor_takes_precedence_over_default(): void
    {
        $formatter = new ConsoleFormatter();
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record(Level::Warning, ['pct' => 95]));

        $output = $formatter->format($record);

        // The color code should appear right before the level and the
        // reset right after — not wrapping the whole line.
        self::assertStringContainsString("\033[93mWARNING\033[0m", $output);
        self::assertStringContainsString('test.', $output);
        self::assertStringContainsString('disk nearly full', $output);
        self::assertStringContainsString('"pct":95', $output);
        // The line should NOT start with a color code (whole-line coloring).
        self::assertStringStartsNotWith("\033[", $output);
    }

    public function test_colors_by_default_without_explicit_processor(): void
    {
        $formatter = new ConsoleFormatter();
        $record    = $this->record(Level::Info);

        $output = $formatter->format($record);

        // The default color processor kicks in, so the level is colored.
        self::assertStringContainsString("\033[97mINFO\033[0m", $output);
        self::assertStringContainsString('test.', $output);
    }

    public function test_error_uses_red_foreground(): void
    {
        $formatter = new ConsoleFormatter();
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record(Level::Error, []));

        $output = $formatter->format($record);

        self::assertStringContainsString("\033[91m", $output);
    }

    public function test_critical_uses_white_on_red(): void
    {
        $formatter = new ConsoleFormatter();
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record(Level::Critical, []));

        $output = $formatter->format($record);

        self::assertStringContainsString("\033[97;41m", $output);
    }

    public function test_debug_uses_normal_weight(): void
    {
        $formatter = new ConsoleFormatter();
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record(Level::Debug, []));

        $output = $formatter->format($record);

        self::assertStringContainsString("\033[37m", $output);
        self::assertStringNotContainsString("\033[97", $output);
    }

    public function test_custom_format_can_color_message_instead(): void
    {
        $formatter = new ConsoleFormatter(
            format: '[%datetime%] %channel%.%level_name%: %extra.color%%message%%extra.reset% %context%',
        );
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record());

        $output = $formatter->format($record);

        // Now the color wraps the message, not the level.
        self::assertStringContainsString("\033[93mdisk nearly full\033[0m", $output);
        self::assertStringContainsString('test.WARNING', $output);
    }

    public function test_extra_keys_not_in_context_json(): void
    {
        $formatter = new ConsoleFormatter();
        $processor = new AnsiColorProcessor();
        $record    = $processor($this->record(Level::Info, ['pct' => 95]));

        $output = $formatter->format($record);

        // color and reset are in extra, not in the context JSON dump.
        self::assertStringNotContainsString('"color"', $output);
        self::assertStringNotContainsString('"reset"', $output);
        self::assertStringContainsString('"pct":95', $output);
    }
}