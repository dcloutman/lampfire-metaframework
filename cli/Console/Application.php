<?php

declare(strict_types=1);

namespace Cli\Console;

use Cli\Attributes\Command as CommandAttribute;
use Cli\Attributes\Option as OptionAttribute;
use ReflectionClass;
use RuntimeException;

/**
 * Dispatches CLI invocations to the correct command class.
 *
 * Commands are registered by class name. The dispatcher reads each class's
 * #[Command] and #[Option] attributes via reflection to route argv, generate
 * help output, and parse options before calling execute().
 *
 * Exit codes returned by execute() are propagated to the caller. The run()
 * method does not call exit() itself; that is the responsibility of the entry
 * point (cli.php).
 */
final class Application
{
    /** @var array<string, class-string<AbstractCommand>> */
    private array $commands = [];

    /**
     * @param string $name The application name shown in help output.
     */
    public function __construct(private string $name)
    {
    }

    /**
     * Registers a command class with the dispatcher.
     *
     * The class must be decorated with #[Command]. The command name declared
     * in that attribute becomes the subcommand name on the command line.
     *
     * @param class-string<AbstractCommand> $commandClass
     * @throws RuntimeException When the class does not carry a #[Command] attribute.
     */
    public function register(string $commandClass): void
    {
        $attr                        = $this->readCommandAttribute($commandClass);
        $this->commands[$attr->name] = $commandClass;
    }

    /**
     * Parses argv and dispatches to the matching command, or prints help.
     *
     * Returns the exit code produced by the command. Returns 0 when help is
     * printed and 1 when an unknown command is requested.
     *
     * @param array<string> $argv The full argv array including the script name.
     */
    public function run(array $argv): int
    {
        $output      = new Output();
        $commandName = $argv[1] ?? null;

        if ($commandName === null || $commandName === '--help' || $commandName === '-h') {
            $this->printUsage($output);
            return 0;
        }

        if (array_key_exists($commandName, $this->commands) === false) {
            $output->error(sprintf('Unknown command "%s".', $commandName));
            $output->writeln();
            $this->printUsage($output);
            return 1;
        }

        $commandClass = $this->commands[$commandName];

        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            $this->printCommandHelp($commandClass, $output);
            return 0;
        }

        $optionDefs = $this->readOptionAttributes($commandClass);
        $input      = new Input($argv, $optionDefs);
        $command    = new $commandClass();

        return $command->execute($input, $output);
    }

    /**
     * Prints the top-level usage listing to stdout.
     */
    private function printUsage(Output $output): void
    {
        $output->writeln($this->name);
        $output->writeln();
        $output->writeln('Usage: php cli.php <command> [options]');
        $output->writeln();
        $output->writeln('Available commands:');

        $maxLen = 0;
        foreach (array_keys($this->commands) as $name) {
            $maxLen = max($maxLen, strlen($name));
        }

        foreach ($this->commands as $name => $class) {
            $attr = $this->readCommandAttribute($class);
            $output->writeln(sprintf('  %-' . $maxLen . 's  %s', $name, $attr->description));
        }

        $output->writeln();
        $output->writeln("Run 'php cli.php <command> --help' for command-specific help.");
    }

    /**
     * Prints the help text for a single command to stdout.
     *
     * @param class-string<AbstractCommand> $commandClass
     */
    private function printCommandHelp(string $commandClass, Output $output): void
    {
        $attr       = $this->readCommandAttribute($commandClass);
        $optionDefs = $this->readOptionAttributes($commandClass);

        $output->writeln($attr->name);
        $output->writeln();
        $output->writeln($attr->description);

        if ($attr->help !== '') {
            $output->writeln();
            $output->writeln($attr->help);
        }

        $output->writeln();
        $output->writeln('Options:');
        $output->writeln(sprintf('  %-22s %s', '--help, -h', 'Show this help message.'));

        foreach ($optionDefs as $opt) {
            $label = '--' . $opt->name;
            if ($opt->short !== '') {
                $label .= ', -' . $opt->short;
            }
            $output->writeln(sprintf('  %-22s %s', $label, $opt->description));
        }
    }

    /**
     * Reads the #[Command] attribute from a class via reflection.
     *
     * @param class-string<AbstractCommand> $commandClass
     * @throws RuntimeException When the attribute is absent.
     */
    private function readCommandAttribute(string $commandClass): CommandAttribute
    {
        $reflection = new ReflectionClass($commandClass);
        $attributes = $reflection->getAttributes(CommandAttribute::class);

        if (count($attributes) === 0) {
            throw new RuntimeException(sprintf(
                'Command class %s is missing the #[Command] attribute.',
                $commandClass
            ));
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Reads all #[Option] attributes from a class via reflection.
     *
     * @param class-string<AbstractCommand> $commandClass
     * @return list<OptionAttribute>
     */
    private function readOptionAttributes(string $commandClass): array
    {
        $reflection = new ReflectionClass($commandClass);
        $attributes = $reflection->getAttributes(OptionAttribute::class);

        return array_map(
            static fn(\ReflectionAttribute $attr) => $attr->newInstance(),
            $attributes
        );
    }
}
