<?php

declare(strict_types=1);

namespace Cli\Console;

/**
 * Writes formatted output to stdout and stderr.
 *
 * When stdout is connected to a terminal, ANSI escape codes are applied to
 * coloured output methods. When stdout is redirected to a file or a pipe,
 * colour codes are suppressed and plain text is written.
 */
final class Output
{
    private bool $colors;

    public function __construct()
    {
        $this->colors = stream_isatty(STDOUT);
    }

    /**
     * Writes a plain line to stdout followed by a newline.
     *
     * Pass an empty string to write a blank line.
     */
    public function writeln(string $line = ''): void
    {
        echo $line . PHP_EOL;
    }

    /**
     * Writes a line to stdout in green, indicating a successful or informational status.
     */
    public function info(string $message): void
    {
        if ($this->colors) {
            echo "\033[32m" . $message . "\033[0m" . PHP_EOL;
        } else {
            echo $message . PHP_EOL;
        }
    }

    /**
     * Writes a key and value pair with distinct colours for readability.
     */
    public function keyValue(string $label, string $value): void
    {
        if ($this->colors) {
            echo "\033[36m" . $label . "\033[0m: " . "\033[32m" . $value . "\033[0m" . PHP_EOL;
        } else {
            echo $label . ': ' . $value . PHP_EOL;
        }
    }

    /**
     * Writes a line to stdout in yellow, indicating a caution or non-critical notice.
     */
    public function warning(string $message): void
    {
        if ($this->colors) {
            echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
        } else {
            echo $message . PHP_EOL;
        }
    }

    /**
     * Writes a line to stderr in red, indicating an error or failure.
     */
    public function error(string $message): void
    {
        if ($this->colors) {
            fwrite(STDERR, "\033[31m" . $message . "\033[0m" . PHP_EOL);
        } else {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }
}
