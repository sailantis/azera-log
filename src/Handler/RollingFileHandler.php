<?php

declare(strict_types=1);

namespace Azera\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * A log4j-style rolling file handler with composite (size + date),
 * size, and date rotation strategies.
 *
 * This is the one file-rotation feature Monolog does not provide out of
 * the box (Monolog's `RotatingFileHandler` is date-only), so it is kept
 * as an Azera extension built on top of Monolog's
 * {@see AbstractProcessingHandler}.
 *
 * Rotation modes (`rollingMode`):
 *  - `date`      — one file per period (default `Y-m-d`); the current
 *                  file keeps the configured filename, older files get
 *                  the date suffix.
 *  - `size`      — rolls when the file exceeds `maxFileSize` bytes;
 *                  backups are numbered `.1`, `.2`, …
 *  - `composite` — both: date-suffix backups, plus size rotation
 *                  within the current period.
 *  - `once`      — rotates once on first write, then writes to the
 *                  same file for the process lifetime (useful for
 *                  long-running CLI daemons that roll per start).
 *
 * Other options:
 *  - `staticLogFileName` (default true): keep the current file as the
 *    configured filename; archived backups carry the date/number suffix.
 *    When false, the current file itself carries the suffix.
 *  - `compressGzip`: gzip archived backups (`.gz`).
 *  - `maxSizeRollBackups`: max number of size-rotated backups to keep
 *    (`-1` = unlimited).
 *  - `maxAge`: delete backups older than N days (date/composite modes).
 *  - `skipEmpty`: don't archive empty files on rotation.
 *  - `fileMode`: `chmod` newly created files.
 *
 * The handler opens the file with `LOCK_EX` and registers a shutdown
 * function so the handle is flushed even on fatal errors.
 */
class RollingFileHandler extends AbstractProcessingHandler
{
    /** @var array<string, mixed> */
    private array $options;

    private bool $rotateSize;

    private bool $rotateDate;

    private string $fileName;

    private int $fileTime = 0;

    /** @var resource|null */
    private $fileHandle = null;

    /**
     * @param string                 $name    Base log filename.
     * @param int|string|Level       $level   Minimum level (Monolog level or PSR-3 name).
     * @param bool                   $bubble
     * @param array<string, mixed>  $options Rotation options (see class doc).
     */
    public function __construct(
        string $name,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        array $options = [],
    ) {
        $options += [
            'rollingMode'        => 'composite',
            'staticLogFileName'  => true,
            'maxFileSize'        => 0,
            'maxSizeRollBackups' => -1,
            'datePattern'        => '-Ymd',
            'maxAge'             => 0,
            'compressGzip'       => false,
            'skipEmpty'          => false,
            'fileMode'           => 0,
            'skipRotate'         => false,
            'rotateOnce'         => false,
        ];
        $options['logFileName'] = $name;
        $options['rotateOnce']  = $options['rollingMode'] === 'once';

        switch ($options['rollingMode']) {
            case 'composite':
                $this->rotateSize = $options['maxFileSize'] > 0;
                $this->rotateDate = !empty($options['datePattern']);
                break;
            case 'size':
                $this->rotateSize = $options['maxFileSize'] > 0;
                $this->rotateDate = false;
                break;
            case 'date':
            default:
                $this->rotateSize = false;
                $this->rotateDate = true;
                break;
        }

        $this->options = $options;
        parent::__construct($level, $bubble);

        // Ensure the handle is flushed even on fatal errors.
        register_shutdown_function([$this, 'close']);
    }

    /**
     * Close the file handle. Safe to call multiple times.
     */
    public function close(): void
    {
        $this->closeLogFile();
    }

    protected function write(LogRecord $record): void
    {
        $this->fileName = $this->getFilename($record->datetime, true);
        $this->openLogFile('a+');

        // Clear the stat cache so filesize()/filemtime() reflect the
        // current state on disk (prior writes in a previous write()
        // call or from another process). Per the OPcache/stat-cache
        // lessons: PHP caches file stats within a request.
        clearstatcache(true, $this->fileName);
        $this->fileTime = (int) filemtime($this->fileName);

        if (!$this->options['skipRotate']) {
            $this->rotate($record->datetime);
            if ($this->options['rotateOnce']) {
                $this->options['skipRotate'] = true;
            }
        }

        @fwrite($this->fileHandle, $record->formatted . "\n");

        if ($this->options['skipRotate']) {
            @touch($this->fileName, $this->fileTime);
        }

        $this->closeLogFile();
    }

    // --- rotation ------------------------------------------------------------

    private function rotate(\DateTimeInterface $dateTime): void
    {
        if ($this->rotateSize) {
            $this->rotateSize($dateTime);
            return;
        }
        if ($this->rotateDate) {
            $this->rotateDate($dateTime);
        }
    }

    private function rotateSize(\DateTimeInterface $dateTime): void
    {
        $fileSize = (int) filesize($this->fileName);
        if (empty($fileSize) && $this->options['skipEmpty']) {
            return;
        }
        if ($fileSize < $this->options['maxFileSize']) {
            return;
        }

        $baseLength = strlen($this->options['logFileName']);
        $count      = 0;
        $max        = abs($this->options['maxSizeRollBackups']);
        $rename     = [];

        foreach ((glob($this->options['logFileName'] . '*') ?: []) as $filename) {
            if ($filename === $this->fileName) {
                continue;
            }
            $appendix = substr($filename, $baseLength);
            if (preg_match('/^\.(\d+)(.*)/', $appendix, $match)) {
                if (empty($match[2]) || str_starts_with($match[2], '.')) {
                    $count++;
                    if ($count >= $max) {
                        @unlink($filename);
                    } else {
                        $rename[$filename] = $this->options['logFileName'] . '.' . ($count + 1) . $match[2];
                    }
                }
            }
        }

        foreach (array_reverse($rename) as $filename => $newName) {
            @rename($filename, $newName);
        }

        $fileDateTime = new \DateTime('@' . $this->fileTime);
        $fileDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $backupFile = $this->getFilename($fileDateTime, false);

        if ($this->options['compressGzip']) {
            $this->archiveGzip($backupFile);
        } elseif ($this->options['staticLogFileName']) {
            $this->archivePlain($backupFile);
        } else {
            $this->truncateLogFile();
        }
    }

    private function rotateDate(\DateTimeInterface $dateTime): void
    {
        $fileDateTime = new \DateTime('@' . $this->fileTime);
        $fileDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        if ($dateTime->format('Ymd') <= $fileDateTime->format('Ymd')) {
            return;
        }

        $backupFile = $this->getFilename($fileDateTime, false);

        if (filesize($this->fileName) > 0 || !$this->options['skipEmpty']) {
            if ($this->options['compressGzip']) {
                $this->archiveGzip($backupFile);
            } elseif ($this->options['staticLogFileName']) {
                $this->archivePlain($backupFile);
            }
        } elseif (!$this->options['staticLogFileName']) {
            $this->truncateLogFile();
        }

        if ($this->options['maxAge'] > 0) {
            $expired = $dateTime->getTimestamp() - $this->options['maxAge'] * 86400;
            foreach ((glob($this->options['logFileName'] . '*') ?: []) as $filename) {
                if ($this->fileName !== $filename && filemtime($filename) <= $expired) {
                    @unlink($filename);
                }
            }
        }
    }

    // --- file management -----------------------------------------------------

    private function getFilename(\DateTimeInterface $dateTime, bool $current = true): string
    {
        $filename = $this->options['logFileName'];
        if (!$current || !$this->options['staticLogFileName']) {
            if ($this->rotateSize) {
                $filename .= '.1';
            } elseif ($this->rotateDate) {
                $filename .= $dateTime->format($this->options['datePattern']);
            }
        }
        if (!$current && $this->options['compressGzip']) {
            $filename .= '.gz';
        }
        return $filename;
    }

    private function openLogFile(string $mode): void
    {
        $created = !file_exists($this->fileName);
        $this->fileHandle = @fopen($this->fileName, $mode);
        if ($this->fileHandle === false) {
            $this->fileHandle = null;
            return;
        }
        @flock($this->fileHandle, LOCK_EX);
        if ($created && !empty($this->options['fileMode'])) {
            @chmod($this->fileName, $this->options['fileMode']);
        }
    }

    private function closeLogFile(): void
    {
        if ($this->fileHandle !== null) {
            @flock($this->fileHandle, LOCK_UN);
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    private function truncateLogFile(): void
    {
        $this->closeLogFile();
        $this->openLogFile('w+');
    }

    /**
     * Compress the current file content to a gz archive, then truncate.
     */
    private function archiveGzip(string $backupFile): void
    {
        if ($this->fileHandle === null) {
            return;
        }
        fseek($this->fileHandle, 0, SEEK_SET);
        $fileWrite = @gzopen($backupFile, 'ab9');
        if ($fileWrite === false) {
            return;
        }
        while (!feof($this->fileHandle)) {
            gzwrite($fileWrite, fread($this->fileHandle, 1048576));
        }
        gzclose($fileWrite);
        @touch($backupFile, $this->fileTime);
        if (!empty($this->options['fileMode'])) {
            @chmod($backupFile, $this->options['fileMode']);
        }
        $this->truncateLogFile();
    }

    /**
     * Copy the current file content to a plain archive, then truncate.
     */
    private function archivePlain(string $backupFile): void
    {
        if ($this->fileHandle === null) {
            return;
        }
        fseek($this->fileHandle, 0, SEEK_SET);
        $fileWrite = @fopen($backupFile, 'a');
        if ($fileWrite === false) {
            return;
        }
        while (!feof($this->fileHandle)) {
            fwrite($fileWrite, fread($this->fileHandle, 1048576));
        }
        fclose($fileWrite);
        @touch($backupFile, $this->fileTime);
        if (!empty($this->options['fileMode'])) {
            @chmod($backupFile, $this->options['fileMode']);
        }
        $this->truncateLogFile();
    }
}