<?php
declare(strict_types=1);
namespace Nova\Context;
use Nova\Support\Runtime;

final class ContextStorage
{
    private static array $store = [];

    public static function id(): string
    {
        if (Runtime::isSwoole() && method_exists('\\Swoole\\Coroutine', 'getCid')) {
            $cid = \Swoole\Coroutine::getCid();
            if ($cid !== -1) {
                return 'swoole:' . $cid;
            }
        }

        if (Runtime::isSwow() && class_exists('\\Swow\\Coroutine')) {
            try {
                $current = \Swow\Coroutine::getCurrent();
                if ($current) {
                    if (method_exists($current, 'getId')) {
                        return 'swow:' . $current->getId();
                    }
                    return 'swow:' . spl_object_id($current);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (Runtime::hasFibers() && class_exists('\\Fiber')) {
            $fiber = \Fiber::getCurrent();
            if ($fiber) {
                return 'fiber:' . spl_object_id($fiber);
            }
        }

        return 'proc:' . (string) getmypid();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $id = self::id();
        return self::$store[$id][$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        $id = self::id();
        return isset(self::$store[$id]) && array_key_exists($key, self::$store[$id]);
    }

    public static function set(string $key, mixed $value): void
    {
        $id = self::id();
        self::$store[$id][$key] = $value;
    }

    public static function delete(string $key): void
    {
        $id = self::id();
        if (isset(self::$store[$id][$key])) {
            unset(self::$store[$id][$key]);
        }
    }

    public static function clearByPrefix(string $prefix): void
    {
        $id = self::id();
        if (!isset(self::$store[$id])) {
            return;
        }
        foreach (array_keys(self::$store[$id]) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset(self::$store[$id][$k]);
            }
        }
    }

    public static function clear(): void
    {
        $id = self::id();
        unset(self::$store[$id]);
    }
}



