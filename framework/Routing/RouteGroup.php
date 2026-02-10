<?php

declare(strict_types=1);

/**
 * PHP 8 attribute that declares a shared route prefix, middleware, and
 * convention settings for every route in a controller class.
 *
 * When present, the RouteDiscovery scanner uses these values to:
 * - Prepend the prefix to every Route path in the class.
 * - Apply the listed middleware to every route in the class.
 * - Map standard method names to routes automatically using the
 *   idPattern and resource values, so that explicit #[Route]
 *   attributes are only needed for non-standard endpoints.
 */

namespace Lampfire\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
    /**
     * Creates a route group declaration.
     *
     * @param string        $prefix     The URL prefix prepended to every route path in the class.
     * @param array<string> $middleware Fully qualified middleware class names applied to every route.
     * @param string        $idPattern  The path segment for single-resource routes. Defaults to
     *                                  '/{id}' for simple primary keys. Override with composite
     *                                  patterns such as '/{setId}/{permissionId}' for join tables.
     * @param string        $resource   The URL segment for admin CRUD conventions. When set, methods
     *                                  named index, create, store, show, edit, update, and destroy
     *                                  are mapped to standard CRUD routes under this resource name.
     */
    public function __construct(
        public readonly string $prefix = '',
        public readonly array $middleware = [],
        public readonly string $idPattern = '/{id}',
        public readonly string $resource = '',
    ) {
    }
}
