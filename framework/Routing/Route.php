<?php

declare(strict_types=1);

/**
 * PHP 8 attribute that explicitly binds a controller method to an HTTP route.
 *
 * This attribute is optional for methods whose names match convention-based
 * routing patterns. Use it when a method needs a non-standard HTTP method,
 * path, or per-route middleware that conventions cannot express.
 *
 * When present, explicitly declared routes always take priority over
 * convention-based routing for the same method.
 */

namespace Lampfire\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * Creates a route binding.
     *
     * @param string        $method     The HTTP method, such as GET, POST, PUT, or DELETE.
     * @param string        $path       The route path relative to the class-level RouteGroup prefix.
     * @param array<string> $middleware Fully qualified middleware class names for this route only.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $middleware = [],
    ) {
    }
}
