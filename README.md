# nova/facade

现代化的 PHP 8.1+ 门面（Facade）库，面向多进程/多线程/协程环境，兼容 Workerman、Swoole、Swow，并可选集成 Eloquent（laravel/illuminate-database）。同时提供降级策略，确保在功能不可用时安全回退。

## 特性
- PHP 8.1+ 与现代语法，面向未来升级；在运行环境不支持协程/纤程时自动降级到进程级上下文
- 运行时自识别：Swoole、Swow、Workerman、CLI/FPM
- 上下文隔离：在不同协程/线程/进程内自动隔离 Facade 的实例
- 轻量级容器：简洁的 IoC 容器与静态门面基类
- Eloquent 可选集成：无需强依赖，存在时自动接入
- CLI 工具：bin/nova 输出环境与上下文信息，方便排障
- 现代化：反射自动注入与属性驱动单例

## 安装
```bash
composer require nova/facade
```

如需 Eloquent：
```bash
composer require illuminate/database
```

如需协程增强：
```bash
# 至少满足其一
pecl install swoole
pecl install swow
composer require workerman/workerman
```

## 快速开始
```php
use Nova\Container;
use Nova\\Support\\Facades\\Facade;

class FooService { public function hello(string $name): string { return "Hello, {$name}"; } }
class Foo extends Facade { protected static function getFacadeAccessor(): string { return FooService::class; } }

$app = Container::getInstance();
$app->singleton(FooService::class, fn() => new FooService());

echo Foo::hello('Nova'); // Hello, Nova
```

## 在不同运行时下
- Swoole/Swow：使用协程 ID/当前协程对象实现上下文隔离
- Workerman：进程级隔离，个别场景可结合 Fiber 实现更细粒度隔离
- 传统 CLI/FPM：进程/请求级隔离

## Eloquent 集成
```php
use Nova\\Providers\EloquentServiceProvider;
use Nova\\Container;

$provider = new EloquentServiceProvider();
$provider->register(Container::getInstance(), [
    'connections' => [
        'default' => [
            'driver' => 'sqlite',
            'database' => __DIR__.'/database.sqlite',
        ],
    ],
]);

// 之后可直接使用 Illuminate ORM
```

## 降级策略
- 优先使用 Swoole/Swow 协程 ID；不可用则尝试 Fiber；仍不可用则回退到进程 ID
- Facade 实例默认按上下文隔离，确保并发安全；没有协程时即退化为进程/请求级

## 二进制/桌面打包建议（Electron/Tauri）
- 将 PHP 运行时与本库作为后端服务打包，前端通过 HTTP/IPC 调用
- 使用 bin/nova info 探测运行环境，在桌面容器内保持 CLI 模式
- 可选方案：结合 RoadRunner/Swoole Server 作为长驻服务，前端统一通讯

## 许可证
MIT

## 现代化：反射自动注入与属性
- 反射自动注入（Autowire）：容器可基于构造函数类型声明自动解析依赖，无需手工传参。
- 工厂回调智能注入：`bind/singleton` 的 Closure/Callable 会按参数类型自动注入 `Container` 或其他依赖。
- 属性驱动单例：为服务类加上 `#[Nova\\Attributes\Singleton]` 即可声明为单例，无需显式 `singleton()` 绑定。

示例：
```php
use Nova\\Container;
use Nova\\Attributes\Singleton;

#[Singleton]
class HttpClient {
    public function __construct(private Logger $logger) {}
}

class Logger {}

$app = Container::getInstance();
$client = $app->make(HttpClient::class); // Logger 将被自动注入，HttpClient 自动识别为单例
```

## 示例脚本（examples/）

已在仓库根目录下提供可直接运行的示例脚本（需先执行 composer dump-autoload）：

```bash
php examples/01_basic_facade.php
php examples/02_autowire_singleton.php
php examples/03_eloquent_integration.php  # 需要先安装 illuminate/database
php examples/04_runtime_info.php
```

每个脚本都包含必要的注释，展示 Facade 缓存管理、反射自动注入、属性单例、Eloquent 集成与运行时信息探测等能力。

## 完整使用方法与 API 参考

### 容器（Container / ContainerInterface）

核心方法：
- bind(string $abstract, Closure|callable|string $concrete, bool $singleton = false): 绑定抽象到实现（可工厂回调/类名）。
- singleton(string $abstract, Closure|callable|string $concrete): 注册单例绑定。
- instance(string $abstract, object $instance): 直接注入已实例化对象。
- make(string $abstract, array $parameters = []): 解析实例，支持反射自动注入与命名参数覆盖。
- has(string $abstract): 判断是否已绑定或已实例化。

要点与示例：
```php
use Nova\\Container;
use Nova\\Attributes\Singleton;
use Nova\\Contracts\ContainerInterface;

$app = Container::getInstance();

// 1) 类名绑定（自动注入其构造函数依赖）
$app->bind(Logger::class, Logger::class);

// 2) 单例绑定（声明为容器单例）
$app->singleton(Config::class, fn() => new Config('/path/to/config.php'));

// 3) 工厂回调（类型注入与命名参数覆盖）
$app->bind(Client::class, function (ContainerInterface $app, Logger $logger, string $baseUri = 'https://api.example.com') {
    return new Client($logger, $baseUri);
});
$client = $app->make(Client::class, ['baseUri' => 'https://api.internal']);

// 4) 属性驱动单例（无需显式 singleton）
#[Singleton]
class HttpClient { public function __construct(private Logger $logger) {} }
$http1 = $app->make(HttpClient::class);
$http2 = $app->make(HttpClient::class);
assert($http1 === $http2);

// 5) 直接注入实例
$app->instance('clock', new \DateTimeImmutable());
```

注意：
- 工厂回调必须返回对象；否则会抛出异常。
- 对于无法通过类型推断的标量参数，可通过 make 的命名参数覆盖。
- 如果某参数名为 app/container，将按约定注入容器实例。

### 门面（Facade）

自定义门面：
```php
use Nova\\Facade;

class FooService { public function hello(string $name): string { return "Hello, {$name}"; } }
class Foo extends Facade { protected static function getFacadeAccessor(): string { return FooService::class; } }
```

静态调用与上下文缓存：
- 第一次调用时，从容器解析并按“当前上下文（协程/纤程/进程）”缓存实例。
- 同一上下文内的后续调用命中缓存，跨上下文不会共享。

缓存管理 API：
- Facade::clearResolved(?string $accessor = null): 仅清理当前上下文下指定门面的缓存。
- Facade::clearResolvedAll(): 清理当前上下文下所有门面的缓存。

容器桥接：
- Facade::setContainer(?ContainerInterface $container): 注入（或替换）全局容器实例。
- Facade::getContainer(): 读取当前全局容器实例。

### 助手函数

- app(): 返回全局容器实例（ContainerInterface）。
- app(Foo::class): 解析并返回实例（支持自动注入与属性单例）。

### CLI

- 直接运行：php bin/nova info
- 通过 Composer 脚本：composer run nova:info

输出内容包含包信息、PHP/SAPI、运行时、上下文 ID、Fiber 支持、OS 等，便于排障。

### 典型使用模式

1) 在 Swoole/Swow/Workerman 服务中注册 Provider（如 Eloquent）并按上下文使用 Facade。
2) 在 CLI/脚本中使用 app()/Facade 快速获取服务并调用。
3) 利用 Facade::clearResolved 在任务结束处主动清理上下文缓存（非单例服务尤为适用）。


