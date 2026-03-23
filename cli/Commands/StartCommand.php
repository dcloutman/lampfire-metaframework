<?php

declare(strict_types=1);

namespace Cli\Commands;

use Cli\Attributes\Command;
use Cli\Console\AbstractCommand;
use Cli\Console\Input;
use Cli\Console\Output;
use Dotenv\Dotenv;
use RuntimeException;

/**
 * Starts the Docker development services for the project.
 *
 * This command starts the Docker stack only. It does not generate passwords,
 * create database users, or run migrations.
 */
#[Command(
    name: 'start',
    description: 'Start Docker services for this project.',
    help: 'Starts the Lampfire Docker services normally. Use after the project has been initialized with `php cli.php init`. This command does not modify the database or run migrations.'
    . 'Run php cli.php init after start to create or repair the database and users when needed.',
)]
final class StartCommand extends AbstractCommand
{
    /**
     * Executes the start workflow.
     */
    public function execute(Input $input, Output $output): int
    {
        try {
            $projectRoot = dirname(__DIR__, 2);

            if ($this->hasEnvironmentFile($projectRoot) === false) {
                $output->error('No .env file was found. Run php cli.php init first.');
                return 1;
            }

            $this->loadEnvironment($projectRoot);

            $output->info('Starting Docker services...');
            $this->startContainers($projectRoot, $output);

            $output->info('Waiting for database to become healthy...');
            $this->waitForDatabase($projectRoot);

            $output->writeln();
            $output->info('Docker services are running.');
            return 0;
        } catch (RuntimeException $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    /**
     * Returns true when the project .env file exists.
     */
    private function hasEnvironmentFile(string $projectRoot): bool
    {
        return file_exists($projectRoot . '/.env');
    }

    /**
     * Loads environment values from .env.
     */
    private function loadEnvironment(string $projectRoot): void
    {
        $dotenv = Dotenv::createUnsafeImmutable($projectRoot);
        $dotenv->load();
    }

    /**
     * Starts the Docker Compose services for the existing project.
     */
    private function startContainers(string $projectRoot, Output $output): void
    {
        $compose = $this->resolveComposeCommand($projectRoot);
        $resultCode = $this->runShellCommand($compose . ' up -d --remove-orphans', $projectRoot, $output);

        if ($resultCode !== 0) {
            throw new RuntimeException(sprintf('%s up -d failed.', $compose));
        }
    }

    /**
     * Returns the available Docker Compose command.
     *
     * @throws RuntimeException When no compose command can be found.
     */
    private function resolveComposeCommand(string $projectRoot): string
    {
        if ($this->runShellCommand('docker compose version >/dev/null 2>&1', $projectRoot, null) === 0) {
            return 'docker compose';
        }

        throw new RuntimeException(
            '"docker compose" is not available. Install Docker Compose v2 and retry.'
        );
    }

    /**
     * Runs a shell command in the project root and optionally streams output.
     *
     * @param Output|null $output The console output, or null to suppress command output.
     */
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

    /**
     * Polls the database container until it reports a healthy status.
     */
    private function waitForDatabase(string $projectRoot): void
    {
        for ($i = 0; $i < 30; $i++) {
            $containerId = $this->getRunningDatabaseContainerId($projectRoot);

            if ($containerId === '') {
                sleep(2);
                continue;
            }

            $status = trim($this->captureShellOutput(
                sprintf("docker inspect --format='{{.State.Health.Status}}' %s 2>/dev/null", escapeshellarg($containerId)),
                $projectRoot,
            ));

            if ($status === 'healthy') {
                return;
            }

            sleep(2);
        }

        throw new RuntimeException('Database did not become healthy within 60 seconds.');
    }

    /**
     * Returns the ID of the running database container, or an empty string when absent.
     */
    private function getRunningDatabaseContainerId(string $projectRoot): string
    {
        $output = trim($this->captureShellOutput(
            sprintf('docker ps -q --filter %s', escapeshellarg('name=lampfire-db')),
            $projectRoot,
        ));

        if ($output === '') {
            return '';
        }

        $lines = preg_split('/\s+/', $output);

        return is_array($lines) && isset($lines[0]) ? (string) $lines[0] : '';
    }

    /**
     * Captures stdout from a shell command.
     */
    private function captureShellOutput(string $command, string $cwd): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (is_resource($process) !== true) {
            throw new RuntimeException(sprintf('Failed to start process: %s', $command));
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return is_string($stdout) ? $stdout : '';
    }
}