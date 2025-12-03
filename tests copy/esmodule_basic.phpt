--TEST--
Test V8::executeModule() : Basic ES module import/export
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

$v8 = new V8Js();

// Set up module loader
$v8->setModuleLoader(function($module) {
    $modules = [
        'math' => 'export function add(a, b) { return a + b; } export const PI = 3.14159;',
        'greet' => 'export default function(name) { return "Hello, " + name; }',
    ];
    
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    
    throw new Exception("Module not found: " . $module);
});

// Test basic import/export
try {
    $result = $v8->executeModule('
        import { add, PI } from "math";
        export const result = add(2, 3);
        export const pi = PI;
    ', 'main');
    
    var_dump($result);
    echo "PASS: Basic import/export\n";
} catch (V8JsException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test default export
try {
    $v8_2 = new V8Js();
    $v8_2->setModuleLoader(function($module) {
        if ($module === 'greet') {
            return 'export default function(name) { return "Hello, " + name; }';
        }
        throw new Exception("Module not found");
    });
    
    $result = $v8_2->executeModule('
        import greet from "greet";
        export const message = greet("World");
    ', 'main');
    
    var_dump($result);
    echo "PASS: Default export\n";
} catch (V8JsException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

?>
===DONE===
--EXPECT--
PASS: Basic import/export
PASS: Default export
===DONE===
