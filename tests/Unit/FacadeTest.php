<?php

declare(strict_types=1);

namespace Kode\Facade\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Kode\Facade\Facade;
use Kode\Facade\FacadeProxy;
use Kode\Facade\Exception\FacadeException;

/**
 * 测试服务类
 */
class TestService
{
    public function testMethod(): string
    {
        return 'test-result';
    }

    public function getValue(): string
    {
        return 'test-value';
    }

    protected function protectedValue(): string
    {
        return 'hidden';
    }
}

/**
 * 门面测试类
 */
class FacadeTest extends TestCase
{
    protected function setUp(): void
    {
        FacadeProxy::reset();
        TestFacade::clearAll();
        TestFacade::disableContextSafeMode();
    }

    protected function tearDown(): void
    {
        FacadeProxy::reset();
        TestFacade::clearAll();
        TestFacade::disableContextSafeMode();
    }

    /**
     * 测试门面实例解析
     */
    public function testFacadeInstanceResolution(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $instance = TestFacade::getInstance();

        $this->assertIsObject($instance);
        $this->assertEquals('test-result', $instance->testMethod());
    }

    /**
     * 测试门面静态调用
     */
    public function testFacadeStaticCall(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $result = TestFacade::getValue();

        $this->assertEquals('test-value', $result);
    }

    /**
     * 测试门面清除
     */
    public function testFacadeClear(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->exactly(2))
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->exactly(2))
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $result1 = TestFacade::getValue();
        TestFacade::clear();
        $result2 = TestFacade::getValue();

        $this->assertEquals('test-value', $result1);
        $this->assertEquals('test-value', $result2);
    }

    /**
     * 测试门面代理缓存解析实例
     */
    public function testFacadeProxyCachesResolvedInstance(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $first = TestFacade::getInstance();
        $second = TestFacade::getInstance();

        $this->assertSame($first, $second);
    }

    /**
     * 测试方法检查仅检查公共方法
     */
    public function testHasMethodChecksPublicOnly(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->assertTrue(TestFacade::hasMethod('getValue'));
        $this->assertFalse(TestFacade::hasMethod('protectedValue'));
    }

    /**
     * 测试门面使用闭包模拟
     */
    public function testFacadeCanUseClosureMock(): void
    {
        FacadeProxy::mock(TestFacade::class, static fn () => new class {
            public function getValue(): string
            {
                return 'mocked';
            }
        });

        $this->assertSame('mocked', TestFacade::getValue());
    }

    /**
     * 测试门面使用对象模拟
     */
    public function testFacadeCanUseObjectMock(): void
    {
        $mock = new class {
            public function getValue(): string
            {
                return 'object-mocked';
            }
        };

        TestFacade::mock($mock);

        $this->assertSame('object-mocked', TestFacade::getValue());
    }

    /**
     * 测试门面绑定检查
     */
    public function testFacadeBindingCheck(): void
    {
        $this->assertFalse(FacadeProxy::isBound(TestFacade::class));

        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->assertTrue(FacadeProxy::isBound(TestFacade::class));
    }

    /**
     * 测试获取服务ID
     */
    public function testGetServiceId(): void
    {
        $this->assertEquals('test-service', TestFacade::getServiceId());

        FacadeProxy::bind(TestFacade::class, 'test-service');
        $this->assertEquals('test-service', FacadeProxy::getServiceId(TestFacade::class));
    }

    /**
     * 测试批量绑定
     */
    public function testBindMany(): void
    {
        FacadeProxy::bindMany([
            TestFacade::class => 'test-service',
            AnotherFacade::class => 'another-service',
        ]);

        $bindings = FacadeProxy::getBindings();

        $this->assertArrayHasKey(TestFacade::class, $bindings);
        $this->assertArrayHasKey(AnotherFacade::class, $bindings);
        $this->assertEquals('test-service', $bindings[TestFacade::class]);
        $this->assertEquals('another-service', $bindings[AnotherFacade::class]);
    }

    /**
     * 测试解除绑定
     */
    public function testUnbind(): void
    {
        FacadeProxy::bind(TestFacade::class, 'test-service');
        $this->assertTrue(FacadeProxy::isBound(TestFacade::class));

        FacadeProxy::unbind(TestFacade::class);
        $this->assertFalse(FacadeProxy::isBound(TestFacade::class));
    }

    /**
     * 测试调用方法
     */
    public function testCallMethod(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $result = TestFacade::call('getValue');

        $this->assertEquals('test-value', $result);
    }

    /**
     * 测试未知门面异常
     */
    public function testUnknownFacadeException(): void
    {
        $this->expectException(FacadeException::class);

        FacadeProxy::getInstance('UnknownFacade');
    }

    /**
     * 测试容器未设置异常
     */
    public function testContainerNotSetException(): void
    {
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->expectException(FacadeException::class);

        FacadeProxy::getInstance(TestFacade::class);
    }

    /**
     * 测试解析状态检查
     */
    public function testIsResolved(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->assertFalse(TestFacade::isResolved());

        TestFacade::getValue();

        $this->assertTrue(TestFacade::isResolved());
    }

    /**
     * 测试模拟状态检查
     */
    public function testIsMocked(): void
    {
        $this->assertFalse(FacadeProxy::isMocked(TestFacade::class));

        FacadeProxy::mock(TestFacade::class, new \stdClass());

        $this->assertTrue(FacadeProxy::isMocked(TestFacade::class));
    }

    /**
     * 测试未显式 bind 时仍能通过门面 id() 解析服务
     *
     * 回归测试：服务ID解析应统一以门面 id() 为回退来源，
     * 使 bind() 成为可选的运行时覆盖手段。
     */
    public function testResolvesViaIdWithoutExplicitBinding(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->with('test-service')->willReturn(true);
        $container->method('get')->with('test-service')->willReturn($testInstance);

        TestFacade::setContainer($container);
        // 注意：此处未调用 FacadeProxy::bind()

        $this->assertEquals('test-value', TestFacade::getValue());
        $this->assertTrue(TestFacade::isResolved());
    }

    /**
     * 测试 swap 运行时热替换实例
     *
     * swap 写入的是正常的实例缓存，不影响 isMocked() 判定，
     * 调用 clear() 即可回退到容器解析。
     */
    public function testSwapReplacesInstance(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->assertSame('test-value', TestFacade::getValue());

        $swapped = new class {
            public function getValue(): string
            {
                return 'swapped';
            }
        };
        FacadeProxy::swap(TestFacade::class, $swapped);

        $this->assertSame('swapped', TestFacade::getValue());
        $this->assertFalse(FacadeProxy::isMocked(TestFacade::class));

        TestFacade::clear();
        $this->assertSame('test-value', TestFacade::getValue());
    }

    /**
     * 测试 unmock 撤销模拟并恢复容器解析
     */
    public function testUnmockRestoresContainerResolution(): void
    {
        $mocked = new class {
            public function getValue(): string
            {
                return 'mocked';
            }
        };
        TestFacade::mock($mocked);

        $this->assertSame('mocked', TestFacade::getValue());

        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        TestFacade::unmock();

        $this->assertSame('test-value', TestFacade::getValue());
    }

    /**
     * 测试以实例方式使用门面（__call 转发）
     */
    public function testInstanceStyleCall(): void
    {
        $testInstance = new TestService();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($testInstance);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $facade = new TestFacade();

        $this->assertSame('test-value', $facade->getValue());
    }

    /**
     * 测试业务异常透传（不被包装成 FacadeException）
     *
     * 门面是透明代理：服务方法自身抛出的业务异常原样向上传播。
     */
    public function testBusinessExceptionIsNotWrapped(): void
    {
        $throwing = new class {
            public function boom(): void
            {
                throw new \RuntimeException('boom-business');
            }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($throwing);

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'test-service');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom-business');

        TestFacade::boom();
    }

    /**
     * 测试每门面上下文安全模式相互隔离
     *
     * 一个门面启用上下文安全模式，不应影响其它门面的判定（回归：
     * 曾因共享 static 属性导致全局污染）。
     */
    public function testPerFacadeContextSafeModeIsolation(): void
    {
        TestFacade::enableContextSafeMode();
        $this->assertTrue(TestFacade::isContextSafeMode());
        $this->assertFalse(AnotherFacade::isContextSafeMode());

        AnotherFacade::enableContextSafeMode();
        $this->assertTrue(AnotherFacade::isContextSafeMode());

        TestFacade::disableContextSafeMode();
        $this->assertFalse(TestFacade::isContextSafeMode());
        $this->assertTrue(AnotherFacade::isContextSafeMode());
    }

    /**
     * 测试绑定变更后已缓存实例自动失效
     *
     * 绑定发生改变时，旧实例缓存必须失效并重新从容器解析，
     * 否则会返回过期实例（历史缺陷）。
     */
    public function testInstanceCacheInvalidatedOnRebind(): void
    {
        $serviceA = new TestService();
        $serviceB = new class {
            public function getValue(): string
            {
                return 'service-b';
            }
        };

        $container = new class ($serviceA, $serviceB) implements ContainerInterface {
            public function __construct(
                private object $a,
                private object $b
            ) {
            }

            public function get(string $id): object
            {
                return match ($id) {
                    'service-a' => $this->a,
                    'service-b' => $this->b,
                    default => throw new \RuntimeException("unknown: $id"),
                };
            }

            public function has(string $id): bool
            {
                return $id === 'service-a' || $id === 'service-b';
            }
        };

        TestFacade::setContainer($container);
        FacadeProxy::bind(TestFacade::class, 'service-a');

        $this->assertSame('test-value', TestFacade::getValue());

        // 重新绑定到另一个服务ID，旧实例缓存必须失效
        FacadeProxy::bind(TestFacade::class, 'service-b');

        $this->assertSame('service-b', TestFacade::getValue());
    }
}

/**
 * 测试门面实现
 */
class TestFacade extends Facade
{
    protected static function id(): string
    {
        return 'test-service';
    }
}

/**
 * 另一个测试门面
 */
class AnotherFacade extends Facade
{
    protected static function id(): string
    {
        return 'another-service';
    }
}
