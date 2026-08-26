# azera-log

Logging companion for the [Azera framework](../azera-framework).

The framework ships only a `NullLogger` (PSR-3). This package builds on
[Monolog](https://github.com/Seldaek/monolog) (the de-facto standard PSR-3
logger) and adds only the pieces Monolog does not provide:

- `Handler\RollingFileHandler` — log4j-style composite (size + date), size,
  and date rotation. Monolog's `RotatingFileHandler` is date-only, so this
  covers the missing size/composite rotation.
- `Formatter\ConsoleFormatter` + `Processor\AnsiColorProcessor` — selective
  per-level ANSI coloring for CLI output (only the level segment is colored),
  without a Symfony Console dependency.
- Listeners (PSR-14): `DbQueryLogger` (all DB events), `SlowQueryHandler`
  (threshold-filtered `QueryExecuted`), `TransactionAuditListener`,
  `AuthLoggerListener`, `QueueWorkerListener`.

Everything else — the `Logger`, standard handlers, formatters, and processors —
comes from Monolog directly. Use `Monolog\Logger` and push the Azera extensions
onto it as you would any Monolog handler/processor.

## Example

```php
use Azera\Log\Formatter\ConsoleFormatter;
use Azera\Log\Handler\RollingFileHandler;
use Azera\Log\Processor\AnsiColorProcessor;
use Monolog\Logger;

$logger = new Logger('app');

$handler = new RollingFileHandler(
    '/var/log/app.log',
    options: ['rollingMode' => 'composite', 'maxFileSize' => 10485760],
);
$handler->setFormatter(new ConsoleFormatter());
$logger->pushHandler($handler);

$logger->pushProcessor(new AnsiColorProcessor());
```

## Installation

```json
{
    "repositories": [{ "type": "path", "url": "../azera-log" }],
    "require": { "sailantis/azera-log": "dev-main" }
}
```

## License

MIT.