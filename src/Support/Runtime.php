<?php
declare(strict_types=1);
namespace Nova\Support;

final class Runtime
{
    public const RUNTIME_FPM = 'fpm';
    public const RUNTIME_CLI = 'cli';
    public const RUNTIME_SWOOLE = 'swoole';
    public const RUNTIME_SWOW = 'swow';
    public const RUNTIME_WORKERMAN = 'workerman';
    public const OS_WINDOWS = 'windows';
    public const OS_UNIX = 'unix';

    public static function isCli(): bool { return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg'; }
    public static function isWindows(): bool { return DIRECTORY_SEPARATOR === '\\'; }
    public static function hasFibers(): bool { return class_exists('Fiber'); }
    public static function isSwoole(): bool { return extension_loaded('swoole') && class_exists('\\Swoole\\Coroutine'); }
    public static function isSwow(): bool { return extension_loaded('swow') && class_exists('\\Swow\\Coroutine'); }
    public static function isWorkerman(): bool { return class_exists('\\Workerman\\Worker'); }

    public static function getRuntime(): string {
        if (self::isSwoole()) return self::RUNTIME_SWOOLE;
        if (self::isSwow()) return self::RUNTIME_SWOW;
        if (self::isWorkerman()) return self::RUNTIME_WORKERMAN;
        return self::isCli() ? self::RUNTIME_CLI : self::RUNTIME_FPM;
    }
}


