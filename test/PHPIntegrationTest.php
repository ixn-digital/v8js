<?php
/**
 * Modern PHP Integration Test Framework for V8js
 * 
 * This base class provides a clean, modern testing API for V8js integration tests
 * that focus on PHP-JavaScript interoperability.
 * 
 * Features:
 * - Simple test discovery via static test*() methods
 * - Fluent assertion API
 * - Clear error messages
 * - Exception handling
 * - Support for skip conditions
 * - JSON output compatible with test262-coordinator.js
 * 
 * Example Test:
 * 
 * class MyTest extends PHPIntegrationTest {
 *     public static function testPassObjectToJS() {
 *         $v8 = new V8Js();
 *         $v8->myObject = new stdClass();
 *         $v8->myObject->name = 'test';
 *         
 *         $result = $v8->executeString('PHP.myObject.name');
 *         self::assertEquals('test', $result);
 *     }
 * 
 *     public static function testCallbackFromJS() {
 *         $v8 = new V8Js();
 *         $called = false;
 *         $v8->callback = function($arg) use (&$called) {
 *             $called = true;
 *             return $arg * 2;
 *         };
 *         
 *         $result = $v8->executeString('PHP.callback(21)');
 *         self::assertEquals(42, $result);
 *         self::assertTrue($called);
 *     }
 * }
 */
abstract class PHPIntegrationTest
{
    private static $currentTest = null;
    private static $assertions = 0;
    
    /**
     * Assert that two values are equal
     */
    public static function assertEquals($expected, $actual, string $message = '')
    {
        self::$assertions++;
        
        if ($expected !== $actual) {
            $expectedStr = self::valueToString($expected);
            $actualStr = self::valueToString($actual);
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected: {$expectedStr}\nActual: {$actualStr}"
            );
        }
    }
    
    /**
     * Assert that a value is true
     */
    public static function assertTrue($actual, string $message = '')
    {
        self::assertEquals(true, $actual, $message ?: 'Expected value to be true');
    }
    
    /**
     * Assert that a value is false
     */
    public static function assertFalse($actual, string $message = '')
    {
        self::assertEquals(false, $actual, $message ?: 'Expected value to be false');
    }
    
    /**
     * Assert that a value is null
     */
    public static function assertNull($actual, string $message = '')
    {
        if ($actual !== null) {
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected null but got: " . self::valueToString($actual)
            );
        }
        self::$assertions++;
    }
    
    /**
     * Assert that a value is not null
     */
    public static function assertNotNull($actual, string $message = '')
    {
        if ($actual === null) {
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError("{$msg}Expected value to not be null");
        }
        self::$assertions++;
    }
    
    /**
     * Assert that two values are the same (===)
     */
    public static function assertSame($expected, $actual, string $message = '')
    {
        self::$assertions++;
        
        if ($expected !== $actual) {
            $expectedStr = self::valueToString($expected);
            $actualStr = self::valueToString($actual);
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected (identical): {$expectedStr}\nActual: {$actualStr}"
            );
        }
    }
    
    /**
     * Assert that a string contains a substring
     */
    public static function assertStringContains(string $needle, string $haystack, string $message = '')
    {
        self::$assertions++;
        
        if (strpos($haystack, $needle) === false) {
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected string to contain: '{$needle}'\nActual: '{$haystack}'"
            );
        }
    }
    
    /**
     * Assert that a value is an instance of a class
     */
    public static function assertInstanceOf(string $class, $actual, string $message = '')
    {
        self::$assertions++;
        
        if (!($actual instanceof $class)) {
            $actualType = is_object($actual) ? get_class($actual) : gettype($actual);
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected instance of: {$class}\nActual: {$actualType}"
            );
        }
    }
    
    /**
     * Assert that an array has a specific key
     */
    public static function assertArrayHasKey($key, array $array, string $message = '')
    {
        self::$assertions++;
        
        if (!array_key_exists($key, $array)) {
            $msg = $message ? "$message\n" : '';
            $keys = implode(', ', array_keys($array));
            throw new TestAssertionError(
                "{$msg}Expected array to have key: '{$key}'\nAvailable keys: [{$keys}]"
            );
        }
    }
    
    /**
     * Assert that a count matches
     */
    public static function assertCount(int $expected, $countable, string $message = '')
    {
        self::$assertions++;
        
        $actual = is_array($countable) ? count($countable) : (
            $countable instanceof Countable ? count($countable) : null
        );
        
        if ($actual === null) {
            throw new TestAssertionError('Value is not countable');
        }
        
        if ($expected !== $actual) {
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected count: {$expected}\nActual count: {$actual}"
            );
        }
    }
    
    /**
     * Assert that a condition throws an exception
     */
    public static function assertThrows(string $exceptionClass, callable $callback, string $message = '')
    {
        self::$assertions++;
        
        try {
            $callback();
            $msg = $message ? "$message\n" : '';
            throw new TestAssertionError(
                "{$msg}Expected exception: {$exceptionClass}\nBut no exception was thrown"
            );
        } catch (Throwable $e) {
            if (!($e instanceof $exceptionClass)) {
                // If it's our TestAssertionError from above, re-throw it
                if ($e instanceof TestAssertionError) {
                    throw $e;
                }
                
                $actualClass = get_class($e);
                $msg = $message ? "$message\n" : '';
                throw new TestAssertionError(
                    "{$msg}Expected exception: {$exceptionClass}\nActual: {$actualClass}\nMessage: {$e->getMessage()}"
                );
            }
        }
    }
    
    /**
     * Skip the current test
     */
    public static function skip(string $reason)
    {
        throw new SkipTestException($reason);
    }
    
    /**
     * Skip test if condition is true
     */
    public static function skipIf(bool $condition, string $reason)
    {
        if ($condition) {
            self::skip($reason);
        }
    }
    
    /**
     * Skip test if V8js extension is not loaded
     */
    public static function skipIfNoV8js()
    {
        self::skipIf(!extension_loaded('v8js'), 'V8js extension not loaded');
    }
    
    /**
     * Convert a value to a readable string for error messages
     */
    private static function valueToString($value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_array($value)) {
            return 'array(' . count($value) . ')';
        } elseif (is_object($value)) {
            return get_class($value);
        } else {
            return var_export($value, true);
        }
    }
    
    /**
     * Run all test methods in the class
     */
    public static function runAll(): array
    {
        $class = get_called_class();
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
        
        $results = [];
        
        foreach ($methods as $method) {
            $name = $method->getName();
            
            // Only run methods that start with "test"
            if (strpos($name, 'test') !== 0) {
                continue;
            }
            
            self::$currentTest = $name;
            self::$assertions = 0;
            
            $result = [
                'test' => $name,
                'class' => $class,
                'status' => 'unknown',
                'message' => null,
                'assertions' => 0,
                'duration' => 0
            ];
            
            $startTime = microtime(true);
            
            try {
                $method->invoke(null);
                $result['status'] = 'pass';
                $result['assertions'] = self::$assertions;
            } catch (SkipTestException $e) {
                $result['status'] = 'skip';
                $result['message'] = $e->getMessage();
            } catch (TestAssertionError $e) {
                $result['status'] = 'fail';
                $result['message'] = $e->getMessage();
                $result['assertions'] = self::$assertions;
            } catch (V8JsException $e) {
                $result['status'] = 'fail';
                $result['message'] = 'V8JsException: ' . $e->getMessage();
                $result['assertions'] = self::$assertions;
            } catch (Throwable $e) {
                $result['status'] = 'error';
                $result['message'] = get_class($e) . ': ' . $e->getMessage();
                $result['trace'] = $e->getTraceAsString();
            }
            
            $result['duration'] = (microtime(true) - $startTime) * 1000; // Convert to ms
            $results[] = $result;
        }
        
        return $results;
    }
}

/**
 * Custom exception for test assertions
 */
class TestAssertionError extends Exception {}

/**
 * Exception to skip a test
 */
class SkipTestException extends Exception {}
