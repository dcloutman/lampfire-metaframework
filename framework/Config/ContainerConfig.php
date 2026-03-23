<?php

declare(strict_types=1);

/**
 * Builds the dependency injection container for the framework.
 *
 * This class registers the standard infrastructure that every
 * Lampfire application needs: database connections, logging,
 * template rendering, and configuration objects. PHP-DI resolves
 * most classes automatically from their constructor type hints.
 *
 * Application code should extend this class and override
 * getApplicationDefinitions() to register any additional services
 * that require manual wiring.
 */

namespace Lampfire\Config;

use DI\ContainerBuilder;
use Lampfire\Database\DatabaseConnection;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class ContainerConfig
{
    /**
     * Creates and returns a fully configured DI container.
     *
     * @param string $projectRoot Absolute path to the project root directory.
     * @return \DI\Container The application container.
     */
    public static function build(string $projectRoot): \DI\Container
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions(static::getFrameworkDefinitions($projectRoot));
        $builder->addDefinitions(static::getApplicationDefinitions($projectRoot));

        return $builder->build();
    }

    /**
     * Returns the definitions that the framework registers by default.
     *
     * These define the PDO connection, logger, Twig renderer, and the
     * AppSettings configuration object. Override this method only if
     * the framework defaults need to be replaced entirely.
     *
     * @param string $projectRoot Absolute path to the project root directory.
     * @return array<string, callable> PHP-DI definition entries.
     */
    protected static function getFrameworkDefinitions(string $projectRoot): array
    {
        return [
            // AppSettings needs the project root path, which is not
            // available from the environment alone.
            AppSettings::class => function () use ($projectRoot): AppSettings {
                return new AppSettings($projectRoot);
            },

            // The PDO instance is obtained from DatabaseConnection,
            // which creates it lazily on first access.
            PDO::class => function (DatabaseConnection $conn): PDO {
                return $conn->getConnection();
            },

            // The application logger writes to the conventional log path.
            LoggerInterface::class => function (AppSettings $app): LoggerInterface {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler($app->getLogPath(), Logger::DEBUG));

                return $logger;
            },

            // The Twig renderer uses compiled-template caching.
            Twig::class => function (AppSettings $app): Twig {
                $twig = Twig::create($app->getTemplatePath(), [
                    'cache'       => $app->getTemplateCachePath(),
                    'auto_reload' => true,
                ]);

                $projectName = trim($app->getProjectName());
                $organizationName = trim($app->getOrganizationName());
                $twig->getEnvironment()->addGlobal(
                    'projectName',
                    $projectName !== '' ? $projectName : 'Application'
                );
                $twig->getEnvironment()->addGlobal(
                    'organizationName',
                    $organizationName !== '' ? $organizationName : 'Organization'
                );

                return $twig;
            },
        ];
    }

    /**
     * Returns additional container definitions for the application.
     *
     * Override this method in a subclass to register services that
     * PHP-DI cannot resolve from constructor type hints alone. For
     * example, a service whose constructor accepts a plain string
     * from the environment would need a manual definition here.
     *
     * The base implementation returns an empty array.
     *
     * @param string $projectRoot Absolute path to the project root directory.
     * @return array<string, callable> PHP-DI definition entries.
     */
    protected static function getApplicationDefinitions(string $projectRoot): array
    {
        return [];
    }
}
