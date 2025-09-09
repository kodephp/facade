<?php
declare(strict_types=1);
namespace Nova;

use Closure;
use Nova\Contracts\ContainerInterface;
use Nova\Attributes\Singleton as SingletonAttr;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * A lightweight dependency injection container with modern PHP features.
 *
 * Features:
 * - bind/singleton/instance registration
 * - reflection-based autowiring (constructor & factory closures)
 * - attribute-driven singleton via #[Nova\\Attributes\Singleton]
 * - interface-first API via Nova\\Contracts\ContainerInterface
 */
class Container implements ContainerInterface
{
    /** Global container instance (can be replaced to support covariance). */
    protected static ?ContainerInterface $instance = null;

    /** @var array<string, array{concrete: Closure|callable|string, singleton: bool}> */
    protected array $bindings = [];

    /** @var array<string, object> */
    protected array $instances = [];

    public static function getInstance(): ContainerInterface
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?ContainerInterface $container): void
    {
        self::$instance = $container;
    }

    /**
     * Bind an abstract to a concrete implementation.
     * @param string $abstract Identifier or class/interface name
     * @param Closure|callable|string $concrete Factory/callable or class name
     * @param bool $singleton Whether to register as singleton
     */
    public function bind(string $abstract, Closure|callable|string $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => $singleton];
    }

    /** Register a singleton binding. */
    public function singleton(string $abstract, Closure|callable|string $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /** Register an existing instance. */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve an abstract into an object, with autowiring and attribute-driven singleton.
     * @param array $parameters Named parameters to override autowiring
     */
    public function make(string $abstract, array $parameters = []): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        if ($concrete instanceof Closure || \is_callable($concrete)) {
            $object = $this->invokeCallable($concrete, $parameters);
        } else {
            $object = $this->build((string)$concrete, $parameters);
        }

        $isSingleton = ($this->bindings[$abstract]['singleton'] ?? false) === true || $this->isAnnotatedSingleton($object);
        if ($isSingleton) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /** Whether an abstract is bound or instantiated. */
    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    /** Build a class via reflection autowiring. */
    protected function build(string $class, array $parameters): object
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class {$class} not found");
        }
        $rc = new ReflectionClass($class);
        if (!$rc->isInstantiable()) {
            throw new \RuntimeException("Class {$class} is not instantiable");
        }
        $ctor = $rc->getConstructor();
        if ($ctor === null) {
            return new $class();
        }
        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $deps[] = $this->resolveParameter($param, $parameters);
        }
        return $rc->newInstanceArgs($deps);
    }

    /** Resolve one parameter (named override -> class type -> union -> defaults/null -> container heuristic). */
    protected function resolveParameter(ReflectionParameter $param, array $parameters): mixed
    {
        $name = $param->getName();

        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $class = $type->getName();
            return $this->make($class, []);
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if (!$t->isBuiltin()) {
                    return $this->make($t->getName(), []);
                }
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }
        if ($param->allowsNull()) {
            return null;
        }

        // best-effort inject container for common names
        if (in_array($name, ['app', 'container'], true)) {
            return $this;
        }

        throw new \RuntimeException('Unable to resolve parameter $' . $name);
    }

    /** Invoke a factory callable with autowired parameters. Must return an object. */
    protected function invokeCallable(callable $callable, array $parameters): object
    {
        $rf = new ReflectionFunction(Closure::fromCallable($callable));
        $args = [];
        foreach ($rf->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
                continue;
            }
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $class = $type->getName();
                if ($class === self::class || is_a($this, $class, true)) {
                    $args[] = $this; // inject container or interface
                } else {
                    $args[] = $this->make($class);
                }
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }
            if (in_array($name, ['app', 'container'], true)) {
                $args[] = $this;
                continue;
            }
            throw new \RuntimeException('Unable to resolve callable parameter $' . $name);
        }
        $result = $rf->invokeArgs($args);
        if (!\is_object($result)) {
            throw new \RuntimeException('Factory callable must return an object');
        }
        return $result;
    }

    /** Detect #[Singleton] attribute on a class. */
    protected function isAnnotatedSingleton(object $object): bool
    {
        $rc = new ReflectionClass($object);
        foreach ($rc->getAttributes() as $attr) {
            if ($attr->getName() === SingletonAttr::class) {
                return true;
            }
        }
        return false;
    }
}



