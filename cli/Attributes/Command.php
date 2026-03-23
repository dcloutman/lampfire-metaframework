<?php

declare(strict_types=1);

namespace Cli\Attributes;

/**
 * Marks a class as a CLI command and declares its name, description, and optional help text.
 *
 * Apply this attribute to every class that extends AbstractCommand. The dispatcher
 * reads these values via reflection to route argv and render help output.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Command
{
    /**
     * @param string $name        The subcommand name as typed on the command line (e.g. "init").
     * @param string $description A single-line description shown in the command list.
     * @param string $help        Extended help text shown when the operator passes --help.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $help = '',
    ) {
    }
}
