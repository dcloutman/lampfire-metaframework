<?php

declare(strict_types=1);

namespace Cli;

use Cli\Commands\CreateUserCommand;
use Cli\Commands\InitCommand;
use Cli\Commands\KillCommand;
use Cli\Commands\ProjectResetCommand;
use Cli\Commands\RebuildImagesCommand;
use Cli\Commands\StartCommand;
use Cli\Console\Application;

/**
 * Creates the configured CLI application and registers all subcommands.
 */
final class ApplicationFactory
{
    /**
     * Creates the application and registers all subcommands.
     */
    public static function create(): Application
    {
        $application = new Application('Lampfire CLI');
        $application->register(InitCommand::class);
        $application->register(CreateUserCommand::class);
        $application->register(StartCommand::class);
        $application->register(KillCommand::class);
        $application->register(RebuildImagesCommand::class);
        $application->register(ProjectResetCommand::class);

        return $application;
    }
}
