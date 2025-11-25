--TEST--
Test V8::executeModule() : Promise and async/await support
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

// Test 1: Basic Promise
echo "Test 1: Basic Promise\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'async-math') {
        return 'export function asyncAdd(a, b) {
            return new Promise((resolve) => {
                resolve(a + b);
            });
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { asyncAdd } from "async-math";
        export const result = await asyncAdd(5, 3);
    ', 'main');
    var_dump($result->result);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 2: Async/Await
echo "\nTest 2: Async/Await Functions\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'fetcher') {
        return 'export async function fetchData() {
            return new Promise((resolve) => {
                resolve({ data: "Hello World", status: 200 });
            });
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { fetchData } from "fetcher";
        const response = await fetchData();
        export const data = response.data;
        export const status = response.status;
    ', 'main');
    var_dump($result->data);
    var_dump($result->status);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 3: Promise Chain
echo "\nTest 3: Promise Chain\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'chainer') {
        return 'export function chain(value) {
            return Promise.resolve(value)
                .then(v => v * 2)
                .then(v => v + 10)
                .then(v => v * 3);
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { chain } from "chainer";
        export const result = await chain(5);
    ', 'main');
    var_dump($result->result);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 4: Promise.all()
echo "\nTest 4: Promise.all()\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'multi') {
        return 'export function getMultiple() {
            return Promise.all([
                Promise.resolve(1),
                Promise.resolve(2),
                Promise.resolve(3)
            ]);
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { getMultiple } from "multi";
        export const results = await getMultiple();
    ', 'main');
    var_dump($result->results);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 5: Promise Rejection
echo "\nTest 5: Promise Rejection\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'failing') {
        return 'export function fail() {
            return Promise.reject(new Error("Operation failed"));
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { fail } from "failing";
        export const result = await fail();
    ', 'main');
    echo "FAIL: Should have thrown exception\n";
} catch (V8JsException $e) {
    echo "Caught expected exception: ";
    var_dump(strpos($e->getMessage(), 'Operation failed') !== false);
}

// Test 6: Top-level await
echo "\nTest 6: Top-level Await\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'delay') {
        return 'export const value = await Promise.resolve(42);';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { value } from "delay";
        export const imported = value;
        export const calculated = await Promise.resolve(value * 2);
    ', 'main');
    var_dump($result->imported);
    var_dump($result->calculated);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 7: Async function in module
echo "\nTest 7: Async Function Export\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'async-ops') {
        return 'export async function compute() {
            const a = await Promise.resolve(10);
            const b = await Promise.resolve(20);
            return a + b;
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { compute } from "async-ops";
        export const sum = await compute();
    ', 'main');
    var_dump($result->sum);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 8: Error in async function
echo "\nTest 8: Error in Async Function\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'error-async') {
        return 'export async function badFunction() {
            await Promise.resolve();
            throw new Error("Async error");
        }';
    }
    throw new Exception("Module not found");
});

try {
    $result = $v8->executeModule('
        import { badFunction } from "error-async";
        export const result = await badFunction();
    ', 'main');
    echo "FAIL: Should have thrown exception\n";
} catch (V8JsException $e) {
    echo "Caught expected exception: ";
    var_dump(strpos($e->getMessage(), 'Async error') !== false);
}

?>
===DONE===
--EXPECT--
Test 1: Basic Promise
int(8)

Test 2: Async/Await Functions
string(11) "Hello World"
int(200)

Test 3: Promise Chain
int(60)

Test 4: Promise.all()
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}

Test 5: Promise Rejection
Caught expected exception: bool(true)

Test 6: Top-level Await
int(42)
int(84)

Test 7: Async Function Export
int(30)

Test 8: Error in Async Function
Caught expected exception: bool(true)
===DONE===
