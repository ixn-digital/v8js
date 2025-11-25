--TEST--
Test V8::executeModule() : Error handling
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

// Test 1: Missing module loader
try {
    $v8 = new V8Js();
    $result = $v8->executeModule('import { x } from "test";', 'main');
    echo "FAIL: Should throw exception for missing loader\n";
} catch (V8JsException $e) {
    if (strpos($e->getMessage(), 'loader') !== false) {
        echo "PASS: Missing loader detected\n";
    } else {
        echo "FAIL: Wrong error: " . $e->getMessage() . "\n";
    }
}

// Test 2: Module not found
try {
    $v8 = new V8Js();
    $v8->setModuleLoader(function($module) {
        throw new Exception("Module not found: " . $module);
    });
    
    $result = $v8->executeModule('import { x } from "nonexistent";', 'main');
    echo "FAIL: Should throw exception for missing module\n";
} catch (Exception $e) {
    echo "PASS: Module not found error\n";
}

// Test 3: Syntax error in module
try {
    $v8 = new V8Js();
    $v8->setModuleLoader(function($module) {
        return 'export const x = ;'; // Invalid syntax
    });
    
    $result = $v8->executeModule('import { x } from "broken";', 'main');
    echo "FAIL: Should throw exception for syntax error\n";
} catch (V8JsException $e) {
    echo "PASS: Syntax error detected\n";
}

?>
===DONE===
--EXPECT--
PASS: Missing loader detected
PASS: Module not found error
PASS: Syntax error detected
===DONE===
