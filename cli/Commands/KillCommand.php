<?php

declare(strict_types=1);

namespace Cli\Commands;

use Cli\Attributes\Command;
use Cli\Console\AbstractCommand;
use Cli\Console\Input;
use Cli\Console\Output;
use Dotenv\Dotenv;
use RuntimeException;

#[Command(
    name: 'kill',
    description: 'Stop the Docker services for the current project.',
    help: 'Stops and removes the Docker containers for the current project without deleting database volumes.',
)]
final class KillCommand extends AbstractCommand
{
    public function execute(Input $input, Output $output): int
    {
        try {
            $projectRoot = dirname(__DIR__, 2);

            $this->loadEnvironment($projectRoot);
            $compose = $this->resolveComposeCommand($projectRoot);

            $output->info('Stopping Docker services...');
            $resultCode = $this->runShellCommand($compose . ' down --remove-orphans', $projectRoot, $output);

            if ($resultCode !== 0) {
                throw new RuntimeException(sprintf('%s down failed.', $compose));
            }

            $output->writeln();
            $output->info('Docker services have been stopped.');
            return 0;
        } catch (RuntimeException $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function loadEnvironment(string $projectRoot): void
    {
        $environmentFile = $projectRoot . '/.env';

        if (file_exists($environmentFile) !== true) {
            return;
        }

        $dotenv = Dotenv::createUnsafeImmutable($projectRoot);
        $dotenv->load();
    }

    private function resolveComposeCommand(string $projectRoot): string
    {
        if ($this->runShellCommand('docker compose version >/dev/null 2>&1', $projectRoot, null) === 0) {
            return 'docker compose';
        }

        throw new RuntimeException(
            '"docker compose" is not available. Install Docker Compose v2 and retry.'
        );
    }

    private function runShellCommand(string $command, string $cwd, ?Output $output): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (is_resource($process) !== true) {
            throw new RuntimeException(sprintf('Failed to start process: %s', $command));
        }

        fclose($pipes[0]);

        while (feof($pipes[1]) !== true) {
            $line = fgets($pipes[1]);

            if ($output !== null && is_string($line) && trim($line) !== '') {
                $output->writeln(trim($line));
            }
        }

        fclose($pipes[1]);

        return proc_close($process);
    }
}