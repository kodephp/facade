<?php
require __DIR__ . '/../vendor/autoload.php';

use Nova\Container;
use Nova\Support\Facades\Facade; // 兼容导入风格（Laravel/Lumen 风格）

class GreeterService { 
    public int $id;
    public function __construct() { $this->id = random_int(1, 999999); }
    public function hello(string $name): string { return "[#{$this->id}] Hello, {$name}"; }
}
class Greeter extends Facade { protected static function getFacadeAccessor(): string { return GreeterService::class; } }

$app = Container::getInstance();
// 使用非单例绑定以演示 Facade 的“上下文缓存”与清理效果
$app->bind(GreeterService::class, fn() => new GreeterService());

echo Greeter::hello('Examples') . PHP_EOL; // 第一次解析并缓存到当前上下文

echo Greeter::hello('Examples') . PHP_EOL; // 命中 Facade 缓存，id 不变

Greeter::clearResolved(); // 清理当前上下文下 Greeter 的缓存

echo Greeter::hello('Examples') . PHP_EOL; // 重新解析，id 变化



