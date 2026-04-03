<?php

declare(strict_types=1);

namespace Cli\Commands;

use Cli\Attributes\Command;
use Cli\Console\AbstractCommand;
use Cli\Console\Input;
use Cli\Console\Output;
use Dotenv\Dotenv;
use Lampfire\Utilities\Terminal;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

/**
 * Initializes the application database and runs SQL migrations.
 *
 * Loads configuration from .env if the file exists, then prompts the operator for every
 * configuration field, defaulting to the value already present in the environment. The operator
 * may keep, change, generate, or skip each value. When the Docker services are already running,
 * init connects as root to create the database and database users, renders the migration
 * templates, and executes the generated SQL files. This command never starts or stops Docker.
 */
#[Command(
    name: 'init',
    description: 'Collect configuration, then bootstrap the running database and run migrations.',
    help: 'Prompts for configuration values and saves them to .env. When the Docker services are '
        . 'already running, init creates the database and database users, renders migration files, '
        . 'and executes them. Run php cli.php start before init when the containers are not already up.',
)]
final class InitCommand extends AbstractCommand
{
    /**
     * Executes the init workflow.
     */
    public function execute(Input $input, Output $output): int
    {
        try {
            $projectRoot = dirname(__DIR__, 2);

            $this->loadEnvironment();

            if ($this->isProjectInitAllowed($projectRoot) === false) {
                $output->warning('Project initialization is disabled. Set ALLOW_PROJECT_INIT=true in .env to continue.');
                return 0;
            }

            $this->collectConfiguration($output);
            $this->loadEnvironment();

            if ($this->hasDatabaseConfiguration() === false) {
                $output->warning('Database configuration is incomplete. Aborting.');
                return 0;
            }

            $output->writeln();
            $rootPassword = $this->generatePassword(24, 48);
            $output->warning('Generated MariaDB root password for this initialization run:');
            $output->writeln($rootPassword);
            $output->warning('Store this password securely. It is only shown once and is never saved to disk.');

            if ($this->isDatabaseContainerRunning($projectRoot) === false) {
                $output->writeln();
                $output->info('Docker services are not running. Starting services...');
                $this->startContainers($projectRoot, $rootPassword, $output);
            }

            $output->writeln();
            $output->info('Waiting for database to become healthy...');
            $this->waitForDatabase($projectRoot);

            $output->writeln();
            $output->info('Configuring root account password...');
            $this->configureRootAccountPassword($rootPassword);

            $dbaPassword = $this->collectDbaPassword($output);

            $output->writeln();
            $output->info('Bootstrapping database and database users...');
            $this->bootstrapDatabaseAccounts($rootPassword, $dbaPassword, $output);

            if ($this->canConnectAsDba($dbaPassword) === false) {
                throw new RuntimeException('DBA authentication failed after bootstrap. Verify the root password and rerun init.');
            }

            $output->writeln();
            $output->info('Generating migration files from templates...');
            $this->generateMigrations($output);

            $this->runMigration('00001-initial-schema.sql', $dbaPassword, $output);

            if ($this->hasAdminConfiguration() === false) {
                $output->warning('Admin configuration is incomplete. Skipping admin seed migration.');
            } else {
                $this->runMigration('00002-create-initial-admin_permissions.sql', $dbaPassword, $output);
            }

            $output->writeln();
            $output->info('Refreshing web container environment from .env...');
            $this->reloadWebContainer($projectRoot, $output);

            $this->disableProjectInitFailsafe($output);

            $output->info('Initialization complete.');
            return 0;
        } catch (Throwable $throwable) {
            $output->error($throwable->getMessage());
            return 1;
        }
    }

    /**
     * Loads environment values from .env if the file exists.
     *
     * No error is raised when the file is absent. The operator will be prompted
     * for all required values interactively.
     */
    private function loadEnvironment(): void
    {
        $environmentDirectory = dirname(__DIR__, 2);
        $environmentFile      = $environmentDirectory . '/.env';

        if (file_exists($environmentFile) !== true) {
            return;
        }

        $dotenv = Dotenv::createUnsafeImmutable($environmentDirectory);
        $dotenv->load();
    }

    /**
     * Returns the metadata definition for each configuration field that init requires.
     *
     * @return array<string, array{description: string, default: string|null, password: bool, generatable: bool, minLength: int, maxLength: int}>
     */
    private function getConfigFields(): array
    {
        return [
            'PROJECT_NAME' => [
                'description' => 'Human-readable name for this application. Used in titles and display strings.',
                'default'     => $this->readComposerProjectName(),
                'password'    => false,
                'generatable' => false,
                'minLength'   => 1,
                'maxLength'   => 128,
            ],
            'ORGANIZATION_NAME' => [
                'description' => 'Human-readable legal organization name used in copyright notices.',
                'default'     => 'Organization',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 1,
                'maxLength'   => 128,
            ],
            'DATABASE_HOST' => [
                'description' => 'Database host name or IP address.',
                'default'     => 'db',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'DATABASE_PORT' => [
                'description' => 'Database port number.',
                'default'     => '3306',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'DATABASE_NAME' => [
                'description' => 'Name of the application database to create.',
                'default'     => null,
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'DATABASE_DBA_USER' => [
                'description' => 'Database administrator username with CREATE and GRANT privileges.',
                'default'     => null,
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'DATABASE_APPLICATION_USER' => [
                'description' => 'The username that the web application will use to connect to the database.',
                'default'     => null,
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'DATABASE_APPLICATION_USER_PASSWORD' => [
                'description' => 'Password for the application runtime database user.',
                'default'     => null,
                'password'    => true,
                'generatable' => true,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'ADMIN_USER_GROUP_NAME' => [
                'description' => 'Display name for the administrators user group.',
                'default'     => 'Application Administrators',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'ADMIN_PERMISSION_TOKEN_PREFIX' => [
                'description' => 'Prefix applied to all administrator permission tokens.',
                'default'     => 'ADMIN_PERMISSION_',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'ADMIN_PERMISSION_SET_NAME' => [
                'description' => 'Display name for the administrators permission set.',
                'default'     => 'Admin Permissions',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'ADMIN_PERMISSION_SET_DESCRIPTION' => [
                'description' => 'Description of the administrators permission set.',
                'default'     => 'Full administrative privileges.',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
            'ADMIN_PERMISSION_SET_TOKEN_PREFIX' => [
                'description' => 'Prefix applied to administrator permission set tokens.',
                'default'     => 'ADMIN_PERMISSION_SET_',
                'password'    => false,
                'generatable' => false,
                'minLength'   => 24,
                'maxLength'   => 48,
            ],
        ];
    }

    /**
     * Prompts the operator for every configuration value, defaulting to any value already
     * present in the environment.
     *
     * For each field the operator may keep the existing value, use the static field default,
        * generate a new one (for password fields), enter a value manually, or skip it. After all
        * prompts, the .env file is rendered from template and fully overwritten.
     *
     * @param Output $output The console output.
     */
    private function collectConfiguration(Output $output): void
    {
        $values = [];

        foreach ($this->getConfigFields() as $name => $field) {
            $raw           = getenv($name);
            $existingValue = ($raw !== false && trim((string) $raw) !== '') ? (string) $raw : null;
            $fieldDefault  = $field['default'];

            $output->writeln();
            if ($existingValue !== null) {
                $displayed = $field['password'] ? '(currently set)' : $existingValue;
                $output->keyValue($name, $displayed);
            } else {
                $output->warning(sprintf('%s is not set.', $name));
            }
            $output->writeln(sprintf('  %s', $field['description']));

            $value      = $this->promptForConfigValue(
                $output,
                $name,
                $field['generatable'],
                $field['password'],
                $existingValue,
                $fieldDefault,
                $field['minLength'],
                $field['maxLength'],
            );
            $initialRaw = $existingValue ?? '';

            if ($value !== $initialRaw) {
                putenv($name . '=' . $value);
            }

            $values[$name] = $value;
        }

        $this->saveConfigurationToEnv($values, $output);
    }

    /**
     * Returns true when init is allowed by failsafe configuration.
     */
    private function isProjectInitAllowed(string $projectRoot): bool
    {
        if (file_exists($projectRoot . '/.env') !== true) {
            return true;
        }

        return $this->isTruthyEnvironmentValue(getenv('ALLOW_PROJECT_INIT'));
    }

    /**
     * Disables ALLOW_PROJECT_INIT after a successful init run.
     */
    private function disableProjectInitFailsafe(Output $output): void
    {
        putenv('ALLOW_PROJECT_INIT=false');
        $this->saveConfigurationToEnv([], $output);
    }

    /**
     * Collects the DBA password for this init run.
     *
     * The password is never loaded from .env and is never persisted to disk.
     */
    private function collectDbaPassword(Output $output): string
    {
        $dbaUser = (string) getenv('DATABASE_DBA_USER');

        while (true) {
            $output->writeln();
            $output->info(sprintf('DBA password source for %s:', $dbaUser));
            echo '  [G] Generate a new password' . PHP_EOL;
            echo '  [E] Enter the current password' . PHP_EOL;
            echo '  Choice [G]: ';

            $choice = strtoupper(trim((string) fgets(STDIN)));

            if ($choice === '') {
                $choice = 'G';
            }

            if ($choice === 'G') {
                $dbaPassword = $this->generatePassword(24, 48);

                $output->writeln();
                $output->info(sprintf('Generated password for DBA user %s:', $dbaUser));
                echo $dbaPassword . PHP_EOL;
                $output->warning('Store this password securely. It is never saved anywhere.');

                return $dbaPassword;
            }

            if ($choice === 'E') {
                $dbaPassword = Terminal::readPassword('  DBA password: ');

                if ($dbaPassword === '') {
                    $output->warning('DBA password cannot be blank.');
                    continue;
                }

                return $dbaPassword;
            }

            $output->warning(sprintf('"%s" is not a valid option. Please choose from: G, E.', $choice));
        }
    }

    /**
     * Prompts the operator for a single configuration value.
     *
     * Shows up to four choices depending on the field's state:
     *   [K] Keep — shown only when a non-empty value is already set in the environment.
     *   [D] Default — shown only when the field definition supplies a static default value.
     *   [G] Generate — shown only for generatable (password) fields.
     *   [E] Enter — always shown.
     *   [S] Skip — shown only when no existing value is set.
     *
     * The default choice (accepted on Enter) is K > D > G > E in that order of precedence.
     *
     * @param string      $name          The environment variable name.
     * @param bool        $isGeneratable Whether the operator may generate a random value.
     * @param bool        $isPassword    Whether the value is a password.
     * @param string|null $existingValue The current value from the environment, or null when absent.
     * @param string|null $fieldDefault  The static default declared in the field definition, or null.
     * @param int         $minLength     Minimum length for generated values.
     * @param int         $maxLength     Maximum length for generated values.
     * @return string The resulting value, or an empty string when skipped.
     */
    private function promptForConfigValue(
        Output  $output,
        string  $name,
        bool    $isGeneratable,
        bool    $isPassword,
        ?string $existingValue,
        ?string $fieldDefault,
        int     $minLength,
        int     $maxLength,
    ): string {
        $validChoices = ['E'];

        if ($existingValue !== null) {
            $validChoices[] = 'K';
        }

        if ($fieldDefault !== null) {
            $validChoices[] = 'D';
        }

        if ($isGeneratable) {
            $validChoices[] = 'G';
        }

        if ($existingValue === null) {
            $validChoices[] = 'S';
        }

        if ($existingValue !== null) {
            $defaultChoice = 'K';
        } elseif ($fieldDefault !== null) {
            $defaultChoice = 'D';
        } elseif ($isGeneratable) {
            $defaultChoice = 'G';
        } else {
            $defaultChoice = 'E';
        }

        while (true) {
            if ($existingValue !== null) {
                $keepLabel = $isPassword
                    ? '[K] Keep existing password'
                    : sprintf('[K] Keep existing value (%s)', $existingValue);
                echo '  ' . $keepLabel . PHP_EOL;
            }

            if ($fieldDefault !== null) {
                $defaultLabel = $isPassword
                    ? '[D] Use default password'
                    : sprintf('[D] Use default (%s)', $fieldDefault);
                echo '  ' . $defaultLabel . PHP_EOL;
            }

            if ($isGeneratable) {
                echo '  [G] Generate a new value' . PHP_EOL;
            }

            echo '  [E] Enter a value' . PHP_EOL;

            if ($existingValue === null) {
                echo '  [S] Skip' . PHP_EOL;
            }

            echo sprintf('  Choice [%s]: ', $defaultChoice);
            $rawInput = trim((string) fgets(STDIN));
            $choice   = strtoupper($rawInput);

            if ($choice === '') {
                $choice = $defaultChoice;
            }

            if (!in_array($choice, $validChoices, true)) {
                echo sprintf('  "%s" is not a valid option. Please choose from: %s.', $rawInput, implode(', ', $validChoices)) . PHP_EOL;
                continue;
            }

            break;
        }

        if ($choice === 'K' && $existingValue !== null) {
            return $existingValue;
        }

        if ($choice === 'D' && $fieldDefault !== null) {
            return $fieldDefault;
        }

        if ($choice === 'G' && $isGeneratable) {
            $generated = $this->generatePassword($minLength, $maxLength);
            $output->writeln();
            $output->info(sprintf('  Generated %s:', $name));
            $output->writeln();
            echo sprintf('    %s', $generated) . PHP_EOL;
            $output->writeln();
            if ($isPassword) {
                echo '  Copy this value now. It will be masked in the confirmation summary.' . PHP_EOL;
            }
            return $generated;
        }

        if ($choice === 'S') {
            echo sprintf('  Skipping %s.', $name) . PHP_EOL;
            return '';
        }

        // $choice === 'E'
        if ($isPassword) {
            $password = Terminal::readPassword(sprintf('  Value for %s: ', $name));

            if ($password === '') {
                if ($existingValue !== null) {
                    echo '  (blank — keeping existing password)' . PHP_EOL;
                    return $existingValue;
                }

                if ($fieldDefault !== null) {
                    echo '  (blank — using default password)' . PHP_EOL;
                    return $fieldDefault;
                }
            }

            return $password;
        }

        if ($existingValue !== null) {
            echo sprintf('  Value for %s (current: %s): ', $name, $existingValue);
        } elseif ($fieldDefault !== null) {
            echo sprintf('  Value for %s (default: %s): ', $name, $fieldDefault);
        } else {
            echo sprintf('  Value for %s: ', $name);
        }

        $value = trim((string) fgets(STDIN));

        if ($value === '') {
            if ($existingValue !== null) {
                return $existingValue;
            }

            if ($fieldDefault !== null) {
                return $fieldDefault;
            }
        }

        return $value;
    }

    /**
     * Persists configuration values by fully overwriting .env from the Twig template.
     *
     * @param array<string, string> $values The key-value pairs to persist.
     * @param Output                $output The console output.
     */
    private function saveConfigurationToEnv(array $values, Output $output): void
    {
        $environmentDirectory = dirname(__DIR__, 2);
        $environmentFile      = $environmentDirectory . '/.env';

        foreach ($values as $name => $value) {
            putenv($name . '=' . $value);
        }

        $content = $this->renderEnvironmentTemplate();

        if (file_put_contents($environmentFile, $content) === false) {
            $output->error('Could not write to .env.');
            return;
        }

        $output->info('Configuration saved to .env.');
    }

    /**
     * Renders the .env file from the Twig template used by init.
     *
     * SUPERADMIN_USERNAME is always rendered as blank to avoid implicit elevation behavior.
     *
     * @throws RuntimeException When the template cannot be rendered.
     */
    private function renderEnvironmentTemplate(): string
    {
        $cliDirectory       = dirname(__DIR__);
        $templatesDirectory = $cliDirectory . '/templates/env';
        $cacheDirectory     = $cliDirectory . '/templates_compiled';

        $loader = new TwigFilesystemLoader($templatesDirectory);
        $twig   = new TwigEnvironment($loader, [
            'cache'       => $cacheDirectory,
            'auto_reload' => true,
        ]);

        $context = [
            'PROJECT_NAME'                        => $this->formatEnvironmentValue(getenv('PROJECT_NAME'), 'Lampfire Auth App', true),
            'ORGANIZATION_NAME'                   => $this->formatEnvironmentValue(getenv('ORGANIZATION_NAME'), 'Organization', true),
            'COMPOSE_PROJECT_NAME'               => $this->formatEnvironmentValue(getenv('COMPOSE_PROJECT_NAME'), 'lampfire'),
            'ALLOW_PROJECT_RESET'                => $this->formatBooleanEnvironmentValue(getenv('ALLOW_PROJECT_RESET'), false),
            'ALLOW_PROJECT_INIT'                 => $this->formatBooleanEnvironmentValue(getenv('ALLOW_PROJECT_INIT'), true),
            'DATABASE_NAME'                      => $this->formatEnvironmentValue(getenv('DATABASE_NAME'), 'lampfire_auth'),
            'DATABASE_HOST'                      => $this->formatEnvironmentValue(getenv('DATABASE_HOST'), 'db'),
            'DATABASE_PORT'                      => $this->formatEnvironmentValue(getenv('DATABASE_PORT'), '3306'),
            'DATABASE_DBA_USER'                  => $this->formatEnvironmentValue(getenv('DATABASE_DBA_USER'), 'lampfire_dba'),
            'DATABASE_APPLICATION_USER'          => $this->formatEnvironmentValue(getenv('DATABASE_APPLICATION_USER'), 'lampfire_auth'),
            'DATABASE_APPLICATION_USER_PASSWORD' => $this->formatEnvironmentValue(getenv('DATABASE_APPLICATION_USER_PASSWORD'), 'mtq1DE2SdvfkXFsBNqCOClYIiSubvxQU'),
            'APP_DEBUG'                          => $this->formatBooleanEnvironmentValue(getenv('APP_DEBUG'), false),
            'ADMIN_USER_GROUP_NAME'             => $this->formatEnvironmentValue(getenv('ADMIN_USER_GROUP_NAME'), 'Application Administrators', true),
            'ADMIN_PERMISSION_TOKEN_PREFIX'     => $this->formatEnvironmentValue(getenv('ADMIN_PERMISSION_TOKEN_PREFIX'), 'ADMIN_PERMISSION_'),
            'ADMIN_PERMISSION_SET_NAME'         => $this->formatEnvironmentValue(getenv('ADMIN_PERMISSION_SET_NAME'), 'Admin Permissions', true),
            'ADMIN_PERMISSION_SET_DESCRIPTION'  => $this->formatEnvironmentValue(getenv('ADMIN_PERMISSION_SET_DESCRIPTION'), 'Full administrative privileges.', true),
            'ADMIN_PERMISSION_SET_TOKEN_PREFIX' => $this->formatEnvironmentValue(getenv('ADMIN_PERMISSION_SET_TOKEN_PREFIX'), 'ADMIN_PERMISSION_SET_'),
            'APP_DOMAIN'                        => $this->formatEnvironmentValue(getenv('APP_DOMAIN'), 'localhost'),
            'APP_PORT'                          => $this->formatEnvironmentValue(getenv('APP_PORT'), '8080'),
            'USE_HTTPS'                         => $this->formatEnvironmentValue(getenv('USE_HTTPS'), '0'),
            'PASETO_KEY'                        => $this->formatEnvironmentValue(getenv('PASETO_KEY'), '5c11b3a44efb4d819913a7b5baefa96dfe10db68a8b4f9ea02b6827342f2edf0'),
            'TOKEN_ISSUER'                      => $this->formatEnvironmentValue(getenv('TOKEN_ISSUER'), 'lampfire'),
        ];

        $rendered = $twig->render('default.twig', $context);

        if (str_ends_with($rendered, PHP_EOL) === false) {
            $rendered .= PHP_EOL;
        }

        return $rendered;
    }

    /**
     * Formats a value for assignment in an .env file.
     */
    private function formatEnvironmentValue(string|false $rawValue, ?string $default = null, bool $alwaysQuote = false): string
    {
        $value = $rawValue;

        if ($value === false || $value === '') {
            $value = $default ?? '';
        }

        if ($alwaysQuote || preg_match('/[\s\'"#]/', $value) === 1) {
            return '"' . addslashes($value) . '"';
        }

        return $value;
    }

    /**
     * Formats a boolean value for assignment in an .env file.
     */
    private function formatBooleanEnvironmentValue(string|false $rawValue, bool $default): string
    {
        if ($rawValue === false || trim($rawValue) === '') {
            return $default ? 'true' : 'false';
        }

        return $this->isTruthyEnvironmentValue($rawValue) ? 'true' : 'false';
    }

    /**
     * Returns true when an environment-style string represents a true value.
     */
    private function isTruthyEnvironmentValue(string|false $rawValue): bool
    {
        if ($rawValue === false) {
            return false;
        }

        return in_array(strtolower(trim($rawValue)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Returns true when the minimum variables required for a database connection are all set.
     */
    private function hasDatabaseConfiguration(): bool
    {
        foreach ([
            'DATABASE_HOST',
            'DATABASE_PORT',
            'DATABASE_NAME',
            'DATABASE_DBA_USER',
            'DATABASE_APPLICATION_USER',
            'DATABASE_APPLICATION_USER_PASSWORD',
        ] as $name) {
            $value = getenv($name);

            if ($value === false || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true when all variables required for the admin seed migration are set.
     */
    private function hasAdminConfiguration(): bool
    {
        $adminVars = [
            'ADMIN_USER_GROUP_NAME',
            'ADMIN_PERMISSION_TOKEN_PREFIX',
            'ADMIN_PERMISSION_SET_NAME',
            'ADMIN_PERMISSION_SET_DESCRIPTION',
            'ADMIN_PERMISSION_SET_TOKEN_PREFIX',
        ];

        foreach ($adminVars as $name) {
            $value = getenv($name);

            if ($value === false || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads the project slug from the app/composer.json name field and returns the
     * portion after the vendor prefix as a title-cased default project name.
     *
     * Returns null when the file is absent or cannot be parsed so that the operator
     * is prompted to supply a value without a pre-filled default.
     */
    private function readComposerProjectName(): ?string
    {
        $composerFile = dirname(__DIR__, 2) . '/app/composer.json';

        if (file_exists($composerFile) !== true) {
            return null;
        }

        $json = file_get_contents($composerFile);

        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        if (is_array($data) !== true || isset($data['name']) !== true || is_string($data['name']) !== true) {
            return null;
        }

        $parts = explode('/', $data['name']);
        $slug  = end($parts);

        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * Polls the Docker health status of the database container until it reports healthy.
     *
     * @throws RuntimeException When the database does not become healthy within 60 seconds.
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
     * Returns true when the database container is currently running.
     */
    private function isDatabaseContainerRunning(string $projectRoot): bool
    {
        return $this->getRunningDatabaseContainerId($projectRoot) !== '';
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
    private function captureShellOutput(string $command, string $projectRoot): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $projectRoot);

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

    /**
     * Starts Docker services for the project.
     *
     * @param string $projectRoot The absolute path to the project root.
     * @param Output $output      The console output.
     * @throws RuntimeException When Docker Compose cannot start services.
     */
    private function startContainers(string $projectRoot, string $bootstrapRootPassword, Output $output): void
    {
        $compose = $this->resolveComposeCommand($projectRoot);
        $command = sprintf(
            'LAMPFIRE_BOOTSTRAP_ROOT_PASSWORD=%s %s up -d --remove-orphans',
            escapeshellarg($bootstrapRootPassword),
            $compose
        );

        $resultCode = $this->runShellCommand($command, $projectRoot, $output);

        if ($resultCode !== 0) {
            throw new RuntimeException(sprintf('%s up -d failed.', $compose));
        }
    }

    /**
     * Recreates the web container so runtime environment values match .env.
     *
     * @param string $projectRoot The absolute path to the project root.
     * @param Output $output      The console output.
     * @throws RuntimeException When Docker Compose cannot recreate the container.
     */
    private function reloadWebContainer(string $projectRoot, Output $output): void
    {
        $compose = $this->resolveComposeCommand($projectRoot);
        $resultCode = $this->runShellCommand(
            $compose . ' up -d --force-recreate --no-deps web',
            $projectRoot,
            $output
        );

        if ($resultCode !== 0) {
            throw new RuntimeException(sprintf('%s up -d --force-recreate --no-deps web failed.', $compose));
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
     * Connects as the MariaDB root user to create the application database and both database users.
     *
     * Creates the application database, the DBA user with full privileges on that database, and
     * the application runtime user with SELECT, INSERT, UPDATE, and DELETE privileges. All three
     * resources are created here rather than via MariaDB environment variables so that the database
     * name and user names remain fully configurable through the init prompt. Neither the root
     * password nor the DBA password is stored anywhere.
     *
     * @param string $rootPassword The generated root password for the MariaDB root account.
     * @param string $dbaPassword  The generated DBA user password.
     * @param Output $output       The console output.
     * @throws RuntimeException When the root connection fails or any account creation fails.
     */
    private function bootstrapDatabaseAccounts(string $rootPassword, string $dbaPassword, Output $output): void
    {
        $pdo     = $this->buildRootConnection($rootPassword);
        $dbaUser = (string) getenv('DATABASE_DBA_USER');
        $appUser = (string) getenv('DATABASE_APPLICATION_USER');
        $appPass = (string) getenv('DATABASE_APPLICATION_USER_PASSWORD');
        $dbName  = (string) getenv('DATABASE_NAME');

        $this->validateMySQLIdentifier($dbaUser);
        $this->validateMySQLIdentifier($appUser);
        $this->validateMySQLIdentifier($dbName);

        $this->executeSqlStatement($pdo, sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
            $this->escapeMySQLIdentifier($dbName)
        ), 'creating application database');
        $output->info(sprintf('Created database: %s', $dbName));

        $this->executeSqlStatement($pdo, sprintf(
            "CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY %s",
            $this->escapeMySQLIdentifier($dbaUser),
            $pdo->quote($dbaPassword)
        ), 'creating DBA user');
        $this->executeSqlStatement($pdo, sprintf(
            "ALTER USER '%s'@'%%' IDENTIFIED BY %s",
            $this->escapeMySQLIdentifier($dbaUser),
            $pdo->quote($dbaPassword)
        ), 'setting DBA user password');
        $this->executeSqlStatement($pdo, sprintf(
            "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE VIEW, SHOW VIEW, TRIGGER ON `%s`.* TO '%s'@'%%'",
            $this->escapeMySQLIdentifier($dbName),
            $this->escapeMySQLIdentifier($dbaUser)
        ), 'granting DBA privileges');
        $output->info(sprintf('Created DBA user: %s', $dbaUser));

        $this->executeSqlStatement($pdo, sprintf(
            "CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY %s",
            $this->escapeMySQLIdentifier($appUser),
            $pdo->quote($appPass)
        ), 'creating application user');
        $this->executeSqlStatement($pdo, sprintf(
            "ALTER USER '%s'@'%%' IDENTIFIED BY %s",
            $this->escapeMySQLIdentifier($appUser),
            $pdo->quote($appPass)
        ), 'setting application user password');
        $this->executeSqlStatement($pdo, sprintf(
            "GRANT SELECT, INSERT, UPDATE, DELETE ON `%s`.* TO '%s'@'%%'",
            $this->escapeMySQLIdentifier($dbName),
            $this->escapeMySQLIdentifier($appUser)
        ), 'granting application user privileges');
        $output->info(sprintf('Created application user: %s', $appUser));

        $this->executeSqlStatement($pdo, 'FLUSH PRIVILEGES', 'flushing privilege cache');
    }

    /**
     * Opens a PDO connection to MariaDB as the root user.
     *
     * Always connects to 127.0.0.1 because init runs on the host, not inside the Docker network.
     *
     * @param string $rootPassword The MariaDB root password.
     * @return PDO The root database connection.
     * @throws RuntimeException When the connection cannot be established.
     */
    private function buildRootConnection(string $rootPassword): PDO
    {
        $dsn = sprintf('mysql:host=127.0.0.1;port=%s;charset=utf8mb4', getenv('DATABASE_PORT'));

        try {
            return new PDO($dsn, 'root', $rootPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                sprintf('Could not connect as root: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * Configures the MariaDB root account to use the generated password.
     *
     * This method assumes a reset/new environment where root currently has an
     * empty password. It updates both root@localhost and root@% so the CLI can
     * connect from the host during initialization.
     *
     * @param string $rootPassword The generated root password.
     * @throws RuntimeException When root cannot be configured.
     */
    private function configureRootAccountPassword(string $rootPassword): void
    {
        try {
            $connection = $this->buildRootConnection($rootPassword);
            $connection->query('SELECT 1');
        } catch (RuntimeException $exception) {
            throw new RuntimeException(
                'Could not connect to MariaDB as root with the generated password. '
                    . 'The database was likely already initialized with a different root password. '
                    . 'Run project-reset and retry init. '
                    . 'Details: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Executes a SQL statement and validates execution success.
     *
     * @param PDO    $connection The PDO connection to use.
     * @param string $sql        The SQL statement.
     * @param string $operation  Human-readable operation label for error context.
     * @return int The affected row count reported by PDO.
     * @throws RuntimeException When statement preparation or execution fails.
     */
    private function executeSqlStatement(PDO $connection, string $sql, string $operation): int
    {
        $statement = $connection->prepare($sql);

        if ($statement === false) {
            $errorInfo = $connection->errorInfo();
            $details = is_array($errorInfo) && isset($errorInfo[2]) && is_string($errorInfo[2])
                ? $errorInfo[2]
                : 'Unknown SQL preparation error.';
            throw new RuntimeException(sprintf('Failed while %s: %s', $operation, $details));
        }

        $success = $statement->execute();

        if ($success === false) {
            $errorInfo = $statement->errorInfo();
            $details = is_array($errorInfo) && isset($errorInfo[2]) && is_string($errorInfo[2])
                ? $errorInfo[2]
                : 'Unknown SQL execution error.';
            throw new RuntimeException(sprintf('Failed while %s: %s', $operation, $details));
        }

        return $statement->rowCount();
    }

    /**
     * Returns true when the configured DBA user can connect to the target database.
     */
    private function canConnectAsDba(string $dbaPassword): bool
    {
        $dsn = sprintf(
            'mysql:host=127.0.0.1;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DATABASE_PORT'),
            getenv('DATABASE_NAME')
        );

        try {
            $connection = new PDO($dsn, (string) getenv('DATABASE_DBA_USER'), $dbaPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $connection->query('SELECT 1');

            return true;
        } catch (PDOException $exception) {
            return false;
        }
    }

    /**
     * Validates that a string is safe for use as a MySQL identifier.
     *
     * Only letters, digits, underscores, and hyphens are permitted.
     *
     * @param string $identifier The identifier to validate.
     * @throws RuntimeException When the identifier contains disallowed characters.
     */
    private function validateMySQLIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z0-9_\-]+$/', $identifier) !== 1) {
            throw new RuntimeException(sprintf(
                'Invalid MySQL identifier "%s". Only letters, digits, underscores, and hyphens are permitted.',
                $identifier
            ));
        }
    }

    /**
     * Escapes a MySQL identifier for safe interpolation inside backtick-quoted contexts.
     *
     * @param string $identifier The identifier to escape.
     * @return string The escaped identifier.
     */
    private function escapeMySQLIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    /**
     * Generates a cryptographically random password.
     *
     * The alphabet consists of uppercase and lowercase letters, digits, and special characters.
     *
     * @param int $minLength The minimum length of the password.
     * @param int $maxLength The maximum length of the password.
     * @return string A random password.
     */
    private function generatePassword(int $minLength = 24, int $maxLength = 48): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_.,/|+=';
        $length   = random_int($minLength, $maxLength);
        $password = '';

        $alphabet = $this->shuffleString($alphabet, random_int(20, 50));

        for ($i = 0; $i < $length; $i++) {
            $alphabet = $this->shuffleString($alphabet, random_int(20, 50));
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /**
     * Returns a cryptographically shuffled copy of a string using Fisher-Yates.
     *
     * @param string $string The string to shuffle.
     * @return string The shuffled string.
     */
    private function shuffleString(string $string, int $numShuffles = 10): string
    {
        $characters = str_split($string);
        $last       = count($characters) - 1;

        for ($i = $last; $i > 0; $i--) {
            $j = random_int(0, $i);

            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    /**
     * Pipes a single SQL migration file to the mysql command-line client as the DBA user.
     *
     * @param string $fileName    The base name of the migration file in app/migrations/.
     * @param string $dbaPassword The DBA user password, held in memory only.
     * @param Output $output      The console output.
     * @throws RuntimeException When mysql exits with a non-zero status code.
     */
    private function runMigration(string $fileName, string $dbaPassword, Output $output): void
    {
        $migrationFilePath = dirname(__DIR__, 2) . '/app/migrations/' . $fileName;

        $output->info(sprintf('Running migration: %s', $fileName));

        $command = sprintf(
            'mysql --table --host=127.0.0.1 --port=%s --user=%s --password=%s %s < %s',
            escapeshellarg((string) getenv('DATABASE_PORT')),
            escapeshellarg((string) getenv('DATABASE_DBA_USER')),
            escapeshellarg($dbaPassword),
            escapeshellarg((string) getenv('DATABASE_NAME')),
            escapeshellarg($migrationFilePath)
        );

        $resultCode = 0;
        passthru($command, $resultCode);

        if ($resultCode !== 0) {
            throw new RuntimeException(sprintf(
                'Migration %s failed with exit code %d.',
                $fileName,
                $resultCode
            ));
        }
    }

    /**
     * Renders each Twig migration template and writes the resulting SQL
     * to the app/migrations/ directory before migrations run.
     *
     * @param Output $output The console output.
     * @throws RuntimeException When a template cannot be rendered or written.
     */
    private function generateMigrations(Output $output): void
    {
        $cliDirectory        = dirname(__DIR__);
        $templatesDirectory  = $cliDirectory . '/templates/migrations/init';
        $cacheDirectory      = $cliDirectory . '/templates_compiled';
        $outputDirectory     = dirname(__DIR__, 2) . '/app/migrations';

        $loader = new TwigFilesystemLoader($templatesDirectory);
        $twig   = new TwigEnvironment($loader, [
            'cache'       => $cacheDirectory,
            'auto_reload' => true,
        ]);

        $context = [
            'ENV'                        => getenv(),
            'admin_permission_set_token' => $this->buildAdminPermissionSetToken(),
        ];

        $templates = [
            '00001-initial-schema.sql.twig'                    => '00001-initial-schema.sql',
            '00002-create-initial-admin_permissions.sql.twig'  => '00002-create-initial-admin_permissions.sql',
        ];

        foreach ($templates as $templateName => $outputFileName) {
            $sql         = $twig->render($templateName, $context);
            $outputPath  = $outputDirectory . '/' . $outputFileName;

            if (file_put_contents($outputPath, $sql) === false) {
                throw new RuntimeException(sprintf(
                    'Could not write migration file %s.',
                    $outputFileName
                ));
            }

            $output->info(sprintf('Generated: %s', $outputFileName));
        }
    }

    /**
     * Builds the permission set token for the administrators permission set.
     *
     * @return string The administrators permission set token.
     */
    private function buildAdminPermissionSetToken(): string
    {
        return ((string) getenv('ADMIN_PERMISSION_SET_TOKEN_PREFIX')) . 'ADMIN';
    }

    /**
     * Builds the list of permission token definitions for the administrators permission set.
     *
     * Each entry contains the permission_token, permission_title, and notes fields
     * that the migration template inserts into the Permissions table.
     *
     * @return array<string, array{permission_token: string, permission_title: string, notes: string}>
     */
}

