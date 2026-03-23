<?php

declare(strict_types=1);

namespace Lampfire\Utilities;

/**
 * Utility methods for interacting with the terminal.
 */
class Terminal
{
    /**
     * Reads a password from the terminal without echoing the input.
     *
     * Echo suppression is achieved via the readline callback mechanism on
     * Unix-like systems. On Windows, input is captured correctly but echo
     * suppression is not available without OS-level calls.
     *
     * @param string $prompt The prompt displayed to the operator.
     * @return string The string entered by the operator, or an empty string if stdin cannot be opened.
     */
    public static function readPassword(string $prompt = 'Password: '): string
    {
        echo $prompt;

        $canSuppress = function_exists('readline_callback_handler_install');

        if ($canSuppress) {
            readline_callback_handler_install('', static fn() => null);
        }

        $password = '';

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            if ($canSuppress) {
                readline_callback_handler_remove();
            }
            throw new \RuntimeException('Failed to open stdin for reading.');
        }

        while (true) {
            $char = fgetc($stdin);

            if ($char === "\n" || $char === "\r" || $char === false) {
                break;
            }

            if (ord($char) === 127 || ord($char) === 8) {
                $password = substr($password, 0, -1);
                continue;
            }

            $password .= $char;
        }

        if ($canSuppress) {
            readline_callback_handler_remove();
        }

        fclose($stdin);
        echo PHP_EOL;

        return $password;
    }
}
