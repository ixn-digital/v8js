# Test Suite for V8js

This directory contains two types of tests for V8js:

1. **Test262**: The official ECMAScript conformance test suite
2. **PHP Integration Tests**: Modern tests for PHP-JavaScript interoperability

## Table of Contents

- [Quick Start](#quick-start)
- [Test262 Tests](#test262-tests)
- [PHP Integration Tests](#php-integration-tests)
- [Configuration](#configuration)
- [Understanding Results](#understanding-results)
- [Contributing](#contributing)

## Quick Start

### Prerequisites

1. V8js extension must be built and installed
2. Test262 submodule must be initialized:
   ```bash
   git submodule update --init test262
   ```
3. Node.js (recommended for concurrent execution and crash isolation)

### Running All Tests

Run both Test262 and PHP integration tests with 4 concurrent workers:

```bash
make test262  # This now runs both test types
```

Or using the coordinator directly:

```bash
node test/test262-coordinator.js --concurrency=4
```

### Running Only PHP Integration Tests

```bash
node test/test262-coordinator.js --type=integration
```

### Running Only Test262 Tests

```bash
node test/test262-coordinator.js --type=test262
```

## PHP Integration Tests

PHP Integration Tests provide a modern, easy-to-write format for testing V8js functionality, especially the PHP-JavaScript interoperability features like passing objects, callbacks, and handling arrays.

### Why PHP Integration Tests?

- **Modern syntax**: No .phpt format, just plain PHP classes
- **Easy to write**: Simple assertion API similar to PHPUnit/Jest
- **Clear output**: Detailed error messages and test results
- **Type safety**: Uses modern PHP 8 features
- **Runs alongside Test262**: Integrated with the same coordinator

### Writing Integration Tests

Create a PHP file in `test/integration/` that extends `PHPIntegrationTest`:

```php
<?php
require_once __DIR__ . '/../PHPIntegrationTest.php';

class MyTest extends PHPIntegrationTest
{
    /**
     * Test methods must start with "test"
     */
    public static function testPassObjectToJS()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->data = ['name' => 'Alice', 'age' => 30];
        
        $result = $v8->executeString('PHP.data.name');
        
        self::assertEquals('Alice', $result);
    }
    
    public static function testCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $called = false;
        
        $v8->callback = function($x) use (&$called) {
            $called = true;
            return $x * 2;
        };
        
        $result = $v8->executeString('PHP.callback(21)');
        
        self::assertEquals(42, $result);
        self::assertTrue($called);
    }
}
```

### Assertion API

The `PHPIntegrationTest` base class provides the following assertions:

#### Basic Assertions
- `assertEquals($expected, $actual, $message = '')` - Assert equal values
- `assertSame($expected, $actual, $message = '')` - Assert identical values (===)
- `assertTrue($actual, $message = '')` - Assert value is true
- `assertFalse($actual, $message = '')` - Assert value is false
- `assertNull($actual, $message = '')` - Assert value is null
- `assertNotNull($actual, $message = '')` - Assert value is not null

#### Type Assertions
- `assertInstanceOf($class, $actual, $message = '')` - Assert object is instance of class

#### String Assertions
- `assertStringContains($needle, $haystack, $message = '')` - Assert string contains substring

#### Array Assertions
- `assertArrayHasKey($key, $array, $message = '')` - Assert array has key
- `assertCount($expected, $countable, $message = '')` - Assert count matches

#### Exception Assertions
- `assertThrows($exceptionClass, $callback, $message = '')` - Assert callback throws exception

#### Skip Conditions
- `skip($reason)` - Skip current test with reason
- `skipIf($condition, $reason)` - Skip if condition is true
- `skipIfNoV8js()` - Skip if V8js extension not loaded

### Running a Single Integration Test

```bash
php test/PHPTestRunner.php test/integration/BasicTest.php
```

### Example Integration Tests

The `test/integration/` directory includes example tests:

- **BasicTest.php**: Basic V8js functionality and type conversions
- **ObjectPassingTest.php**: Passing objects between PHP and JavaScript
- **CallbacksTest.php**: Testing callbacks and closures
- **ArrayAccessTest.php**: ArrayAccess interface integration

### Integration Test Output

Tests output JSON results compatible with the test262-coordinator:

```json
{
  "path": "test/integration/BasicTest.php",
  "status": "pass",
  "message": "All 10 test(s) passed",
  "duration": 45.2,
  "stats": {
    "pass": 10,
    "fail": 0,
    "skip": 0,
    "error": 0,
    "total": 10
  },
  "results": [
    {
      "test": "testBasicExecution",
      "class": "BasicTest",
      "status": "pass",
      "assertions": 1,
      "duration": 2.1
    }
  ]
}
```

## Test262 Tests

Test262 is the official conformance test suite for ECMAScript (JavaScript). It contains over 40,000 tests that verify compliance with the ECMAScript specification.

### What is Test262?

Run a specific test directory with Node.js coordinator:

```bash
node tests/test262/test262-coordinator.js \
    --path=test262/test/language/expressions/arrow-function/ \
    --concurrency=4
```

Run a specific test file:

```bash
php tests/test262/test262-wrapper.php \
    test262/test/language/expressions/arrow-function/basic.js
```

Filter tests by regex pattern:

```bash
php tests/test262/run-test262.php --filter="/arrow-function/"
```

### Custom Output Location

Save results to a custom file:

```bash
php tests/test262/run-test262.php --output=my-results.json
```

## Configuration

The `config.json` file controls which tests are run and how they're executed:

### Supported Features

Features that V8js supports are listed in `supportedFeatures`. Tests requiring these features will be run.

### Unsupported Features

Features that V8js doesn't support are listed in `unsupportedFeatures`. Tests requiring these features will be skipped. Common unsupported features include:

- `BigInt` - Arbitrary precision integers
- `Atomics` - Shared memory atomic operations
- `SharedArrayBuffer` - Shared memory buffers
- `FinalizationRegistry` - Weak references with cleanup callbacks
- `WeakRef` - Weak references

### Skip Patterns

Directories or file patterns to skip entirely:

- `intl402/` - Internationalization tests (Intl API)
- `staging/` - Experimental/draft proposals
- `annexB/` - Optional legacy features
- `built-ins/Atomics/` - Atomics tests
- `built-ins/BigInt/` - BigInt tests

### Test Paths

Default test directories to scan when no `--path` is specified. Focuses on core language features and commonly used built-ins.

### Timeout and Memory Limits

- `timeout`: Maximum execution time per test in milliseconds (default: 10000)
- `memoryLimit`: Maximum memory per test in bytes (default: 128MB)

## Understanding Results

### Test Statuses

- **pass**: Test executed successfully and met expectations
- **fail**: Test executed but failed assertions or threw unexpected errors
- **skip**: Test was skipped due to unsupported features
- **timeout**: Test exceeded time limit
- **error**: Test runner encountered an error (not a test failure)

### Results File

Results are saved as JSON with the following structure:

```json
{
  "timestamp": "2025-12-02 10:30:00",
  "duration": 123.45,
  "stats": {
    "total": 1000,
    "pass": 850,
    "fail": 100,
    "skip": 40,
    "timeout": 5,
    "error": 5
  },
  "results": [
    {
      "path": "test262/test/language/expressions/arrow-function/basic.js",
      "status": "pass",
      "message": null,
      "duration": 0.012
    }
  ]
}
```

### Summary Output

The runner displays a summary showing:

- Total number of tests
- Pass/fail counts and percentages
- Skipped tests
- Overall compliance rating

Example:

```
======================================================================
TEST262 SUMMARY
======================================================================
Total tests:    1000
Passed:          850 (85.0%)
Failed:          100 (10.0%)
Skipped:          40 (4.0%)
Timeout:           5
Error:             5
----------------------------------------------------------------------
Duration:     123.45 seconds
======================================================================
✓ EXCELLENT compliance with Test262
```

## Wrapper Script

The `test262-wrapper.php` script runs a single test in isolation. This is useful for:

- Debugging specific test failures
- Parallel test execution
- Integration with external test harnesses

Usage:

```bash
php tests/test262/test262-wrapper.php test262/test/language/expressions/arrow-function/basic.js
```

Output is JSON on stdout:

```json
{
  "path": "test262/test/language/expressions/arrow-function/basic.js",
  "status": "pass",
  "message": null,
  "duration": 0.012
}
```

## Architecture

### test262-coordinator.js (Recommended)

Node.js-based test coordinator that:

- Runs both Test262 and PHP integration tests
- Executes tests concurrently in separate PHP processes
- Isolates crashes and segfaults (one test failure doesn't stop others)
- Provides better performance through parallelization
- Handles timeouts gracefully
- Tracks crash statistics separately
- Offers better progress reporting

### Test262Adapter.php

Core adapter class that:

- Parses Test262 YAML metadata from test files
- Loads harness files (assert.js, sta.js, etc.)
- Executes tests through V8js
- Handles strict/non-strict mode
- Captures and categorizes results
- Validates expected errors (negative tests)

### run-test262.php

PHP-based test runner (fallback) that:

- Discovers test files based on configuration
- Filters tests by pattern or path
- Executes tests sequentially
- Aggregates results and statistics
- Generates JSON output and console summary

### test262-wrapper.php

Single test execution wrapper:

- Runs one test in isolation
- Returns JSON result
- Used by both coordinator and direct execution

### config.json

Configuration file specifying:

- ECMAScript features supported by V8js
- Features to skip
- Test directories to scan
- Resource limits (timeout, memory)

## Node.js Coordinator Options

```bash
node test/test262-coordinator.js [options]

Options:
  --path=<path>        Specific test file or directory to run
  --filter=<regex>     Filter tests by path regex
  --concurrency=<n>    Number of concurrent workers (default: 4)
  --type=<type>        Test type: test262, integration, or all (default: all)
  --output=<file>      Output results to JSON file
  --verbose            Show detailed output per test
  --summary-only       Only show final summary
  --php=<path>         Path to PHP executable
  --extension=<path>   Path to v8js.so extension
```

### Why Use the Node.js Coordinator?

1. **Unified Testing**: Run both Test262 and PHP integration tests together
2. **Crash Isolation**: Each test runs in a separate PHP process, so segfaults don't crash the entire test suite
3. **Concurrency**: Run multiple tests simultaneously for faster execution
4. **Better Reporting**: Separate crash statistics from other failures
5. **Reliability**: Continue running even when individual tests crash
6. **Performance**: 4-8x faster than sequential execution

## CI/CD Integration

Test262 runs automatically in GitHub Actions on every push and pull request. Results are uploaded as artifacts for each PHP/V8 version combination.

To view results:

1. Go to Actions tab in GitHub
2. Select a workflow run
3. Download Test262 results artifact
4. View JSON file with detailed results

## Troubleshooting

### "Harness file not found" error

Make sure the test262 submodule is initialized:

```bash
git submodule update --init test262
```

### Tests timing out

Increase timeout in `config.json`:

```json
{
  "timeout": 20000
}
```

### Memory limit errors

Increase memory limit in `config.json`:

```json
{
  "memoryLimit": 268435456
}
```

### Too many failures

Review `unsupportedFeatures` and `skipPatterns` to exclude tests for features V8js doesn't implement.

## Contributing

When adding new ECMAScript features to V8js:

1. Add feature name to `supportedFeatures` in `config.json`
2. Run Test262 to verify compliance:
   ```bash
   php tests/test262/run-test262.php --filter="/your-feature/"
   ```
3. Fix any failures
4. Update documentation

## Resources

- [Test262 Repository](https://github.com/tc39/test262)
- [ECMAScript Specification](https://tc39.es/ecma262/)
- [V8js Documentation](https://github.com/phpv8/v8js)
- [Test262 Feature List](https://github.com/tc39/test262/blob/main/INTERPRETING.md)

## License

The Test262 test suite is licensed under its own license. See `test262/LICENSE` for details.
