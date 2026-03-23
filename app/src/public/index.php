<?php

/**
 * Public entry point for the Slim application.
 *
 * Loads environment variables, builds the DI container,
 * creates the Slim app, registers routes, and runs.
 *
 * @author David Cloutman
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Lampfire\Config\AppSettings;
use Lampfire\Config\ContainerConfig;
use Lampfire\Routing\RouteDiscovery;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;

// Load environment variables from the project root .env file.
$projectRoot = dirname(__DIR__, 2);
$dotenv = Dotenv::createImmutable(dirname($projectRoot));
$dotenv->load();

// Build the PHP-DI container with all application services.
$container = ContainerConfig::build($projectRoot);

// Retrieve application settings for use during bootstrap.
$appSettings = $container->get(AppSettings::class);

// Create the Slim application with the PHP-DI container.
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register the body parsing middleware so POST/PUT payloads are decoded.
$app->addBodyParsingMiddleware();

// Allow HTML forms to override the HTTP method with a hidden _METHOD field.
// This enables PUT and DELETE submissions from browser forms.
$app->add(new MethodOverrideMiddleware());

// Add the routing middleware so RouteContext is available in controllers.
$app->addRoutingMiddleware();

// Discover and register all controller routes from class properties.
RouteDiscovery::register(
    $app,
    dirname(__DIR__) . '/lib/Controllers',
    'App\\Controllers'
);

RouteDiscovery::register(
    $app,
    dirname($projectRoot) . '/framework/Controllers/Api',
    'Lampfire\\Controllers\\Api'
);

// Add Slim error middleware last so it wraps everything.
$app->addErrorMiddleware(
    displayErrorDetails: $appSettings->isDebug(),
    logErrors: true,
    logErrorDetails: true
);

$app->run();

