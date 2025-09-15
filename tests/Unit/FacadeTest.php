<?php

declare(strict_types=1);

namespace Kode\Facade\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Kode\Facade\Facade;
use Kode\Facade\FacadeProxy;
use Kode\Facade\Exception\FacadeException;

// Test service implementation
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
}

class FacadeTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear all facade data before each test
        FacadeProxy::clearAll();
        TestFacade::clearAll();
    }

    public function testFacadeInstanceResolution(): void
    {
        // Create test instance
        $testInstance = new TestService();
        
        // Create a mock container
        $container = $this->createMock(ContainerInterface::class);
        
        // Configure the mock BEFORE setting container and binding facade
        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);
            
        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);
        
        // Set the container
        TestFacade::setContainer($container);
        
        // Bind a facade
        FacadeProxy::bind(TestFacade::class, 'test-service');
        
        // Get the instance
        $instance = TestFacade::getInstance();
        
        // Assert the instance is resolved correctly
        $this->assertIsObject($instance);
        $this->assertEquals('test-result', $instance->testMethod());
    }

    public function testFacadeStaticCall(): void
    {
        // Create test instance
        $testInstance = new TestService();
        
        // Create a mock container
        $container = $this->createMock(ContainerInterface::class);
        
        // Configure the mock BEFORE setting container and binding facade
        $container->expects($this->once())
            ->method('has')
            ->with('test-service')
            ->willReturn(true);
            
        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);
        
        // Set the container
        TestFacade::setContainer($container);
        
        // Bind a facade
        FacadeProxy::bind(TestFacade::class, 'test-service');
        
        // Call the method statically
        $result = TestFacade::getValue();
        
        // Assert the result
        $this->assertEquals('test-value', $result);
    }

    public function testFacadeClear(): void
    {
        // Create test instance
        $testInstance = new TestService();
        
        // Create a mock container
        $container = $this->createMock(ContainerInterface::class);
        
        // Configure the mock - expect two calls because clear() will clear the instance cache
        $container->expects($this->exactly(2))
            ->method('has')
            ->with('test-service')
            ->willReturn(true);
            
        $container->expects($this->exactly(2))
            ->method('get')
            ->with('test-service')
            ->willReturn($testInstance);
        
        // Set the container
        TestFacade::setContainer($container);
        
        // Bind a facade
        FacadeProxy::bind(TestFacade::class, 'test-service');
        
        // Call the method to resolve the instance
        $result1 = TestFacade::getValue();
        
        // Clear the facade
        TestFacade::clear();
        
        // Call again - should resolve a new instance from the container
        $result2 = TestFacade::getValue();
        
        // Assert the results
        $this->assertEquals('test-value', $result1);
        $this->assertEquals('test-value', $result2);
    }
}

// Test facade implementation
class TestFacade extends Facade
{
    protected static function id(): string
    {
        return 'test-service';
    }
}