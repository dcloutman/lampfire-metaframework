<?php

declare(strict_types=1);

namespace Cli\Console;

/**
 * Base class for all CLI commands.
 *
 * Every concrete command must extend this class, declare the #[Command] attribute,
 * and implement the execute() method. Optional #[Option] attributes on the class
 * declare the named options and flags the command accepts.
 *
 * The confirm() helper provides a consistent yes/no prompt for destructive operations.
 */
abstract class AbstractCommand
{
    /**
     * Executes the command.
     *
     * Return 0 on success or a positive integer on failure. The return value is
     * passed directly to exit() by the dispatcher.
     *
     * @param Input  $input  The parsed argv options for this invocation.
     * @param Output $output The output writer for this invocation.
     * @return int The process exit code.
     */
    abstract public function execute(Input $input, Output $output): int;

    /**
     * Prompts the operator with a yes/no question and returns their answer.
     *
     * The $default is used when the operator presses Enter without typing a value.
     *
     * @param string $question The question to display.
     * @param bool   $default  The default answer when no input is given.
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo $question . ' ' . $hint . ': ';
        $answer = strtolower(trim((string) fgets(STDIN)));

        if ($answer === '') {
            return $default;
        }

        return $answer === 'y' || $answer === 'yes';
    }
}
