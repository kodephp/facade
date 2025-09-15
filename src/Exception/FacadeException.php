<?php

declare(strict_types=1);

namespace Kode\Facade\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Facade Exception
 */
class FacadeException extends Exception implements ContainerExceptionInterface
{
    /**
     * Create an exception for an unknown facade.
     *
     * @param string $name
     * @return static
     */
    public static function unknownFacade(string $name): self
    {
        return new static("Unknown facade: {$name}");
    }

    /**
     * Create an exception for a facade without a resolved instance.
     *
     * @param string $name
     * @return static
     */
    public static function noResolvedInstance(string $name): self
    {
        return new static("No resolved instance for facade: {$name}");
    }

    /**
     * Create an exception for a facade without a resolved instance.
     *
     * @param string $name
     * @param string $method
     * @return static
     */
    public static function undefinedMethod(string $name, string $method): self
    {
        return new static("Undefined method {$method} for facade {$name}");
    }
}