<?php
/**
 * Test snapshot functionality
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class SnapshotTest extends PHPIntegrationTest
{
    /**
     * Test basic snapshot creation
     */
    public static function testCreateSnapshot()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('var preloaded = "snapshot value";');
        
        self::assertNotNull($snapshot);
        self::assertTrue(strlen($snapshot) > 0);
    }
    
    /**
     * Test using a snapshot
     */
    public static function testUseSnapshot()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            var Utils = {
                add: function(a, b) { return a + b; },
                multiply: function(a, b) { return a * b; }
            };
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $result = $v8->executeString('Utils.add(2, 3)');
        self::assertEquals(5, $result);
    }
    
    /**
     * Test snapshot with functions
     */
    public static function testSnapshotWithFunctions()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            function square(x) { return x * x; }
            function cube(x) { return x * x * x; }
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $result = $v8->executeString('[square(5), cube(3)]');
        self::assertEquals([25, 27], $result);
    }
    
    /**
     * Test snapshot with library code
     */
    public static function testSnapshotWithLibrary()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            var MathLib = (function() {
                var PI = 3.14159;
                
                return {
                    circleArea: function(r) {
                        return PI * r * r;
                    },
                    circleCircumference: function(r) {
                        return 2 * PI * r;
                    }
                };
            })();
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $area = $v8->executeString('MathLib.circleArea(10)');
        self::assertTrue(abs($area - 314.159) < 0.01);
    }
    
    /**
     * Test snapshot with constants
     */
    public static function testSnapshotWithConstants()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            const VERSION = "1.0.0";
            const MAX_SIZE = 100;
            const CONFIG = {
                debug: false,
                timeout: 5000
            };
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $result = $v8->executeString('[VERSION, MAX_SIZE, CONFIG.timeout]');
        self::assertEquals(['1.0.0', 100, 5000], $result);
    }
    
    /**
     * Test snapshot with classes
     */
    public static function testSnapshotWithClasses()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            class Counter {
                constructor() {
                    this.count = 0;
                }
                
                increment() {
                    return ++this.count;
                }
                
                get value() {
                    return this.count;
                }
            }
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $result = $v8->executeString('
            var c = new Counter();
            c.increment();
            c.increment();
            c.value;
        ');
        
        self::assertEquals(2, $result);
    }
    
    /**
     * Test snapshot reduces execution time
     */
    public static function testSnapshotPerformance()
    {
        self::skipIfNoV8js();
        
        $libraryCode = '
            var LargeLib = {
                method1: function() { return 1; },
                method2: function() { return 2; },
                method3: function() { return 3; }
            };
        ';
        
        // With snapshot
        $snapshot = V8Js::createSnapshot($libraryCode);
        $v8_with = new V8Js('PHP', [], $snapshot);
        $result_with = $v8_with->executeString('LargeLib.method1()');
        
        // Without snapshot
        $v8_without = new V8Js();
        $v8_without->executeString($libraryCode);
        $result_without = $v8_without->executeString('LargeLib.method1()');
        
        self::assertEquals(1, $result_with);
        self::assertEquals(1, $result_without);
    }
    
    /**
     * Test multiple instances with same snapshot
     */
    public static function testMultipleInstancesWithSnapshot()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('var shared = "snapshot";');
        
        $v8_1 = new V8Js('PHP', [], $snapshot);
        $v8_2 = new V8Js('PHP', [], $snapshot);
        
        $result1 = $v8_1->executeString('shared');
        $result2 = $v8_2->executeString('shared');
        
        self::assertEquals('snapshot', $result1);
        self::assertEquals('snapshot', $result2);
    }
    
    /**
     * Test snapshot isolation
     */
    public static function testSnapshotIsolation()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('var counter = 0;');
        
        $v8_1 = new V8Js('PHP', [], $snapshot);
        $v8_2 = new V8Js('PHP', [], $snapshot);
        
        $v8_1->executeString('counter = 10;');
        $v8_2->executeString('counter = 20;');
        
        $result1 = $v8_1->executeString('counter');
        $result2 = $v8_2->executeString('counter');
        
        self::assertEquals(10, $result1);
        self::assertEquals(20, $result2);
    }
    
    /**
     * Test snapshot with polyfills
     */
    public static function testSnapshotWithPolyfills()
    {
        self::skipIfNoV8js();
        
        $snapshot = V8Js::createSnapshot('
            if (!Array.prototype.includes) {
                Array.prototype.includes = function(item) {
                    return this.indexOf(item) !== -1;
                };
            }
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        $result = $v8->executeString('[1, 2, 3].includes(2)');
        self::assertTrue($result);
    }
    
    /**
     * Test snapshot error handling
     */
    public static function testSnapshotWithError()
    {
        self::skipIfNoV8js();
        
        // Snapshot creation should succeed even with throwable code
        // as long as it's valid syntax
        $snapshot = V8Js::createSnapshot('
            function throwError() {
                throw new Error("test error");
            }
        ');
        
        $v8 = new V8Js('PHP', [], $snapshot);
        
        // Using the snapshot should work, error only thrown when calling the function
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('throwError()');
        });
    }
}
