--TEST--
Test V8::executeModule() : Circular dependency detection
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

$v8 = new V8Js();

// Set up module loader with circular dependency
$v8->setModuleLoader(function($module) {
    $modules = [
        'a' => 'import { b } from "b"; export const a = "module-a";',
        'b' => 'import { a } from "a"; export const b = "module-b";',
    ];
    
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    
    throw new Exception("Module not found: " . $module);
});

// Test circular dependency detection
try {
    $result = $v8->executeModule('
        import { a } from "a";
        export const result = a;
    ', 'main');
    
    echo "FAIL: Should have detected circular dependency\n";
} catch (V8JsException $e) {
    if (strpos($e->getMessage(), 'cyclic') !== false) {
        echo "PASS: Circular dependency detected\n";
    } else {
        echo "FAIL: Wrong error: " . $e->getMessage() . "\n";
    }
}

?>
===DONE===
--EXPECT--
PASS: Circular dependency detected
===DONE===
