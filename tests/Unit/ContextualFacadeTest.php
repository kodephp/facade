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
}
