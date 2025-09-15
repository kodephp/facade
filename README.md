# KodePHP Facade Component

> **Package Name:** `kode/facade`  
> **Version:** 1.0.0 (Stable)  
> **PHP Version:** >=8.1  
> **Author:** KodePHP Team  
> **License:** Apache-2.0  
> **IDE Support:**   PhpStorm, VS Code  

---

## 📦 概述

`kode/facade` 是一个**健壮、通用、轻量级**的 PHP Facade 抽象组件，专为 [KodePHP](https://github.com/kodephp) 框架设计，同时兼容 **Laravel、Symfony、ThinkPHP 8、Webman、自研框架** 等主流 PHP 框架。

该组件提供：

- ✅ **静态代理** 实现服务容器绑定的动态调用
- ✅ 支持 **PHP 8.1+** 所有新特性（协变、逆变、枚举、只读类等）
- ✅ 完全无副作用，不影响协程、多线程、多进程模型
- ✅ 使用 **反射 + 缓存** 实现安全、快速的方法调用
- ✅ 类名与方法名**简洁、易记、不与 PHP 原生冲突**
- ✅ 支持跨框架调用，可作为通用市场组件发布

---

## 🧩 核心设计理念

| 特性 | 说明 |
|------|------|
| 🔐 **无全局状态污染** | 不使用 `static::$app` 全局赋值，通过 `Container` 接口注入 |
| ⚡ **性能优化** | 方法映射缓存 + 反射缓存，避免重复解析 |
| 🔄 **协变逆变支持** | 接口返回类型与参数支持 PHP 泛型风格协变（out）与逆变（in） |
| 🧱 **解耦设计** | 不依赖任何具体容器实现，仅依赖 `Psr\Container\ContainerInterface` |
| 🔧 **高度可配置** | 支持自定义容器实现、Facade 映射、方法缓存等 |
| 🔧 **高度可扩展** | 支持自定义 Facade 映射、方法缓存等 |

---

## 📚 安装方式

```bash
composer require kode/facade
```

---

## 🧠 核心类与 API

### 1. `Facade` 抽象类（核心）

> 所有 Facade 的基类，提供静态代理能力。

```php
namespace Kode\Facade;

use Psr\Container\ContainerInterface;

abstract class Facade
```{
    /**
     * 获取当前 Facade 对应的服务名（在容器中的 key）
     * @return string
     */
    protected static function id(): string;

    /**
     * 设置服务容器
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void;

    /**
     * 清除当前 Facade 的代理实例（用于测试或重置）
     * @return void
     */
    public static function clear(): void;

    /**
     * 动态静态调用转发
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args);
}
```

---

### 2. `FacadeProxy`（内部代理管理器）

> 内部使用，管理 Facade 到真实实例的映射。

```php
namespace Kode\Facade;

class FacadeProxy
```{
    private static array $instances = [];
    private static array $ids = [];

    /**
     * 绑定服务 ID 到 Facade
     * @template T
     * @param class-string<T> $facade
     * @param string $serviceId
     * @return void
     */
    public static function bind(string $facade, string $serviceId): void;

    /**
     * 获取 Facade 对应的实例
     * @param string $facade
     * @return object
     */
    public static function getInstance(string $facade): object;
}
```

---

## 🛠 使用示例

### Step 1：定义一个服务接口

```php
namespace App\Service;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}
```

### Step 2：实现服务

```php
namespace App\Service;

class SmtpMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        // 发送逻辑...
        return true;
    }
}
```

### Step 3：创建 Facade

```php
namespace App\Facade;
```php
use Kode\Facade\Facade;

/**
 * @method static bool send(string $to, string $subject, string $body)
 */
class Mail extends Facade
```{
    protected static function id(): string
    {
        return 'mailer'; // 对应容器中的服务 key
    }
}
```

### Step 4：在任意框架中使用

#### Laravel / Symfony / ThinkPHP / Webman 示例

```php
// 假设你已获取容器实例 $container（实现 Psr\Container\ContainerInterface）

use App\Facade\Mail;
use Kode\Facade\FacadeProxy;

// 绑定 Facade 到服务 ID
FacadeProxy::bind(\App\Facade\Mail::class, 'mailer');

// 设置容器
Mail::setContainer($container);

// 使用静态调用
Mail::send('user@example.com', 'Hello', 'Welcome!');
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

内部使用 `ReflectionMethod` + APCu/Array 缓存：

```php
$reflector = new ReflectionMethod($instance, $method);
$reflector->invokeArgs($instance, $args);
```

调用信息缓存于 `static` 数组，避免重复反射。

---


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


## 📌 最佳实践建议

1. **Facade 类名**：使用单数、动词或名词，如 `Mail`, `Cache`, `Log`, `DB`
2. **方法名**：保持与服务接口一致，避免动词重复（如 `getGet`）
3. **不覆盖 `__callStatic`**：避免破坏代理机制
4. **绑定在启动时完成**：在 `bootstrap.php` 或 `ServiceProvider` 中调用 `FacadeProxy::bind()`
5. **测试时使用 `clear()`**：避免测试间状态污染

---

## 📞 联系与贡献

- GitHub: [github.com/kodephp/facade](https://github.com/kodephp/facade)
- Issues: 欢迎提交 Bug 与 Feature Request
- PR: 开放贡献，需包含单元测试

---

> ✅ `kode/facade` —— **简单、安全、通用、高性能的 PHP Facade 解决方案**。  
> 为未来协程、多线程、多进程架构打下坚实基础。