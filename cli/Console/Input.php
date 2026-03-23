<?php

declare(strict_types=1);

namespace Cli\Console;

use Cli\Attributes\Option;

/**
 * Parses a raw argv array into named options and flags.
 *
 * Parsing begins at argv[2], skipping the script name at argv[0] and the
 * subcommand name at argv[1]. Option definitions supplied at construction
 * time establish defaults for every declared option and allow short aliases
 * to be resolved to their long-form names.
 *
 * Supported argv token forms:
 *   --name=value   Long option with inline value.
 *   --name value   Long option with separate value (only when option is not a flag).
 *   --flag         Boolean flag; sets the option to true.
 *   -n value       Short alias with separate value.
 *   -n             Short alias as a boolean flag.
 */
final class Input
{
    /** @var array<string, string|bool> */
    private array $options = [];

    /**
     * @param array<string>  $argv        The full argv array including the script name.
     * @param list<Option>   $definitions The option attribute instances declared on the command.
     */
    public function __construct(array $argv, array $definitions)
    {
        /** @var array<string, string> $shortToLong */
        $shortToLong = [];

        /** @var array<string, bool> $isFlagOption */
        $isFlagOption = [];

        foreach ($definitions as $definition) {
            if ($definition->short !== '') {
                $shortToLong[$definition->short] = $definition->name;
            }

            if (is_bool($definition->default)) {
                $isFlagOption[$definition->name] = true;
            }

            if ($definition->default !== null) {
                $this->options[$definition->name] = $definition->default;
            }
        }

        $tokens = array_slice($argv, 2);
        $count  = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (str_starts_with($token, '--')) {
                $name = substr($token, 2);

                if (str_contains($name, '=')) {
                    [$name, $value] = explode('=', $name, 2);
                    $this->options[$name] = $value;
                } elseif (isset($isFlagOption[$name])) {
                    $this->options[$name] = true;
                } elseif (isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                    $this->options[$name] = $tokens[++$i];
                } else {
                    $this->options[$name] = true;
                }
            } elseif (str_starts_with($token, '-') && strlen($token) === 2) {
                $short    = substr($token, 1);
                $longName = $shortToLong[$short] ?? $short;

                if (isset($isFlagOption[$longName])) {
                    $this->options[$longName] = true;
                } elseif (isset($tokens[$i + 1]) && !str_starts_with($tokens[$i + 1], '-')) {
                    $this->options[$longName] = $tokens[++$i];
                } else {
                    $this->options[$longName] = true;
                }
            }
        }
    }

    /**
     * Returns the value of a named option, or null when the option was not provided
     * and carries no default.
     */
    public function getOption(string $name): string|bool|null
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Returns true when the named flag option is present and set to true.
     */
    public function hasFlag(string $name): bool
    {
        return ($this->options[$name] ?? false) === true;
    }
}
