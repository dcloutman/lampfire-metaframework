<?php

declare(strict_types=1);

namespace Cli\Commands;

use Cli\Attributes\Command;
use Cli\Console\AbstractCommand;
use Cli\Console\Input;
use Cli\Console\Output;
use Lampfire\Config\DatabaseConfig;
use Lampfire\Config\SecuritySettings;
use Lampfire\Database\DatabaseConnection;
use Lampfire\Gateways\UserDataGateway;
use Lampfire\Gateways\UserGateway;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Creates a new user account with no group memberships or permissions.
 *
 * Prompts interactively for all required and optional fields. The new account can
 * log in immediately but has no application privileges until a user group membership
 * is assigned separately.
 */
#[Command(
    name: 'create-user',
    description: 'Create a new user account with no permissions.',
    help: 'Prompts for a username, email address, password, and optional name fields, '
        . 'then inserts the account into the database. The new account has no group '
        . 'memberships or permissions. Docker services must be running before this command is executed.',
)]
final class CreateUserCommand extends AbstractCommand
{
    /**
     * Executes the create-user workflow.
     *
     * When called from the host, this method re-invokes itself inside the Docker CLI
     * container so that the database is reachable via the Docker network hostname.
     */
    public function execute(Input $input, Output $output): int
    {
        try {
            if ($this->isRunningInDocker() === false) {
                return $this->reRunInDockerContainer($output);
            }

            $pdo             = (new DatabaseConnection(new DatabaseConfig()))->getConnection();
            $userGateway     = new UserGateway($pdo);
            $userDataGateway = new UserDataGateway($pdo);

            $username  = $this->promptUsername($userGateway, $output);
            $email     = $this->promptEmail($userDataGateway, $output);
            $password  = $this->generatePassword();
            $firstName = $this->promptOptionalField('First name', $output);
            $lastName  = $this->promptOptionalField('Last name', $output);

            $userId       = Uuid::uuid4()->toString();
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

            $pdo->beginTransaction();

            try {
                $userGateway->insert($userId, $username, $passwordHash);
                $userDataGateway->insert(
                    $userId,
                    $email,
                    $firstName !== '' ? $firstName : null,
                    $lastName !== '' ? $lastName : null
                );
                $pdo->commit();
            } catch (Throwable $throwable) {
                $pdo->rollBack();
                throw $throwable;
            }

            $output->writeln();
            $output->info(sprintf('User "%s" created successfully. ID: %s', $username, $userId));
            $output->info(sprintf('Temporary password: %s', $password));
            $output->info('Provide this password to the user and instruct them to change it after first login.');

            return 0;
        } catch (Throwable $throwable) {
            $output->error($throwable->getMessage());
            return 1;
        }
    }

    /**
     * Returns true when the process is running inside a Docker container.
     */
    private function isRunningInDocker(): bool
    {
        return file_exists('/.dockerenv');
    }

    /**
     * Re-invokes this command inside the already-running web container.
     *
     * The web container has identical source mounts and database network access.
     * Stdio is inherited so that interactive prompts and terminal echo suppression
     * work exactly as if the command were running locally.
     *
     * @param Output $output The console output.
     * @return int The exit code from the container process.
     * @throws RuntimeException When Docker Compose is not available or the exec fails.
     */
    private function reRunInDockerContainer(Output $output): int
    {
        $projectRoot = dirname(__DIR__, 2);

        if ($this->runShellCommand('docker compose version >/dev/null 2>&1', $projectRoot) !== 0) {
            throw new RuntimeException('"docker compose" is not available. Install Docker Compose v2 and retry.');
        }

        $command = sprintf('docker compose exec -t -w /var/www/app web php cli.php %s', escapeshellarg('create-user'));

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes, $projectRoot);

        if (is_resource($process) !== true) {
            throw new RuntimeException('Failed to exec into the web container.');
        }

        return proc_close($process);
    }

    /**
     * Runs a shell command and returns its exit code.
     *
     * @param string $command     The shell command to run.
     * @param string $workingDir  The working directory for the command.
     * @return int The exit code.
     */
    private function runShellCommand(string $command, string $workingDir): int
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDir);

        if (is_resource($process) !== true) {
            return 1;
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    /**
     * Prompts for a username, re-prompting when the value is too short or already taken.
     *
     * @param UserGateway $gateway The gateway used to check username uniqueness.
     * @param Output      $output  The console output.
     * @return string The accepted username.
     */
    private function promptUsername(UserGateway $gateway, Output $output): string
    {
        while (true) {
            $output->writeln();
            echo sprintf('Username (minimum %d characters): ', SecuritySettings::MINIMUM_USERNAME_LENGTH);
            $value = trim($this->readStdin());

            if (strlen($value) < SecuritySettings::MINIMUM_USERNAME_LENGTH) {
                $output->warning(sprintf('Username must be at least %d characters.', SecuritySettings::MINIMUM_USERNAME_LENGTH));
                continue;
            }

            if ($gateway->usernameExists($value)) {
                $output->warning(sprintf('The username "%s" is already taken.', $value));
                continue;
            }

            return $value;
        }
    }

    /**
     * Prompts for an email address, re-prompting when the format is invalid or the
     * address is already registered.
     *
     * @param UserDataGateway $gateway The gateway used to check email uniqueness.
     * @param Output          $output  The console output.
     * @return string The accepted email address.
     */
    private function promptEmail(UserDataGateway $gateway, Output $output): string
    {
        while (true) {
            $output->writeln();
            echo 'Email address: ';
            $value = trim($this->readStdin());

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $output->warning('Please enter a valid email address.');
                continue;
            }

            if ($gateway->emailExists($value)) {
                $output->warning(sprintf('"%s" is already registered to another account.', $value));
                continue;
            }

            return $value;
        }
    }

    /**
     * Generates a cryptographically random password that satisfies the minimum length requirement.
     *
     * The password is encoded as a URL-safe Base64 string so it contains only printable characters.
     *
     * @return string The generated plaintext password.
     */
    private function generatePassword(): string
    {
        $byteCount = (int) ceil(SecuritySettings::MINIMUM_PASSWORD_LENGTH * 6 / 8);
        return rtrim(strtr(base64_encode(random_bytes($byteCount)), '+/', '-_'), '=');
    }

    /**
     * Reads one line from STDIN, suppressing terminal echo when connected to a TTY.
     *
     * @param bool $isTty Whether STDIN is connected to an interactive terminal.
     * @return string The trimmed input value.
     */
    private function readLine(bool $isTty): string
    {
        if ($isTty) {
            system('stty -echo');
        }

        try {
            $value = trim($this->readStdin());
        } finally {
            if ($isTty) {
                system('stty echo');
            }
        }

        return $value;
    }

    /**
     * Reads one line from STDIN and returns it as a string.
     *
     * @return string The raw line including any trailing newline.
     * @throws RuntimeException When STDIN is closed or returns no data.
     */
    private function readStdin(): string
    {
        $line = fgets(STDIN);

        if ($line === false) {
            throw new RuntimeException('Input stream closed. Aborting.');
        }

        return $line;
    }

    /**
     * Prompts for an optional text field. Returns an empty string when no value is entered.
     *
     * @param string $label  The field label shown to the operator.
     * @param Output $output The console output.
     * @return string The entered value, or an empty string when skipped.
     */
    private function promptOptionalField(string $label, Output $output): string
    {
        $output->writeln();
        echo sprintf('%s (optional, press Enter to skip): ', $label);

        return trim($this->readStdin());
    }
}
