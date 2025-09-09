<?php
declare(strict_types=1);

use Nova\Container;
use Nova\Contracts\ContainerInterface;

if (!function_exists('app')) {
    /**
     * Helper to access the global container or resolve an abstract.
     * - app(): ContainerInterface
     * - app(Foo::class): Foo instance (autowired if not bound)
     */
    function app(?string $abstract = null): mixed
    {
        $container = Container::getInstance();
        return $abstract ? $container->make($abstract) : $container;
    }
}



