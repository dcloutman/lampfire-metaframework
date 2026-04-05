<?php

declare(strict_types=1);

/**
 * Root base class for all controllers.
 *
 * Houses functionality shared across every controller type, including
 * route configuration properties, route-argument extraction, and UUID
 * validation. Concrete controller families extend this to add their
 * own specializations.
 *
 * Route configuration is declared through class properties rather than
 * attributes. RouteDiscovery reads these properties via reflection and
 * registers routes using convention-based mapping for standard method
 * names. Explicit #[Route] attributes on individual methods are still
 * supported as an override for non-standard endpoints.
 */

namespace Lampfire\Controllers;

use Lampfire\Utilities\Validators;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

abstract class AbstractController
{
    /**
     * The URL prefix prepended to every route on this controller.
     *
     * @var string
     */
    protected string $routePrefix = '';

    /**
     * The path pattern appended for single-resource operations such as
     * getById, put, patch, and delete. Override with a composite pattern
     * like '/{setId}/{permissionId}' for join-table controllers.
     *
     * @var string
     */
    protected string $idPattern = '/{id}';

    /**
     * Fully qualified middleware class names applied to every route
     * registered on this controller.
     *
     * @var array<string>
     */
    protected array $routeMiddleware = [];

    /**
     * Convention-based route method names that must not be registered
     * for this controller. Use this to suppress REST conventions that
     * do not apply, such as 'delete' on resources that cannot be removed.
     *
     * @var array<string>
     */
    protected array $routeExclusions = [];

    /**
     * Extracts a named route argument from the request.
     *
     * @param Request $request The incoming request.
     * @param string  $name    The route parameter name.
     * @return string The argument value, or an empty string when absent.
     */
    protected function routeArgument(Request $request, string $name): string
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        return $route !== null ? $route->getArgument($name, '') : '';
    }

    /**
     * Validates that a value is a well-formed UUID v4.
     *
     * @param string $value The value to test.
     * @return bool True when the value is a valid UUID v4.
     */
    protected function isValidUuid(string $value): bool
    {
        return Validators::isValidUuid($value);
    }
}
