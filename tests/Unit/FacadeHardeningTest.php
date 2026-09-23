<?php

declare(strict_types=1);

namespace Kode\Facade\Tests\Unit;

use Kode\Context\Context;
use Kode\Facade\ContextualFacadeManager;
use Kode\Facade\Exception\FacadeException;
use Kode\Facade\Facade;
use Kode\Facade\FacadeProxy;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WeakReference;

/**
 * 固定服务注册表容器
 */
class HardeningContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function remove(string $id): void
    {
        unset($this->services[$id]);
    }

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

/**
 * 可被魔术方法复写状态的服务替身
 */
class MagicService
{
    public string $role = 'plain';

    public function __construct(string $role = 'plain')
    {
        $this->role = $role;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function __clone(): void
    {
        $this->role = 'CLONED';
    }

    public function __invoke(): string
    {
        return 'INVOKED-as-' . $this->role;
    }
}

/**
 * 带 __call 兜底的服务替身
 */
class MagicCallService
{
    public function __call(string $method, array $args): string
    {
        return "via-__call:{$method}";
    }
}

/**
 * 与门面基类重名的服务方法替身
 */
class ClearableService
{
    public function clear(): string
    {
        return 'SERVICE-CLEAR-RAN';
    }

    public function getValue(): string
    {
        return 'real';
    }
}

/**
 * 加固回归用门面
 */
class HardeningFacade extends Facade
{
    protected static function id(): string
    {
        return 'hardening.service';
    }
}

/**
 * 门面代理层加固回归测试
 *
 * 覆盖 v3.3.0 的四类问题：魔术方法转发、可调用缓存钉住对象、
 * 换容器/清实例时的双侧失效、以及 hasMethod 的可达性口径。
 */
class FacadeHardeningTest extends TestCase
{
    private HardeningContainer $container;

    protected function setUp(): void
    {
        $this->container = new HardeningContainer();

        FacadeProxy::reset();
        ContextualFacadeManager::reset();
        Facade::resetState();
        Context::clear();
    }

    protected function tearDown(): void
    {
        FacadeProxy::reset();
        ContextualFacadeManager::reset();
        Facade::resetState();
        Context::clear();
    }

    private function bind(object $service): void
    {
        $this->container->set('hardening.service', $service);
        HardeningFacade::setContainer($this->container);
        FacadeProxy::bind(HardeningFacade::class, 'hardening.service');
    }

    /**
     * 魔术方法名一律不转发
     *
     * Closure::fromCallable([$instance, '__construct']) 是合法可调用，
     * 放行等于让调用方在代理之外带任意参数重跑服务构造函数、改写内部状态。
     */
    public function testMagicMethodsAreNeverForwarded(): void
    {
        $this->bind(new MagicService());

        foreach (['__construct', '__clone', '__invoke', '__serialize', '__wakeup'] as $magic) {
            $this->assertFalse(HardeningFacade::hasMethod($magic), "hasMethod 不应承认魔术名 {$magic}");

            try {
                HardeningFacade::call($magic, ['superadmin']);
                $this->fail("{$magic} 应被门面拦下");
            } catch (FacadeException $e) {
                $this->assertSame(FacadeException::CODE_MAGIC_METHOD, $e->getCode());
            }
        }

        $this->assertSame('plain', HardeningFacade::role(), '被拦下的魔术调用不得改写服务状态');
    }

    /**
     * 公开 API 清缓存后，服务实例必须可回收
     *
     * 可调用缓存按「门面 + 方法」强引用实例；不随 clear() 剪枝的话，
     * 换过一轮驱动的老对象会被永久钉住（常驻 worker 里即稳定内存地板）。
     */
    public function testPublicClearReleasesPinnedInstance(): void
    {
        $first = new MagicService('first');
        $this->bind($first);

        $this->assertSame('first', HardeningFacade::role());
        $ref = WeakReference::create($first);

        HardeningFacade::clear();
        unset($first);
        $this->container->remove('hardening.service');

        $this->assertNull($ref->get(), 'clear() 之后旧服务实例应可被回收');

        $second = new MagicService('second');
        $this->bind($second);
        $this->assertSame('second', HardeningFacade::role());
    }

    /**
     * 换容器在上下文安全模式下同样要作废缓存
     *
     * FacadeProxy::setContainer() 早就有这条语义；上下文侧漏掉的话，
     * 换容器后当前执行单元仍返回旧容器创建的对象（跨容器脏读）。
     */
    public function testSetContainerInvalidatesContextCache(): void
    {
        HardeningFacade::enableContextSafeMode();
        $this->bind(new MagicService('from-A'));
        $this->assertSame('from-A', HardeningFacade::role());

        $other = new class implements ContainerInterface {
            public function get(string $id)
            {
                return new MagicService('from-B');
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        HardeningFacade::setContainer($other);
        $this->assertSame('from-B', HardeningFacade::role(), '换容器后必须重新解析');
    }

    /**
     * swap 双写 → clear 必须双侧清干净
     *
     * swap() 在非上下文模式下写 FacadeProxy，上下文模式下双写；
     * clear() 只清单边的话，替换实例会在切回普通模式后复活。
     */
    public function testClearLeavesNoSwappedResidue(): void
    {
        HardeningFacade::enableContextSafeMode();
        $this->bind(new MagicService('real'));

        HardeningFacade::swap(new MagicService('SWAPPED'));
        $this->assertSame('SWAPPED', HardeningFacade::role());

        HardeningFacade::clear();
        $this->assertSame('real', HardeningFacade::role(), 'clear 后应回退到容器解析');
        $this->assertSame([], FacadeProxy::getInstances(), '代理侧不得残留 swap 写入的实例');

        HardeningFacade::disableContextSafeMode();
        $this->assertSame('real', HardeningFacade::role(), '切回普通模式后不得复活替换实例');
    }

    /**
     * 上下文路径的服务ID校验与普通路径对齐
     *
     * 此前只判 class_exists + method_exists，任意带 getServiceId() 的无关类
     * 也能被当作门面解析容器服务。
     */
    public function testContextPathRejectsNonFacadeClass(): void
    {
        $this->bind(new MagicService('real'));

        $this->expectException(FacadeException::class);
        $this->expectExceptionCode(FacadeException::CODE_UNKNOWN_FACADE);

        ContextualFacadeManager::getInstance(NotAFacade::class);
    }

    /**
     * isResolved 在上下文模式下要承认 mock
     *
     * FacadeProxy::hasInstance() 把 mock 算作已解析；上下文侧不认的话，
     * 同一句 isResolved() 会随模式开关给出相反答案。
     */
    public function testHasInstanceHonorsMockInContextMode(): void
    {
        HardeningFacade::enableContextSafeMode();
        $this->bind(new MagicService('real'));

        $this->assertFalse(HardeningFacade::isResolved());

        HardeningFacade::mock(new MagicService('mocked'));
        $this->assertTrue(HardeningFacade::isResolved(), 'mock 之后应视为已解析');
        $this->assertSame('mocked', HardeningFacade::role());

        HardeningFacade::unmock();
        $this->assertFalse(HardeningFacade::isResolved(), 'unmock 后回到未解析');
    }

    /**
     * hasMethod 报告的是「静态调用能否走到服务」
     *
     * 基类自身的 clear()/hasMethod() 在 PHP 里恒优先于 __callStatic，
     * 服务上的同名方法只能走 call()；谎报可达比报错更难查。
     */
    public function testHasMethodReflectsForwardingNotJustInstance(): void
    {
        $this->bind(new ClearableService());

        $this->assertTrue(HardeningFacade::hasMethod('getValue'));
        $this->assertFalse(HardeningFacade::hasMethod('clear'), 'clear 被基类占用，静态调用不会转发到服务');
        $this->assertSame('SERVICE-CLEAR-RAN', HardeningFacade::call('clear'), 'call() 仍可达服务方法');
    }

    /**
     * __call 兜底的服务方法不再被误报为不存在
     */
    public function testHasMethodHonorsMagicCall(): void
    {
        $this->bind(new MagicCallService());

        $this->assertTrue(HardeningFacade::hasMethod('anythingElse'), '服务有 __call 兜底即视为可达');
        $this->assertSame('via-__call:anythingElse', HardeningFacade::anythingElse());
    }
}

/**
 * 不是门面的普通类（用于校验解析入口）
 */
class NotAFacade
{
    public static function getServiceId(): string
    {
        return 'hardening.service';
    }
}
