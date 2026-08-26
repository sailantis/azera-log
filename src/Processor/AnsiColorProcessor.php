<?php

declare(strict_types=1);

namespace Azera\Log\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\Level;

/**
 * Injects per-level ANSI color codes into the record's `extra` bag.
 *
 * This is the color logic ported from a proven Monolog-based
 * `TokenProcessor`, reimagined as a dedicated processor so that the
 * formatter decides *where* to place the color codes via a format
 * template — instead of coloring the entire line, which looks
 * unprofessional.
 *
 * The processor adds two keys (named by {@see self::EXTRA_COLOR} and
 * {@see self::EXTRA_RESET}) consumed by
 * {@see \Azera\Log\Formatter\ConsoleFormatter} (or any formatter that
 * uses the `%extra.color%` / `%extra.reset%` placeholders):
 *
 *  - `color`: the ANSI escape sequence for the record's level.
 *  - `reset`: `\\033[0m` (reset).
 *
 * These keys are stripped from the JSON context dump by
 * {@see \Monolog\Formatter\LineFormatter} (they are consumed as
 * individual `%extra.*%` placeholders, so they are removed from the bag
 * before the whole-bag JSON dump), so they never appear as visible
 * noise in structured log output.
 *
 * When `$noColor` is true (e.g. when output is piped, not a TTY), the
 * processor still injects the keys but sets them to empty strings, so
 * the format template renders the same line without any escape codes.
 *
 * All color codes are exposed as `public const` so formatters, tests,
 * or downstream code can reference a specific level's color without
 * instantiating the processor.
 */
class AnsiColorProcessor implements ProcessorInterface
{
    // -----------------------------------------------------------------
    // ANSI color numbers (building blocks for custom codes).
    // -----------------------------------------------------------------
    public const ANSI_BLACK = 0;
    public const ANSI_RED = 1;
    public const ANSI_GREEN = 2;
    public const ANSI_YELLOW = 3;
    public const ANSI_BLUE = 4;
    public const ANSI_PURPLE = 5;
    public const ANSI_CYAN = 6;
    public const ANSI_WHITE = 7;

    // -----------------------------------------------------------------
    // Reset sequence.
    // -----------------------------------------------------------------
    public const RESET = "\033[0m";

    // -----------------------------------------------------------------
    // Per-level color codes (pre-computed from the helpers below).
    //
    // Bold = bright/high-intensity foreground (\033[9Xm), optionally
    //        with a background (\033[9X;4Ym).
    // Normal = standard foreground (\033[3Xm).
    // -----------------------------------------------------------------
    public const COLOR_EMERGENCY = "\033[97;41m"; // bold white on red
    public const COLOR_ALERT = "\033[97;41m";     // bold white on red
    public const COLOR_CRITICAL = "\033[97;41m";  // bold white on red
    public const COLOR_ERROR = "\033[91m";        // bold red
    public const COLOR_WARNING = "\033[93m";      // bold yellow
    public const COLOR_NOTICE = "\033[92m";       // bold green
    public const COLOR_INFO = "\033[97m";         // bold white
    public const COLOR_DEBUG = "\033[37m";        // normal white (gray)

    public const EXTRA_COLOR = 'color';
    public const EXTRA_RESET = 'reset';

    /** @var array<int, string> */
    private array $levelColors;

    public function __construct(
        private bool $noColor = false,
    ) {
        $this->levelColors = [
            Level::Emergency->value => self::COLOR_EMERGENCY,
            Level::Alert->value     => self::COLOR_ALERT,
            Level::Critical->value  => self::COLOR_CRITICAL,
            Level::Error->value     => self::COLOR_ERROR,
            Level::Warning->value   => self::COLOR_WARNING,
            Level::Notice->value    => self::COLOR_NOTICE,
            Level::Info->value      => self::COLOR_INFO,
            Level::Debug->value     => self::COLOR_DEBUG,
        ];
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $color = $this->noColor ? '' : ($this->levelColors[$record->level->value] ?? '');
        $reset = $this->noColor ? '' : self::RESET;

        $record->extra[self::EXTRA_COLOR] = $color;
        $record->extra[self::EXTRA_RESET] = $reset;

        return $record;
    }

    public function setLevelColor(Level $level, string $color): static
    {
        $this->levelColors[$level->value] = $color;
        return $this;
    }

    public function getLevelColor(Level $level): string
    {
        return $this->levelColors[$level->value] ?? '';
    }

    /** @param array<int, string> $levelColors */
    public function setLevelColors(array $levelColors): static
    {
        $this->levelColors = $levelColors;
        return $this;
    }

    /** @return array<int, string> */
    public function getLevelColors(): array
    {
        return $this->levelColors;
    }

    /**
     * Build a normal-weight ANSI color code (foreground, optional background).
     */
    public static function normal(int $fore, int $back = -1): string
    {
        $code = "\033[3" . $fore;
        if ($back >= self::ANSI_BLACK && $back <= self::ANSI_WHITE) {
            $code .= ';4' . $back;
        }
        return $code . 'm';
    }

    /**
     * Build a bold ANSI color code (foreground, optional background).
     */
    public static function bold(int $fore, int $back = -1): string
    {
        $code = "\033[9" . $fore;
        if ($back >= self::ANSI_BLACK && $back <= self::ANSI_WHITE) {
            $code .= ';4' . $back;
        }
        return $code . 'm';
    }
}