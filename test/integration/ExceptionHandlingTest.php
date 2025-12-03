<?php
/**
 * Test exception handling between PHP and JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ExceptionHandlingTest extends PHPIntegrationTest
{
    /**
     * Test JavaScript exception caught in PHP
     */
    public static function testJSExceptionInPHP()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('throw new Error("JS error");');
        });
    }
    
    /**
     * Test PHP exception in JS callback
     */
    public static function testPHPExceptionInJSCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->throwError = function() {
            throw new Exception('PHP exception');
        };
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('PHP.throwError();');
        });
    }
    
    /**
     * Test exception message preserved
     */
    public static function testExceptionMessagePreserved()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        try {
            $v8->executeString('throw new Error("Custom error message");');
            self::assertTrue(false, 'Should have thrown exception');
        } catch (V8JsException $e) {
            self::assertStringContains('Custom error message', $e->getMessage());
        }
    }
    
    /**
     * Test syntax error
     */
    public static function testSyntaxError()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('var x = {invalid syntax');
        });
    }
    
    /**
     * Test reference error
     */
    public static function testReferenceError()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('undefinedVariable.property;');
        });
    }
    
    /**
     * Test type error
     */
    public static function testTypeError()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('null.method();');
        });
    }
    
    /**
     * Test try-catch in JavaScript
     */
    public static function testJSTryCatch()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $result = $v8->executeString('
            try {
                throw new Error("caught");
            } catch (e) {
                e.message;
            }
        ');
        
        self::assertEquals('caught', $result);
    }
    
    /**
     * Test exception from PHP callback caught in JS
     */
    public static function testPHPExceptionCaughtInJS()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->throwError = function() {
            throw new Exception('PHP error');
        };
        
        $result = $v8->executeString('
            try {
                PHP.throwError();
                "not reached";
            } catch (e) {
                "caught";
            }
        ');
        
        self::assertEquals('caught', $result);
    }
    
    /**
     * Test exception in nested callbacks
     */
    public static function testNestedCallbackException()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->outer = function($callback) {
            return $callback();
        };
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('
                PHP.outer(function() {
                    throw new Error("nested error");
                });
            ');
        });
    }
    
    /**
     * Test multiple exceptions
     */
    public static function testMultipleExceptions()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $count = 0;
        
        for ($i = 0; $i < 3; $i++) {
            try {
                $v8->executeString('throw new Error("error ' . $i . '");');
            } catch (V8JsException $e) {
                $count++;
            }
        }
        
        self::assertEquals(3, $count);
    }
    
    /**
     * Test exception doesn't corrupt V8 state
     */
    public static function testExceptionDoesntCorruptState()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set up some state
        $v8->executeString('var x = 42;');
        
        // Cause exception
        try {
            $v8->executeString('throw new Error("test");');
        } catch (V8JsException $e) {
            // Expected
        }
        
        // State should still be accessible
        $result = $v8->executeString('x');
        self::assertEquals(42, $result);
    }
    
    /**
     * Test custom error types
     */
    public static function testCustomErrorTypes()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $types = ['Error', 'TypeError', 'ReferenceError', 'SyntaxError'];
        
        foreach ($types as $type) {
            try {
                $v8->executeString("throw new {$type}('test');");
                self::assertTrue(false, "Should have thrown for {$type}");
            } catch (V8JsException $e) {
                // Expected
            }
        }
    }
    
    /**
     * Test exception with stack trace
     */
    public static function testExceptionStackTrace()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        try {
            $v8->executeString('
                function level3() { throw new Error("deep error"); }
                function level2() { level3(); }
                function level1() { level2(); }
                level1();
            ');
            self::assertTrue(false, 'Should have thrown');
        } catch (V8JsException $e) {
            $trace = $e->getJsTrace();
            self::assertNotNull($trace);
        }
    }
    
    /**
     * Test finally block execution
     */
    public static function testFinallyBlock()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $result = $v8->executeString('
            var executed = false;
            try {
                throw new Error("test");
            } catch (e) {
                // handle
            } finally {
                executed = true;
            }
            executed;
        ');
        
        self::assertTrue($result);
    }
}
