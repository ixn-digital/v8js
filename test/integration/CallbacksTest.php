<?php
/**
 * Test JavaScript callbacks and closures from PHP
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class CallbacksTest extends PHPIntegrationTest
{
    /**
     * Test calling a JavaScript function from PHP
     */
    public static function testCallJSFunctionFromPHP()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->callback = function($x) {
            return $x * 2;
        };
        
        $result = $v8->executeString('PHP.callback(21);');
        
        self::assertEquals(42, $result);
    }
    
    /**
     * Test JavaScript callback with object parameter
     */
    public static function testJSCallbackWithObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $called = false;
        $receivedValue = null;
        
        $v8->callback = function($obj) use (&$called, &$receivedValue) {
            $called = true;
            $receivedValue = $obj->value;
            return 'processed';
        };
        
        $result = $v8->executeString('
            PHP.callback({ value: "test" });
        ');
        
        self::assertTrue($called);
        self::assertEquals('test', $receivedValue);
        self::assertEquals('processed', $result);
    }
    
    /**
     * Test that V8Function can be identified
     */
    public static function testV8FunctionType()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->test = function($callback) {
            return is_a($callback, 'V8Function');
        };
        
        $result = $v8->executeString('
            PHP.test(function(x) { return x + 1; });
        ');
        
        self::assertTrue($result);
    }
    
    /**
     * Test calling a V8Function from PHP
     */
    public static function testCallV8Function()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $jsFunc = null;
        
        $v8->capture = function($func) use (&$jsFunc) {
            $jsFunc = $func;
        };
        
        $v8->executeString('
            PHP.capture(function(a, b) { return a + b; });
        ');
        
        self::assertNotNull($jsFunc);
        self::assertInstanceOf('V8Function', $jsFunc);
        
        // Call the captured function
        $result = $jsFunc(10, 20);
        self::assertEquals(30, $result);
    }
    
    /**
     * Test JavaScript object with callback method
     */
    public static function testJSObjectWithCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->test = function($obj) {
            return $obj->method('hello');
        };
        
        $result = $v8->executeString('
            PHP.test({
                method: function(str) {
                    return str + " world";
                }
            });
        ');
        
        self::assertEquals('hello world', $result);
    }
    
    /**
     * Test closure with captured variables
     */
    public static function testClosureWithCapturedVars()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $multiplier = 3;
        
        $v8->multiply = function($x) use ($multiplier) {
            return $x * $multiplier;
        };
        
        $result = $v8->executeString('PHP.multiply(7);');
        
        self::assertEquals(21, $result);
    }
    
    /**
     * Test multiple callbacks
     */
    public static function testMultipleCallbacks()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $calls = [];
        
        $v8->log = function($msg) use (&$calls) {
            $calls[] = $msg;
        };
        
        $v8->executeString('
            PHP.log("first");
            PHP.log("second");
            PHP.log("third");
        ');
        
        self::assertCount(3, $calls);
        self::assertEquals(['first', 'second', 'third'], $calls);
    }
    
    /**
     * Test callback that throws exception
     */
    public static function testCallbackException()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->throwError = function() {
            throw new Exception('PHP error');
        };
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('PHP.throwError();');
        });
    }
}
