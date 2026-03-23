<?php

declare(strict_types=1);

/**
 * General application configuration.
 *
 * Provides debug flags, conventional filesystem paths, and the public application URL.
 * The URL is composed at runtime from APP_DOMAIN, APP_PORT, and USE_HTTPS rather than
 * stored as a single opaque string, so each component can vary independently per environment.
 * Path getters derive their values from the project root supplied at construction time.
 */

namespace Lampfire\Config;

class AppSettings extends AbstractSettings
{
    private string $projectRoot;
    private bool $debug;
    private string $projectName;
    private string $organizationName;
    private string $appDomain;
    private int $appPort;
    private bool $useHttps;

    /**
     * Reads application configuration from the environment.
     *
     * @param string $projectRoot Absolute path to the project root directory.
     * @throws \RuntimeException When an environment value fails validation.
     */
    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->debug       = $this->getOptionalEnvironmentBool('APP_DEBUG', false);
        $this->projectName = $this->getOptionalEnvironmentString('PROJECT_NAME');
        $this->organizationName = $this->getOptionalEnvironmentString('ORGANIZATION_NAME', 'Organization');
        $this->appDomain   = $this->getOptionalEnvironmentString('APP_DOMAIN');
        $this->appPort     = (int) $this->getOptionalEnvironmentString('APP_PORT', '80');
        $this->useHttps    = $this->getOptionalEnvironmentBool('USE_HTTPS', false);
    }

    /**
     * Returns the absolute project root path.
     */
    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * Returns whether the application is running in debug mode.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Returns the human-readable project name.
     */
    public function getProjectName(): string
    {
        return $this->projectName;
    }

    /**
     * Returns the human-readable organization name.
     */
    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    /**
     * Returns the public domain name of the application, without a scheme or port.
     */
    public function getAppDomain(): string
    {
        return $this->appDomain;
    }

    /**
     * Returns the port the application listens on.
     */
    public function getAppPort(): int
    {
        return $this->appPort;
    }

    /**
     * Returns whether the application is served over HTTPS.
     */
    public function usesHttps(): bool
    {
        return $this->useHttps;
    }

    /**
     * Returns the full public URL of the application, composed from APP_DOMAIN, APP_PORT,
     * and USE_HTTPS. The port is omitted when it matches the default for the scheme.
     */
    public function getApplicationUrl(): string
    {
        $scheme     = $this->useHttps ? 'https' : 'http';
        $defaultPort = $this->useHttps ? 443 : 80;

        if ($this->appPort === $defaultPort || $this->appPort === 0) {
            return sprintf('%s://%s', $scheme, $this->appDomain);
        }

        return sprintf('%s://%s:%d', $scheme, $this->appDomain, $this->appPort);
    }

    /**
     * Returns the conventional path to the application log file.
     */
    public function getLogPath(): string
    {
        return $this->projectRoot . '/logs/app.log';
    }

    /**
     * Returns the conventional path to the Twig template directory.
     */
    public function getTemplatePath(): string
    {
        return $this->projectRoot . '/templates';
    }

    /**
     * Returns the conventional path for compiled Twig templates.
     */
    public function getTemplateCachePath(): string
    {
        return $this->projectRoot . '/templates_compiled';
    }
}
