<?php

declare(strict_types=1);

namespace Cli\Attributes;

/**
 * Declares a named option that a CLI command accepts.
 *
 * Apply this attribute to a command class once per option. It is repeatable, so
 * a command may carry multiple Option attributes. The dispatcher reads these via
 * reflection to parse argv and render per-command help output.
 *
 * Options whose default value is a boolean are treated as flags: they accept no
 * argument and are set to true when present on the command line. All other options
 * expect a string value supplied as --name=value or --name value.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Option
{
    /**
     * @param string           $name        The long option name without leading dashes (e.g. "force").
     * @param string           $description A single-line description shown in help output.
     * @param string           $short       An optional single-character short alias without the dash (e.g. "f").
     * @param string|bool|null $default     The default value. Pass false to declare a boolean flag.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $short = '',
        public readonly string|bool|null $default = null,
    ) {
    }
}
