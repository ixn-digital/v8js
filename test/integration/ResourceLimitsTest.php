<?php
/**
 * Test memory and time limits
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ResourceLimitsTest extends PHPIntegrationTest
{
    /**
     * Test setting memory limit
     */
    public static function testSetMemoryLimit()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set a small memory limit (1MB)
        $v8->setMemoryLimit(1024 * 1024);
        
        // This should work fine
        $result = $v8->executeString('var x = [1, 2, 3]; x.length;');
        self::assertEquals(3, $result);
    }
    
    /**
     * Test memory limit exceeded
     */
    public static function testMemoryLimitExceeded()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set very small memory limit
        $v8->setMemoryLimit(100 * 1024); // 100KB
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            // Try to allocate large array
            $v8->executeString('
                var huge = [];
                for (var i = 0; i < 1000000; i++) {
                    huge.push(new Array(1000));
                }
            ');
        });
    }
    
    /**
     * Test setting time limit
     */
    public static function testSetTimeLimit()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set 1 second time limit
        $v8->setTimeLimit(1000);
        
        // Quick operation should work
        $result = $v8->executeString('2 + 2');
        self::assertEquals(4, $result);
    }
    
    /**
     * Test time limit exceeded
     */
    public static function testTimeLimitExceeded()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set very short time limit (100ms)
        $v8->setTimeLimit(100);
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            // Infinite loop
            $v8->executeString('while(true) {}');
        });
    }
    
    /**
     * Test setting average object size
     */
    public static function testSetAverageObjectSize()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set average object size estimate
        $v8->setAverageObjectSize(1024);
        
        // Should still work normally
        $result = $v8->executeString('var obj = {a: 1, b: 2}; obj.a + obj.b;');
        self::assertEquals(3, $result);
    }
    
    /**
     * Test time limit doesn't affect subsequent calls
     */
    public static function testTimeLimitReset()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->setTimeLimit(100);
        
        try {
            $v8->executeString('while(true) {}');
        } catch (V8JsException $e) {
            // Expected timeout
        }
        
        // After timeout, instance should still work
        $result = $v8->executeString('2 + 2');
        self::assertEquals(4, $result);
    }
    
    /**
     * Test memory limit with multiple executions
     */
    public static function testMemoryLimitMultipleExecutions()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->setMemoryLimit(5 * 1024 * 1024); // 5MB
        
        // Multiple small allocations should work
        for ($i = 0; $i < 10; $i++) {
            $result = $v8->executeString('var arr = [1, 2, 3]; arr.length;');
            self::assertEquals(3, $result);
        }
    }
    
    /**
     * Test time limit with callbacks
     */
    public static function testTimeLimitWithCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->setTimeLimit(200);
        
        $v8->slowFunction = function() {
            usleep(50000); // 50ms
            return 'done';
        };
        
        // Should complete within time limit
        $result = $v8->executeString('PHP.slowFunction()');
        self::assertEquals('done', $result);
    }
    
    /**
     * Test removing time limit
     */
    public static function testRemoveTimeLimit()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set time limit
        $v8->setTimeLimit(100);
        
        // Remove it (set to 0)
        $v8->setTimeLimit(0);
        
        // Longer operation should work
        $result = $v8->executeString('
            var sum = 0;
            for (var i = 0; i < 1000000; i++) {
                sum += i;
            }
            sum > 0;
        ');
        
        self::assertTrue($result);
    }
    
    /**
     * Test different time limits
     */
    public static function testDifferentTimeLimits()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Short limit
        $v8->setTimeLimit(50);
        try {
            $v8->executeString('var i = 0; while(true) { i++; }');
            self::assertTrue(false, 'Should have timed out');
        } catch (V8JsException $e) {
            // Expected
        }
        
        // Longer limit
        $v8->setTimeLimit(1000);
        $result = $v8->executeString('
            var sum = 0;
            for (var i = 0; i < 100000; i++) {
                sum += i;
            }
            sum;
        ');
        
        self::assertTrue($result > 0);
    }
}
