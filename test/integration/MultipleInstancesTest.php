<?php
/**
 * Test multiple V8Js instances and their isolation
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class MultipleInstancesTest extends PHPIntegrationTest
{
    /**
     * Test creating multiple V8Js instances
     */
    public static function testCreateMultipleInstances()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        $v8_3 = new V8Js();
        
        self::assertInstanceOf('V8Js', $v8_1);
        self::assertInstanceOf('V8Js', $v8_2);
        self::assertInstanceOf('V8Js', $v8_3);
    }
    
    /**
     * Test isolation between instances - variables
     */
    public static function testInstanceIsolationVariables()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        // Set variable in first instance
        $v8_1->executeString('var instanceVar = "instance1";');
        
        // Try to access from second instance (should fail)
        self::assertThrows(V8JsException::class, function() use ($v8_2) {
            $v8_2->executeString('instanceVar');
        });
    }
    
    /**
     * Test isolation between instances - globals
     */
    public static function testInstanceIsolationGlobals()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        $v8_1->data1 = 'first';
        $v8_2->data2 = 'second';
        
        // Each instance should only see its own PHP globals
        $result1 = $v8_1->executeString('typeof PHP.data1 + "," + typeof PHP.data2');
        $result2 = $v8_2->executeString('typeof PHP.data1 + "," + typeof PHP.data2');
        
        self::assertEquals('string,undefined', $result1);
        self::assertEquals('undefined,string', $result2);
    }
    
    /**
     * Test each instance has independent state
     */
    public static function testIndependentState()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        // Set up counters in each instance
        $v8_1->executeString('var counter = 0; counter++;');
        $v8_2->executeString('var counter = 100; counter += 10;');
        
        $result1 = $v8_1->executeString('counter');
        $result2 = $v8_2->executeString('counter');
        
        self::assertEquals(1, $result1);
        self::assertEquals(110, $result2);
    }
    
    /**
     * Test different object name prefixes
     */
    public static function testDifferentObjectNames()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js('PHP1');
        $v8_2 = new V8Js('PHP2');
        
        $v8_1->value = 'first';
        $v8_2->value = 'second';
        
        $result1 = $v8_1->executeString('PHP1.value');
        $result2 = $v8_2->executeString('PHP2.value');
        
        self::assertEquals('first', $result1);
        self::assertEquals('second', $result2);
    }
    
    /**
     * Test instances can coexist with different configurations
     */
    public static function testDifferentExtensions()
    {
        self::skipIfNoV8js();
        
        // Note: Extensions parameter is not implemented in this version of V8JS
        // Just test that multiple instances work independently
        $v8_1 = new V8Js('PHP', []);
        $v8_2 = new V8Js('PHP', []);
        
        // Both should work independently
        $result1 = $v8_1->executeString('2 + 2');
        $result2 = $v8_2->executeString('3 + 3');
        
        self::assertEquals(4, $result1);
        self::assertEquals(6, $result2);
    }
    
    /**
     * Test sequential execution across instances
     */
    public static function testSequentialExecution()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        $results = [];
        
        $results[] = $v8_1->executeString('"result1"');
        $results[] = $v8_2->executeString('"result2"');
        $results[] = $v8_1->executeString('"result3"');
        $results[] = $v8_2->executeString('"result4"');
        
        self::assertEquals(['result1', 'result2', 'result3', 'result4'], $results);
    }
    
    /**
     * Test instance cleanup
     */
    public static function testInstanceCleanup()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->executeString('var x = 1;');
        
        // Destroy instance
        unset($v8);
        
        // Create new instance - should be clean
        $v8_new = new V8Js();
        
        self::assertThrows(V8JsException::class, function() use ($v8_new) {
            $v8_new->executeString('x');
        });
    }
    
    /**
     * Test instances with same object name don't interfere
     */
    public static function testSameObjectNameDifferentInstances()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js('PHP');
        $v8_2 = new V8Js('PHP');
        
        $v8_1->data = 'instance1';
        $v8_2->data = 'instance2';
        
        $result1 = $v8_1->executeString('PHP.data');
        $result2 = $v8_2->executeString('PHP.data');
        
        self::assertEquals('instance1', $result1);
        self::assertEquals('instance2', $result2);
    }
    
    /**
     * Test creating many instances
     */
    public static function testManyInstances()
    {
        self::skipIfNoV8js();
        
        $instances = [];
        for ($i = 0; $i < 10; $i++) {
            $instances[] = new V8Js();
        }
        
        // All should work
        foreach ($instances as $i => $v8) {
            $result = $v8->executeString((string)$i);
            self::assertEquals($i, $result);
        }
    }
    
    /**
     * Test instance with shared PHP object
     */
    public static function testSharedPHPObject()
    {
        self::skipIfNoV8js();
        
        $shared = new stdClass();
        $shared->counter = 0;
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        $v8_1->shared = $shared;
        $v8_2->shared = $shared;
        
        // Modifications from one instance affect the shared object
        $v8_1->executeString('PHP.shared.counter = 10;');
        
        $result = $v8_2->executeString('PHP.shared.counter');
        self::assertEquals(10, $result);
        self::assertEquals(10, $shared->counter);
    }
    
    /**
     * Test error in one instance doesn't affect others
     */
    public static function testErrorIsolation()
    {
        self::skipIfNoV8js();
        
        $v8_1 = new V8Js();
        $v8_2 = new V8Js();
        
        // Cause error in first instance
        try {
            $v8_1->executeString('throw new Error("test error");');
        } catch (V8JsException $e) {
            // Expected
        }
        
        // Second instance should still work fine
        $result = $v8_2->executeString('2 + 2');
        self::assertEquals(4, $result);
    }
}
