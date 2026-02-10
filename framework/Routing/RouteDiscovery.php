<?php

declare(strict_types=1);

/**
 * Scans a controller directory and registers routes on the Slim application.
 *
 * Route configuration is read from class properties declared on each
 * controller (routePrefix, idPattern, routeMiddleware). These properties
 * are inherited from AbstractController and overridden by concrete
 * controllers to express their routing needs without attributes.
 *
 * Routes are determined in two ways, applied in order of priority:
 *
 * 1. Explicit #[Route] attributes on individual methods.
 * 2. Convention-based mapping for well-known method names when no
 *    explicit attribute is present.
 *
 * REST conventions apply to any controller with a routePrefix. The
 * following method names are recognised:
 *   get     → GET  {routePrefix}
 *   getById → GET  {routePrefix}{idPattern}
 *   post    → POST {routePrefix}
 *   put     → PUT  {routePrefix}{idPattern}
 *   patch   → PATCH {routePrefix}{idPattern}
 *   delete  → DELETE {routePrefix}{idPattern}
 */

namespace Lampfire\Routing;

use ReflectionClass;
use ReflectionMethod;
use Slim\App;

class RouteDiscovery
{
    /**
     * Convention map for RESTful controllers.
     *
     * Keys are method names. Values contain the HTTP method and whether
     * the path includes the identifier pattern from the controller.
     *
     * @var array<string, array{method: string, usesId: bool}>
     */
    private const REST_CONVENTIONS = [
        'get'     => ['method' => 'GET',    'usesId' => false],
        'getById' => ['method' => 'GET',    'usesId' => true],
        'post'    => ['method' => 'POST',   'usesId' => false],
        'put'     => ['method' => 'PUT',    'usesId' => true],
        'patch'   => ['method' => 'PATCH',  'usesId' => true],
        'delete'  => ['method' => 'DELETE', 'usesId' => true],
    ];

    /**
     * Scans the given directory for controller classes and registers
     * every attributed and convention-based route on the Slim application.
     *
     * @param App    $app       The Slim application instance.
     * @param string $directory Absolute path to the controller directory.
     * @param string $namespace The PSR-4 namespace that maps to that directory.
     * @return void
     */
    public static function register(App $app, string $directory, string $namespace): void
    {
        self::scanDirectory($app, $directory, $namespace);
    }

    /**
     * Recursively scans a directory for controller PHP files and
     * registers their routes. Subdirectories are treated as namespace
     * segments following PSR-4 conventions.
     *
     * @param App    $app       The Slim application instance.
     * @param string $directory Absolute path to the current directory.
     * @param string $namespace The PSR-4 namespace for this directory.
     * @return void
     */
    private static function scanDirectory(App $app, string $directory, string $namespace): void
    {
        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $directory . '/' . $entry;

            // Recurse into subdirectories, appending the directory name
            // as an additional namespace segment.
            if (is_dir($fullPath)) {
                self::scanDirectory($app, $fullPath, $namespace . '\\' . $entry);
                continue;
            }

            // Only process PHP files.
            if (str_ends_with($entry, '.php') === false) {
                continue;
            }

            $className = $namespace . '\\' . basename($entry, '.php');

            if (class_exists($className) === false) {
                continue;
            }

            $reflectionClass = new ReflectionClass($className);

            if ($reflectionClass->isAbstract()) {
                continue;
            }

            self::registerClassRoutes($app, $reflectionClass);
        }
    }

    /**
     * Registers all routes for a single controller class.
     *
     * Reads routing configuration from class properties (routePrefix,
     * idPattern, routeMiddleware). Methods with explicit #[Route]
     * attributes are registered first. Remaining methods are checked
     * against the REST convention map.
     *
     * @param App                     $app             The Slim application.
     * @param ReflectionClass<object> $reflectionClass The reflected controller class.
     * @return void
     */
    private static function registerClassRoutes(App $app, ReflectionClass $reflectionClass): void
    {
        $defaults = $reflectionClass->getDefaultProperties();

        $prefix          = $defaults['routePrefix'] ?? '';
        $idPattern       = $defaults['idPattern'] ?? '/{id}';
        $classMiddleware = $defaults['routeMiddleware'] ?? [];

        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods inherited from parent classes. Convention-based
            // routing must only process methods declared on the concrete
            // controller to avoid accidental registration of base-class helpers.
            if ($method->getDeclaringClass()->getName() !== $reflectionClass->getName()) {
                continue;
            }

            $routeAttributes = $method->getAttributes(Route::class);

            if (count($routeAttributes) > 0) {
                // Explicit #[Route] attributes always take priority.
                self::registerExplicitRoutes(
                    $app,
                    $reflectionClass,
                    $method,
                    $routeAttributes,
                    $prefix,
                    $classMiddleware
                );
                continue;
            }

            // No explicit route declared. Attempt convention-based registration.
            self::registerConventionRoute(
                $app,
                $reflectionClass,
                $method,
                $prefix,
                $classMiddleware,
                $idPattern
            );
        }
    }

    /**
     * Registers routes from explicit #[Route] attributes on a method.
     *
     * @param App                                $app             The Slim application.
     * @param ReflectionClass<object>            $reflectionClass The controller class.
     * @param ReflectionMethod                   $method          The controller method.
     * @param array<\ReflectionAttribute<Route>> $routeAttributes The route attributes.
     * @param string                             $prefix          The route prefix.
     * @param array<string>                      $classMiddleware The controller middleware.
     * @return void
     */
    private static function registerExplicitRoutes(
        App $app,
        ReflectionClass $reflectionClass,
        ReflectionMethod $method,
        array $routeAttributes,
        string $prefix,
        array $classMiddleware
    ): void {
        foreach ($routeAttributes as $routeAttribute) {
            $routeDefinition = $routeAttribute->newInstance();
            $fullPath        = $prefix . $routeDefinition->path;

            $slimRoute = $app->map(
                [strtoupper($routeDefinition->method)],
                $fullPath,
                [$reflectionClass->getName(), $method->getName()]
            );

            // Route-level middleware is applied first, then class-level.
            // Slim executes middleware in LIFO order, so class-level
            // middleware ends up as the outer layer.
            foreach ($routeDefinition->middleware as $middlewareClass) {
                $slimRoute->add($middlewareClass);
            }

            foreach ($classMiddleware as $middlewareClass) {
                $slimRoute->add($middlewareClass);
            }
        }
    }

    /**
     * Attempts to register a route using convention-based mapping.
     *
     * Checks the method name against the REST convention map. If a
     * match is found, the route is registered without requiring an
     * explicit #[Route] attribute.
     *
     * @param App                     $app             The Slim application.
     * @param ReflectionClass<object> $reflectionClass The controller class.
     * @param ReflectionMethod        $method          The controller method.
     * @param string                  $prefix          The group prefix.
     * @param array<string>           $classMiddleware The group middleware.
     * @param string                  $idPattern       The identifier path pattern.
     * @return void
     */
    private static function registerConventionRoute(
        App $app,
        ReflectionClass $reflectionClass,
        ReflectionMethod $method,
        string $prefix,
        array $classMiddleware,
        string $idPattern
    ): void {
        $methodName = $method->getName();
        $httpMethod = null;
        $path       = null;

        // Check REST conventions.
        if (array_key_exists($methodName, self::REST_CONVENTIONS)) {
            $convention = self::REST_CONVENTIONS[$methodName];
            $httpMethod = $convention['method'];
            $path       = $convention['usesId'] ? $idPattern : '';
        }

        if ($httpMethod === null) {
            return;
        }

        $fullPath  = $prefix . $path;
        $slimRoute = $app->map(
            [$httpMethod],
            $fullPath,
            [$reflectionClass->getName(), $methodName]
        );

        foreach ($classMiddleware as $middlewareClass) {
            $slimRoute->add($middlewareClass);
        }
    }
}
