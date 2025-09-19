<?php

declare(strict_types=1);

namespace Kode\Facade\Tests\Unit;

use Kode\Context\Context;
use Kode\Facade\ContextualFacadeManager;
use Kode\Facade\Facade;
use Kode\Facade\FacadeProxy;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * 测试服务类
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
 * 测试门面类
 */
class ContextualTestFacade extends Facade
{
    protected static function id(): string
    {
        return 'test.service';
    }
    
    public static function getValue(): string
    {
        return static::__callStatic('getValue', []);
    }
    
    public static function setValue(string $value): void
    {
        static::__callStatic('setValue', [$value]);
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
        // 创建模拟容器
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
        
        // 设置容器
        ContextualTestFacade::setContainer($this->container);
        FacadeProxy::bind(ContextualTestFacade::class, 'test.service');
        
        // 清除上下文
        Context::clear();
    }
    
    protected function tearDown(): void
    {
        // 清除上下文
        Context::clear();
        
        // 禁用上下文安全模式
        ContextualTestFacade::disableContextSafeMode();
    }
    
    /**
     * 测试普通模式下的门面功能
     */
    public function testNormalMode(): void
    {
        // 确保上下文安全模式未启用
        $this->assertFalse(ContextualTestFacade::isContextSafeMode());
        
        // 设置服务实例
        $service = new ContextualTestService('normal_mode');
        $this->container->set('test.service', $service);
        
        // 验证门面可以获取服务实例
        $this->assertEquals('normal_mode', ContextualTestFacade::getValue());
        
        // 修改服务实例的值
        $service->setValue('modified');
        $this->assertEquals('modified', ContextualTestFacade::getValue());
    }
    
    /**
     * 测试上下文安全模式下的门面功能
     */
    public function testContextSafeMode(): void
    {
        // 启用上下文安全模式
        ContextualTestFacade::enableContextSafeMode();
        $this->assertTrue(ContextualTestFacade::isContextSafeMode());
        
        // 设置服务实例
        $service = new ContextualTestService('context_safe');
        $this->container->set('test.service', $service);
        
        // 验证门面可以获取服务实例
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
        
        // 启用上下文安全模式
        ContextualTestFacade::enableContextSafeMode();
        
        // 设置服务实例
        $service = new ContextualTestService('main_context');
        $this->container->set('test.service', $service);
        
        // 在主上下文中验证值
        $this->assertEquals('main_context', ContextualTestFacade::getValue());
        
        // 创建一个fiber来模拟不同的上下文
        $fiberValue = null;
        $fiber = new \Fiber(function () use (&$fiberValue) {
            // 在fiber上下文中创建新的容器实例
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
            
            // 在fiber上下文中设置不同的服务实例
            $fiberService = new ContextualTestService('fiber_context');
            $fiberContainer->set('test.service', $fiberService);
            
            // 为Fiber设置容器
            ContextualTestFacade::setContainer($fiberContainer);
            
            // 确保在fiber中也启用上下文安全模式
            ContextualTestFacade::enableContextSafeMode();
            
            // 验证fiber上下文中获取的是fiber的值
            $fiberValue = ContextualTestFacade::getValue();
        });
        
        // 使用Context::run方法确保在Fiber中有正确的上下文
        \Kode\Context\Context::run(function () use ($fiber) {
            $fiber->start();
        });
        $this->assertEquals('fiber_context', $fiberValue);
        
        // 验证主上下文的值未被影响
        $this->assertEquals('main_context', ContextualTestFacade::getValue());
    }
    
    /**
     * 测试上下文安全模式下的clear功能
     */
    public function testContextSafeClear(): void
    {
        // 启用上下文安全模式
        ContextualTestFacade::enableContextSafeMode();
        
        // 设置服务实例
        $service = new ContextualTestService('to_be_cleared');
        $this->container->set('test.service', $service);
        
        // 验证服务可获取
        $this->assertEquals('to_be_cleared', ContextualTestFacade::getValue());
        
        // 清除实例
        ContextualTestFacade::clear();
        
        // 重新设置服务实例
        $newService = new ContextualTestService('after_clear');
        $this->container->set('test.service', $newService);
        
        // 验证获取到新的实例
        $this->assertEquals('after_clear', ContextualTestFacade::getValue());
    }
    
    /**
     * 测试上下文管理器的基本功能
     */
    public function testContextualFacadeManager(): void
    {
        // 设置容器
        ContextualFacadeManager::setContainer($this->container);
        
        // 设置服务实例
        $service = new ContextualTestService('manager_test');
        $this->container->set('test.service', $service);
        
        // 通过管理器获取实例
        $instance = ContextualFacadeManager::getInstance(ContextualTestFacade::class);
        $this->assertInstanceOf(ContextualTestService::class, $instance);
        $this->assertEquals('manager_test', $instance->getValue());
    }
}