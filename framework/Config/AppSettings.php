<?php

declare(strict_types=1);

/**
 * General application configuration.
 *
 * Provides debug flags, conventional filesystem paths, and the
 * public application URL. Path getters derive their values from
 * the project root supplied at construction time; they follow
 * framework conventions and do not require environment variables.
 */

namespace Lampfire\Config;

class AppSettings extends AbstractSettings
{
    private string $projectRoot;
    private bool $debug;
    private string $applicationUrl;

    /**
     * Reads application configuration from the environment.
     *
     * @param string $projectRoot Absolute path to the project root directory.
     * @throws \RuntimeException When an environment value fails validation.
     */
    public function __construct(string $projectRoot)
    {
        $this->projectRoot    = rtrim($projectRoot, '/');
        $this->debug          = $this->optionalBool('APP_DEBUG', false);
        $this->applicationUrl = $this->optionalString('APPLICATION_URL');
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
     * Returns the public URL of the application.
     */
    public function getApplicationUrl(): string
    {
        return $this->applicationUrl;
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
