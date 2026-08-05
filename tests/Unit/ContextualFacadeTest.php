<?php

declare(strict_types=1);

namespace Kode\Facade\Tests\Unit;

use Kode\Context\Context;
use Kode\Facade\ContextualFacadeManager;
use Kode\Facade\Facade;
use Kode\Facade\FacadeProxy;
use Kode\Facade\Exception\FacadeException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * 上下文测试服务类
 */
class ContextualTestService
{
    private string $value;

    public function __construct(string $value = 'default')
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

/**
 * 上下文测试门面类
 */
class ContextualTestFacade extends Facade
{
    protected static function id(): string
    {
        return 'test.service';
    }
}

/**
 * 上下文安全门面测试
 */
class ContextualFacadeTest extends TestCase
{
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = new class implements ContainerInterface {
            private array $services = [];

            public function set(string $id, object $service): void
            {
                $this->services[$id] = $service;
            }

            public function get(string $id)
            {
                return $this->services[$id] ?? throw new \Exception("Service not found: $id");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };

        FacadeProxy::reset();
        ContextualFacadeManager::reset();
        Context::clear();
        ContextualTestFacade::disableContextSafeMode();
    }

    protected function tearDown(): void
    {
        FacadeProxy::reset();
        ContextualFacadeManager::reset();
        Context::clear();
        ContextualTestFacade::disableContextSafeMode();
    }

    /**
     * 测试普通模式下的门面功能
     */
    public function testNormalMode(): void
    {
        $this->assertFalse(ContextualTestFacade::isContextSafeMode());

        $service = new ContextualTestService('normal_mode');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertEquals('normal_mode', ContextualTestFacade::getValue());

        $service->setValue('modified');
        $this->assertEquals('modified', ContextualTestFacade::getValue());
    }

    /**
     * 测试上下文安全模式下的门面功能
     */
    public function testContextSafeMode(): void
    {
        ContextualTestFacade::enableContextSafeMode();
        $this->assertTrue(ContextualTestFacade::isContextSafeMode());

        $service = new ContextualTestService('context_safe');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertEquals('context_safe', ContextualTestFacade::getValue());
    }

    /**
     * 测试在不同上下文中的隔离性
     */
    public function testContextIsolation(): void
    {
        if (!class_exists(\Fiber::class)) {
            $this->markTestSkipped('Fiber not available');
        }

        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('main_context');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertEquals('main_context', ContextualTestFacade::getValue());

        $fiberValue = null;
        $fiber = new \Fiber(function () use (&$fiberValue) {
            $fiberContainer = new class implements ContainerInterface {
                private array $services = [];

                public function set(string $id, object $service): void
                {
                    $this->services[$id] = $service;
                }

                public function get(string $id)
                {
                    return $this->services[$id] ?? throw new \Exception("Service not found: $id");
                }

                public function has(string $id): bool
                {
                    return isset($this->services[$id]);
                }
            };

            $fiberService = new ContextualTestService('fiber_context');
            $fiberContainer->set('test.service', $fiberService);

            ContextualTestFacade::setContainer($fiberContainer);
            ContextualTestFacade::enableContextSafeMode();

            $fiberValue = ContextualTestFacade::getValue();
        });

        Context::run(function () use ($fiber) {
            $fiber->start();
        });

        $this->assertEquals('fiber_context', $fiberValue);
        $this->assertEquals('main_context', ContextualTestFacade::getValue());
    }

    /**
     * 测试上下文安全模式下的清除功能
     */
    public function testContextSafeClear(): void
    {
        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('to_be_cleared');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertEquals('to_be_cleared', ContextualTestFacade::getValue());

        ContextualTestFacade::clear();

        $newService = new ContextualTestService('after_clear');
        $this->container->set('test.service', $newService);

        $this->assertEquals('after_clear', ContextualTestFacade::getValue());
    }

    /**
     * 测试上下文管理器的基本功能
     */
    public function testContextualFacadeManager(): void
    {
        ContextualFacadeManager::setContainer($this->container);

        $service = new ContextualTestService('manager_test');
        $this->container->set('test.service', $service);

        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $instance = ContextualFacadeManager::getInstance(ContextualTestFacade::class);
        $this->assertInstanceOf(ContextualTestService::class, $instance);
        $this->assertEquals('manager_test', $instance->getValue());
    }

    /**
     * 测试上下文管理器服务不存在时抛出异常
     */
    public function testContextualFacadeManagerThrowsWhenServiceMissing(): void
    {
        ContextualFacadeManager::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->expectException(FacadeException::class);

        ContextualFacadeManager::getInstance(ContextualTestFacade::class);
    }

    /**
     * 测试上下文管理器服务不是对象时抛出异常
     */
    public function testContextualFacadeManagerThrowsWhenServiceNotObject(): void
    {
        $invalidContainer = new class implements ContainerInterface {
            public function get(string $id)
            {
                return 'invalid';
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        ContextualFacadeManager::setContainer($invalidContainer);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');
        Context::clear();

        $this->expectException(FacadeException::class);

        ContextualFacadeManager::getInstance(ContextualTestFacade::class);
    }

    /**
     * 测试上下文管理器检查实例存在
     */
    public function testContextualFacadeManagerHasInstance(): void
    {
        ContextualFacadeManager::setContainer($this->container);

        $service = new ContextualTestService('has_test');
        $this->container->set('test.service', $service);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertFalse(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));

        ContextualFacadeManager::getInstance(ContextualTestFacade::class);

        $this->assertTrue(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));
    }

    /**
     * 测试上下文管理器清除实例
     */
    public function testContextualFacadeManagerClearInstance(): void
    {
        ContextualFacadeManager::setContainer($this->container);

        $service = new ContextualTestService('clear_test');
        $this->container->set('test.service', $service);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        ContextualFacadeManager::getInstance(ContextualTestFacade::class);
        $this->assertTrue(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));

        ContextualFacadeManager::clearInstance(ContextualTestFacade::class);
        $this->assertFalse(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));
    }

    /**
     * 测试上下文安全模式下的解析状态检查
     */
    public function testIsResolvedInContextSafeMode(): void
    {
        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('resolved_test');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        $this->assertFalse(ContextualTestFacade::isResolved());

        ContextualTestFacade::getValue();

        $this->assertTrue(ContextualTestFacade::isResolved());
    }

    /**
     * 测试上下文安全模式下的清除所有实例
     */
    public function testClearAllInContextSafeMode(): void
    {
        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('clear_all_test');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        ContextualTestFacade::getValue();
        $this->assertTrue(ContextualTestFacade::isResolved());

        ContextualTestFacade::clearAll();
        $this->assertFalse(ContextualTestFacade::isResolved());
    }

    /**
     * 测试上下文安全模式下 clearInstances 真正清除所有门面实例
     *
     * 回归测试：ContextualFacadeManager::clearInstances() 必须按前缀删除
     * 实际写入上下文的实例键，而不能删除一个从未写入的键。
     */
    public function testContextSafeClearInstancesClearsAll(): void
    {
        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('bulk_clear');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        ContextualTestFacade::getValue();
        $this->assertTrue(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));

        ContextualFacadeManager::clearInstances();

        $this->assertFalse(ContextualFacadeManager::hasInstance(ContextualTestFacade::class));
    }

    /**
     * 测试上下文安全模式下 clearAll 不会误清无关上下文数据
     *
     * 回归测试：clearAll() 在上下文安全模式下应仅清除门面实例，
     * 而不能调用 Context::clear() 清掉整个上下文中的其它数据。
     */
    public function testContextSafeClearAllKeepsUnrelatedContext(): void
    {
        ContextualTestFacade::enableContextSafeMode();

        $service = new ContextualTestService('keep_others');
        $this->container->set('test.service', $service);

        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');

        // 在上下文中写入与门面无关的数据
        Context::set('unrelated_context_key', 'should-stay');

        ContextualTestFacade::getValue();
        $this->assertTrue(ContextualTestFacade::isResolved());

        ContextualTestFacade::clearAll();

        $this->assertFalse(ContextualTestFacade::isResolved());
        $this->assertSame('should-stay', Context::get('unrelated_context_key'));
    }

    /**
     * 测试解析失败时（服务缺失）不残留失败状态
     *
     * getOrSet 语义保证：工厂抛异常时不会写入任何键，
     * 因此失败后 hasInstance 必须仍为 false，下次调用会重新解析。
     */
    public function testGetOrSetDoesNotCacheFailedResolution(): void
    {
        ContextualFacadeManager::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'missing.service');

        $thrown = false;
        try {
            ContextualFacadeManager::getInstance(ContextualTestFacade::class);
        } catch (FacadeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, '服务缺失应抛出 FacadeException');
        $this->assertFalse(
            ContextualFacadeManager::hasInstance(ContextualTestFacade::class),
            '解析失败后不应残留失败状态的缓存键'
        );
    }

    /**
     * 测试运行时改绑后 clearInstance 按门面前缀清除所有服务键
     *
     * 每「门面 + 服务ID」使用独立键；改绑后旧键必须也能被一次性清除，
     * 否则会出现"已切换驱动但旧实例仍驻留"的脏数据。
     */
    public function testClearInstanceClearsReboundServiceKeys(): void
    {
        ContextualFacadeManager::setContainer($this->container);

        $a = new ContextualTestService('svc-a');
        $b = new ContextualTestService('svc-b');
        $this->container->set('service-a', $a);
        $this->container->set('service-b', $b);

        FacadeProxy::bind(ContextualTestFacade::class, 'service-a');
        $this->assertSame('svc-a', ContextualFacadeManager::getInstance(ContextualTestFacade::class)->getValue());

        FacadeProxy::bind(ContextualTestFacade::class, 'service-b');
        $this->assertSame('svc-b', ContextualFacadeManager::getInstance(ContextualTestFacade::class)->getValue());

        $prefix = '__kode_facade_instances.' . ContextualTestFacade::class . '.';
        $keysBefore = array_filter(
            Context::keys(),
            static fn (string $k): bool => str_starts_with($k, $prefix)
        );
        $this->assertCount(2, $keysBefore, '改绑后上下文中应存在两个独立的服务键');

        ContextualFacadeManager::clearInstance(ContextualTestFacade::class);

        $keysAfter = array_filter(
            Context::keys(),
            static fn (string $k): bool => str_starts_with($k, $prefix)
        );
        $this->assertEmpty($keysAfter, 'clearInstance 应清除该门面在改绑前产生的所有服务键');
    }
}
