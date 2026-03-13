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
