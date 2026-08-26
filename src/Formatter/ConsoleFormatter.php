<?php

declare(strict_types=1);

namespace Azera\Log\Formatter;

use Azera\Log\Processor\AnsiColorProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * Formats a record for terminal output with selective ANSI coloring.
 *
 * Extends {@see LineFormatter} and uses a format template that wraps
 * only the level segment with `%extra.color%` / `%extra.reset%` — so
 * the timestamp, channel, message, and context stay uncolored, which is
 * more readable than coloring the entire line.
 *
 * The color codes themselves come from a {@see \Azera\Log\Processor\AnsiColorProcessor}
 * injected into the record's `extra` bag (`color` / `reset` keys). This
 * keeps the coloring concern in the processor and the placement in
 * the formatter, so you can adjust *where* colors appear by changing
 * the format string without touching the color logic.
 *
 * If no `AnsiColorProcessor` was registered on the handler, the
 * formatter falls back to an internal default processor so the output
 * is still colored. Pass `new AnsiColorProcessor(noColor: true)` as
 * `$defaultColorProcessor` to opt out of coloring entirely (e.g. when
 * output is piped, not a TTY).
 *
 * The default format:
 *   [%datetime%] %channel%.%extra.color%%level_name%%extra.reset%: %message% %context%
 *
 * produces (for a warning):
 *   [22:30:00] app.\033[93mWARNING\033[0m: disk nearly full {"pct":95}
 */
class ConsoleFormatter extends LineFormatter
{
    /**
     * Default console format — only the level is colored.
     */
    public const SIMPLE_FORMAT = "[%datetime%] %channel%.%extra.color%%level_name%%extra.reset%: %message% %context% %extra%\n";

    private AnsiColorProcessor $defaultColorProcessor;

    public function __construct(
        string $dateFormat = 'H:i:s',
        bool $includeContext = true,
        string $format = self::SIMPLE_FORMAT,
    ) {
        parent::__construct($format, $dateFormat, false, !$includeContext);
    }

    public function format(LogRecord $record): string
    {
        // If no color processor ran (no `color`/`reset` in extra), apply
        // the default so the output is still colored.
        if (!isset($record->extra[AnsiColorProcessor::EXTRA_COLOR])) {
            $this->defaultColorProcessor ??= new AnsiColorProcessor();
            $record = ($this->defaultColorProcessor)($record);
        }

        return parent::format($record);
    }
}