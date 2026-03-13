# KodePHP Facade 组件

> **包名:** `kode/facade`  
> **版本:** 2.0.0 (稳定版)  
> **PHP 版本:** >=8.1  
> **作者:** KodePHP Team  
> **许可证:** Apache-2.0  
> **IDE 支持:** PhpStorm, VS Code

---

## 📦 概述

`kode/facade` 是一个**健壮、通用、轻量级**的 PHP 门面抽象组件，专为 [KodePHP](https://github.com/kodephp) 框架设计，同时兼容 **Laravel、Symfony、ThinkPHP 8、Webman、自研框架** 等主流 PHP 框架。

该组件提供：

- ✅ **静态代理** - 实现服务容器绑定的动态调用
- ✅ **PHP 8.1+ 支持** - 支持所有新特性（协变、逆变、枚举、只读类等）
- ✅ **协程安全** - 完全无副作用，不影响协程、多线程、多进程模型
- ✅ **反射缓存** - 使用反射 + 缓存实现安全、快速的方法调用
- ✅ **上下文隔离** - 支持 Fiber、Swoole、Swow 等协程环境的上下文隔离
- ✅ **跨框架兼容** - 可作为通用组件在任何 PSR-11 容器中使用
- ✅ **IDE 友好** - 提供完整的 PHPDoc 智能提示支持

---

## 🧩 核心设计理念

| 特性 | 说明 |
|------|------|
| 🔐 **无全局状态污染** | 不使用 `static::$app` 全局赋值，通过 `ContainerInterface` 注入 |
| ⚡ **性能优化** | 方法映射缓存 + 反射缓存，避免重复解析 |
| 🔄 **协变逆变支持** | 接口返回类型与参数支持 PHP 泛型风格协变与逆变 |
| 🧱 **解耦设计** | 仅依赖 `Psr\Container\ContainerInterface`，不依赖具体实现 |
| 🔧 **高度可配置** | 支持自定义容器实现、门面映射、方法缓存等 |
| 🔧 **高度可扩展** | 支持自定义门面映射、方法缓存等 |

---

## 📚 安装方式

```bash
composer require kode/facade
```

---

## 🧠 核心类与 API

### 1. `Facade` 抽象类（核心）

> 所有门面的基类，提供静态代理能力。

```php
namespace Kode\Facade;

use Psr\Container\ContainerInterface;

abstract class Facade
{
    /**
     * 获取当前门面对应的服务名（在容器中的 key）
     */
    abstract protected static function id(): string;

    /**
     * 设置服务容器
     */
    public static function setContainer(ContainerInterface $container): void;

    /**
     * 清除当前门面的代理实例（用于测试或重置）
     */
    public static function clear(): void;

    /**
     * 清除所有门面的缓存实例
     */
    public static function clearAll(): void;

    /**
     * 检查门面是否已解析
     */
    public static function isResolved(): bool;

    /**
     * 获取此门面的服务ID
     */
    public static function getServiceId(): string;

    /**
     * 使用参数数组调用门面实例上的方法
     */
    public static function call(string $method, array $args = []): mixed;

    /**
     * 检查门面实例上是否存在指定方法
     */
    public static function hasMethod(string $method): bool;

    /**
     * 启用上下文安全模式
     */
    public static function enableContextSafeMode(): void;

    /**
     * 禁用上下文安全模式
     */
    public static function disableContextSafeMode(): void;

    /**
     * 检查是否启用了上下文安全模式
     */
    public static function isContextSafeMode(): bool;

    /**
     * 动态静态调用转发
     */
    public static function __callStatic(string $method, array $args): mixed;
}
```

---

### 2. `FacadeProxy`（代理管理器）

> 管理门面类与实际服务实例之间的映射关系。

```php
namespace Kode\Facade;

final class FacadeProxy
{
    /**
     * 绑定门面到服务ID
     */
    public static function bind(string $facade, string $serviceId): void;

    /**
     * 批量绑定门面
     */
    public static function bindMany(array $bindings): void;

    /**
     * 解除门面绑定
     */
    public static function unbind(string $facade): void;

    /**
     * 检查门面是否已绑定
     */
    public static function isBound(string $facade): bool;

    /**
     * 获取门面对应的服务ID
     */
    public static function getServiceId(string $facade): ?string;

    /**
     * 获取所有门面绑定
     */
    public static function getBindings(): array;

    /**
     * 模拟门面实例（用于测试）
     */
    public static function mock(string $facade, object|Closure $mock): void;

    /**
     * 检查门面是否被模拟
     */
    public static function isMocked(string $facade): bool;

    /**
     * 获取门面实例
     */
    public static function getInstance(string $facade): object;

    /**
     * 清除所有数据
     */
    public static function clearAll(): void;
}
```

---

### 3. `ContextualFacadeManager`（上下文管理器）

> 为协程环境提供上下文隔离的门面实例管理。

```php
namespace Kode\Facade;

final class ContextualFacadeManager
{
    /**
     * 设置服务容器
     */
    public static function setContainer(ContainerInterface $container): void;

    /**
     * 获取门面实例
     */
    public static function getInstance(string $facadeClass): object;

    /**
     * 检查门面实例是否存在于当前上下文
     */
    public static function hasInstance(string $facadeClass): bool;

    /**
     * 清除当前上下文的所有门面实例
     */
    public static function clearInstances(): void;
}
```

---

## 🛠 使用示例

### 步骤 1：定义一个服务接口

```php
namespace App\Service;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
    public function getDriver(): string;
}
```

### 步骤 2：实现服务

```php
namespace App\Service;

class SmtpMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        // 发送逻辑...
        return true;
    }

    public function getDriver(): string
    {
        return 'smtp';
    }
}
```

### 步骤 3：创建门面

```php
namespace App\Facade;

use Kode\Facade\Facade;

/**
 * 邮件门面
 *
 * @method static bool send(string $to, string $subject, string $body)
 * @method static string getDriver()
 *
 * @see \App\Service\MailerInterface
 */
class Mail extends Facade
{
    protected static function id(): string
    {
        return 'mailer'; // 对应容器中的服务 key
    }
}
```

### 步骤 4：在任意框架中使用

#### Laravel / Symfony / ThinkPHP / Webman 示例

```php
use App\Facade\Mail;
use Kode\Facade\FacadeProxy;

// 绑定门面到服务ID
FacadeProxy::bind(\App\Facade\Mail::class, 'mailer');

// 设置容器
Mail::setContainer($container);

// 使用静态调用
Mail::send('user@example.com', 'Hello', 'Welcome!');
echo Mail::getDriver(); // 输出: smtp
```

---

## 🧩 增强功能

### ✅ 门面状态检查

```php
// 检查门面是否已解析
if (Mail::isResolved()) {
    // 门面已解析
}

// 检查门面是否绑定到服务ID
if (FacadeProxy::isBound(\App\Facade\Mail::class)) {
    // 门面已绑定
}
```

### ✅ 获取门面信息

```php
// 获取门面的服务ID
$serviceId = Mail::getServiceId();

// 获取门面的服务ID（通过代理）
$serviceId = FacadeProxy::getServiceId(\App\Facade\Mail::class);

// 获取所有绑定的门面
$bindings = FacadeProxy::getBindings();
```

### ✅ 方法调用增强

```php
// 使用 call 方法调用，参数以数组形式传递
$result = Mail::call('send', ['user@example.com', 'Subject', 'Body']);

// 检查门面实例上是否存在指定方法
if (Mail::hasMethod('send')) {
    // 方法存在
}
```

### ✅ 上下文安全模式（Context-Safe Mode）

在协程环境（如 Swoole、Swow、PHP 8.1+ Fiber）中，可以启用上下文安全模式以确保门面实例在不同协程间隔离：

```php
// 启用上下文安全模式
Mail::enableContextSafeMode();

// 现在每个协程将拥有独立的门面实例缓存
// 避免不同协程间的实例污染问题
```

上下文安全模式特性：
- ✅ 每个协程/上下文拥有独立的实例缓存
- ✅ 支持 PHP Fiber、Swoole、Swow 等协程环境
- ✅ 与原有 API 完全兼容
- ✅ 可随时启用或禁用

```php
// 检查是否启用了上下文安全模式
if (Mail::isContextSafeMode()) {
    // 上下文安全模式已启用
}

// 禁用上下文安全模式
Mail::disableContextSafeMode();
```

### ✅ 测试模拟

```php
// 模拟门面实例
$mockMailer = new class implements \App\Service\MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "[MOCK] Sending email to {$to}";
        return true;
    }

    public function getDriver(): string {
        return 'mock-driver';
    }
};

Mail::mock($mockMailer);

// 现在调用将使用模拟实例
Mail::send('test@example.com', 'Test', 'Body'); // 输出: [MOCK] Sending email to test@example.com
```

---

## 🧩 高级特性

### ✅ 协变（Covariance）支持

```php
interface ResponseFactory
{
    public function make(): Response; // 返回基类
}

interface JsonResponseFactory extends ResponseFactory
{
    public function make(): JsonResponse; // 子类返回更具体的类型（协变）
}
```

✅ `kode/facade` 完全支持此类返回类型的协变。

---

### ✅ 逆变（Contravariance）支持

```php
interface EventDispatcher
{
    public function dispatch(object $event): void;
}

interface SpecificEventDispatcher extends EventDispatcher
{
    public function dispatch(SpecificEvent $event): void; // 参数更具体（逆变）
}
```

✅ 参数类型的逆变在反射调用中被正确处理。

---

### ✅ 反射安全调用（带缓存）

内部使用 `ReflectionMethod` + 数组缓存：

```php
$reflector = new ReflectionMethod($instance, $method);
$reflector->invokeArgs($instance, $args);
```

调用信息缓存于静态数组，避免重复反射。

---

## 🧪 测试与兼容性

| 框架 | 兼容性 | 说明 |
|------|--------|------|
| Laravel 9+ | ✅ | 使用 `app()` 或 `Container` 注入 |
| Symfony 6+ | ✅ | 通过 `ServiceContainer` 传入 |
| ThinkPHP 8 | ✅ | 使用 `app()` 兼容 PSR 容器 |
| Webman 1+ | ✅ | 支持 Workerman 多进程模型 |
| Swoole 协程 | ✅ | 无全局变量，协程安全 |
| 多线程（ZTS）| ✅ | 不使用静态实例缓存线程局部存储 |

---

## 📂 包结构

```
vendor/kode/facade/
├── src/
│   ├── Facade.php                 # 门面抽象基类
│   ├── FacadeProxy.php            # 门面代理管理器
│   ├── ContextualFacadeManager.php # 上下文安全门面管理器
│   └── Exception/
│       └── FacadeException.php    # 门面异常类
├── tests/
│   └── Unit/
│       ├── FacadeTest.php         # 门面测试
│       └── ContextualFacadeTest.php # 上下文门面测试
├── composer.json
├── LICENSE
└── README.md
```

---

## 📄 `composer.json`

```json
{
    "name": "kode/facade",
    "type": "library",
    "description": "适用于 PHP 8.1+ 的健壮、通用门面组件，兼容 Laravel、Symfony、ThinkPHP、Webman 和 KodePHP。",
    "keywords": ["facade", "proxy", "static", "container", "psr", "kodephp"],
    "license": "Apache-2.0",
    "authors": [
        {
            "name": "半本正经",
            "email": "382601296@qq.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "psr/container": "^1.0 || ^2.0",
        "kode/context": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "Kode\\Facade\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Kode\\Facade\\Tests\\": "tests/"
        }
    }
}
```

---

## 📌 最佳实践建议

1. **门面类名**：使用单数、动词或名词，如 `Mail`、`Cache`、`Log`、`DB`
2. **方法名**：保持与服务接口一致，避免动词重复（如 `getGet`）
3. **不覆盖 `__callStatic`**：避免破坏代理机制
4. **绑定在启动时完成**：在 `bootstrap.php` 或 `ServiceProvider` 中调用 `FacadeProxy::bind()`
5. **测试时使用 `clear()`**：避免测试间状态污染
6. **协程环境启用上下文安全模式**：确保实例隔离

---

## 📞 联系与贡献

- GitHub: [github.com/kodephp/facade](https://github.com/kodephp/facade)
- Issues: 欢迎提交 Bug 与 Feature Request
- PR: 开放贡献，需包含单元测试

---

> ✅ `kode/facade` —— **简单、安全、通用、高性能的 PHP 门面解决方案**。  
> 为未来协程、多线程、多进程架构打下坚实基础。
