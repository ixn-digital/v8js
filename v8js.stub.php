<?php

/**
 * PHPStan stubs for V8Js extension
 * 
 * This file provides type information for static analysis tools like PHPStan.
 * It should not be included in runtime code.
 * 
 * @link https://github.com/phpv8/v8js
 */

/**
 * V8Js - Embedded V8 Javascript Engine for PHP
 * 
 * This class provides an interface to Google's V8 Javascript engine,
 * allowing PHP code to compile and execute JavaScript.
 */
class V8Js
{
    /**
     * V8 engine version
     */
    public const string V8_VERSION = '';

    /**
     * No flags - default behavior
     */
    public const int FLAG_NONE = 1;

    /**
     * Force conversion of JavaScript objects to PHP arrays
     */
    public const int FLAG_FORCE_ARRAY = 2;

    /**
     * Propagate PHP exceptions to JavaScript
     */
    public const int FLAG_PROPAGATE_PHP_EXCEPTIONS = 4;

    /**
     * Create a new V8Js instance
     * 
     * @param string|null $object_name Name for the PHP object in JavaScript context (default: "PHP")
     * @param array<string, mixed>|null $variables Variables to pass to JavaScript context
     * @param string|null $snapshot_blob V8 snapshot blob for faster startup
     * @throws V8JsException if V8 context cannot be created
     */
    public function __construct(?string $object_name = null, ?array $variables = null, ?string $snapshot_blob = null) {}

    /**
     * Prevent serialization
     * @return never
     * @throws V8JsException
     */
    final public function __sleep(): array {}

    /**
     * Prevent unserialization
     * @return never
     * @throws V8JsException
     */
    final public function __wakeup(): void {}

    /**
     * Execute JavaScript code
     * 
     * @param string $script JavaScript code to execute
     * @param string|null $identifier Script identifier for error messages (default: "V8Js::executeString()")
     * @param int $flags Execution flags (V8Js::FLAG_*)
     * @param int $time_limit Maximum execution time in milliseconds (0 = no limit)
     * @param int $memory_limit Maximum memory usage in bytes (0 = no limit)
     * @return mixed Result of the JavaScript execution
     * @throws V8JsScriptException if JavaScript execution fails
     * @throws V8JsTimeLimitException if execution exceeds time limit
     * @throws V8JsMemoryLimitException if execution exceeds memory limit
     */
    public function executeString(string $script, ?string $identifier = null, int $flags = 1, int $time_limit = 0, int $memory_limit = 0): mixed {}

    /**
     * Compile JavaScript code without executing it
     * 
     * @param string $script JavaScript code to compile
     * @param string|null $identifier Script identifier for error messages
     * @return resource|false Compiled script resource or false on failure
     * @throws V8JsScriptException if compilation fails
     */
    public function compileString(string $script, ?string $identifier = null) {}

    /**
     * Execute a previously compiled JavaScript script
     * 
     * @param resource $script Compiled script resource from compileString()
     * @param int $flags Execution flags (V8Js::FLAG_*)
     * @param int $time_limit Maximum execution time in milliseconds (0 = no limit)
     * @param int $memory_limit Maximum memory usage in bytes (0 = no limit)
     * @return mixed Result of the JavaScript execution
     * @throws V8JsScriptException if JavaScript execution fails
     * @throws V8JsTimeLimitException if execution exceeds time limit
     * @throws V8JsMemoryLimitException if execution exceeds memory limit
     */
    public function executeScript($script, int $flags = 1, int $time_limit = 0, int $memory_limit = 0): mixed {}

    /**
     * Execute JavaScript code as an ES module
     * 
     * @param string $script JavaScript module code to execute
     * @param string|null $identifier Module identifier
     * @param int $flags Execution flags (V8Js::FLAG_*)
     * @param int $time_limit Maximum execution time in milliseconds (0 = no limit)
     * @param int $memory_limit Maximum memory usage in bytes (0 = no limit)
     * @return mixed Result of the module execution
     * @throws V8JsScriptException if module execution fails
     * @throws V8JsTimeLimitException if execution exceeds time limit
     * @throws V8JsMemoryLimitException if execution exceeds memory limit
     */
    public function executeModule(string $script, ?string $identifier = null, int $flags = 1, int $time_limit = 0, int $memory_limit = 0): mixed {}

    /**
     * Set a custom module loader callback
     * 
     * The callback receives the module name and should return the module source code.
     * 
     * @param callable(string): string $callable Module loader callback
     */
    public function setModuleLoader(callable $callable): void {}

    /**
     * Set a custom module resolver callback
     * 
     * The callback receives the base module name and the requested module name,
     * and should return the normalized module name.
     * 
     * @param callable(string, string): string $callable Module resolver callback
     */
    public function setModuleResolver(callable $callable): void {}

    /**
     * Set an exception filter callback
     * 
     * The callback receives an exception and can return a modified exception
     * or null to use the original.
     * 
     * @param callable(\Throwable): ?\Throwable $callable Exception filter callback
     */
    public function setExceptionFilter(callable $callable): void {}

    /**
     * Set the maximum execution time for JavaScript code
     * 
     * @param int $time_limit Time limit in milliseconds (0 = no limit)
     */
    public function setTimeLimit(int $time_limit): void {}

    /**
     * Set the maximum memory usage for JavaScript code
     * 
     * @param int $memory_limit Memory limit in bytes (0 = no limit)
     */
    public function setMemoryLimit(int $memory_limit): void {}

    /**
     * Set the average object size for memory calculations
     * 
     * @param int $average_object_size Average size in bytes
     */
    public function setAverageObjectSize(int $average_object_size): void {}

    /**
     * Create a V8 heap snapshot from JavaScript code
     * 
     * @param string $script JavaScript code to execute for snapshot creation
     * @return string|false Snapshot blob or false on failure
     */
    public static function createSnapshot(string $script) {}
}

/**
 * Base exception class for V8Js errors
 */
class V8JsException extends RuntimeException {}

/**
 * Exception thrown when JavaScript execution fails
 */
final class V8JsScriptException extends V8JsException
{
    protected ?string $JsFileName = null;
    protected ?int $JsLineNumber = null;
    protected ?int $JsStartColumn = null;
    protected ?int $JsEndColumn = null;
    protected ?string $JsSourceLine = null;
    protected ?string $JsTrace = null;

    /**
     * Get the JavaScript file name where the error occurred
     */
    final public function getJsFileName(): ?string {}

    /**
     * Get the JavaScript line number where the error occurred
     */
    final public function getJsLineNumber(): ?int {}

    /**
     * Get the JavaScript start column where the error occurred
     */
    final public function getJsStartColumn(): ?int {}

    /**
     * Get the JavaScript end column where the error occurred
     */
    final public function getJsEndColumn(): ?int {}

    /**
     * Get the JavaScript source line where the error occurred
     */
    final public function getJsSourceLine(): ?string {}

    /**
     * Get the JavaScript stack trace
     */
    final public function getJsTrace(): ?string {}
}

/**
 * Exception thrown when JavaScript execution exceeds time limit
 */
final class V8JsTimeLimitException extends V8JsException {}

/**
 * Exception thrown when JavaScript execution exceeds memory limit
 */
final class V8JsMemoryLimitException extends V8JsException {}

/**
 * Represents a JavaScript object in PHP
 * 
 * This class cannot be instantiated directly. Instances are created
 * when JavaScript objects are passed to PHP code.
 */
final class V8Object
{
    /**
     * @internal Cannot be constructed directly
     * @return never
     * @throws V8JsException
     */
    public function __construct() {}

    /**
     * @internal Cannot be serialized
     * @return never
     * @throws V8JsException
     */
    final public function __sleep(): array {}

    /**
     * @internal Cannot be unserialized
     * @return never
     * @throws V8JsException
     */
    final public function __wakeup(): void {}
}

/**
 * Represents a JavaScript function in PHP
 * 
 * This class cannot be instantiated directly. Instances are created
 * when JavaScript functions are passed to PHP code. The function can
 * be invoked by calling the object.
 */
final class V8Function
{
    /**
     * @internal Cannot be constructed directly
     * @return never
     * @throws V8JsException
     */
    public function __construct() {}

    /**
     * @internal Cannot be serialized
     * @return never
     * @throws V8JsException
     */
    final public function __sleep(): array {}

    /**
     * @internal Cannot be unserialized
     * @return never
     * @throws V8JsException
     */
    final public function __wakeup(): void {}

    /**
     * Invoke the JavaScript function
     * 
     * @param mixed ...$args Arguments to pass to the function
     * @return mixed Result of the function call
     */
    public function __invoke(mixed ...$args): mixed {}
}

/**
 * Represents a JavaScript generator in PHP
 * 
 * This class cannot be instantiated directly. Instances are created
 * when JavaScript generator objects are passed to PHP code.
 * Implements Iterator to allow iteration over generator values.
 * 
 * @implements \Iterator<mixed, mixed>
 */
final class V8Generator implements Iterator
{
    /**
     * @internal Cannot be constructed directly
     * @return never
     * @throws V8JsException
     */
    public function __construct() {}

    /**
     * @internal Cannot be serialized
     * @return never
     * @throws V8JsException
     */
    final public function __sleep(): array {}

    /**
     * @internal Cannot be unserialized
     * @return never
     * @throws V8JsException
     */
    final public function __wakeup(): void {}

    /**
     * Get the current value from the generator
     */
    public function current(): mixed {}

    /**
     * Get the current key from the generator
     */
    public function key(): mixed {}

    /**
     * Advance the generator to the next value
     */
    public function next(): void {}

    /**
     * Rewind the generator to the beginning
     */
    public function rewind(): void {}

    /**
     * Check if the generator has more values
     */
    public function valid(): bool {}
}
