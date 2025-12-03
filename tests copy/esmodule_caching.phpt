--TEST--
Test V8::executeModule() : Module caching
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

$v8 = new V8Js();

$load_count = 0;

// Set up module loader that counts loads
$v8->setModuleLoader(function($module) use (&$load_count) {
    $load_count++;
    
    $modules = [
        'counter' => 'let count = 0; export function increment() { return ++count; }',
        'app' => 'import { increment } from "counter"; export const a = increment(); export const b = increment();',
    ];
    
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    
    throw new Exception("Module not found: " . $module);
});

// Test that modules are cached
try {
    $result = $v8->executeModule('
        import { a, b } from "app";
        export const values = [a, b];
    ', 'main');
    
    // counter module should only be loaded once despite multiple imports
    if ($load_count <= 3) {
        echo "PASS: Modules are cached (loaded $load_count times)\n";
    } else {
        echo "FAIL: Modules loaded too many times ($load_count)\n";
    }
} catch (V8JsException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

?>
===DONE===
--EXPECT--
PASS: Modules are cached (loaded 3 times)
===DONE===
