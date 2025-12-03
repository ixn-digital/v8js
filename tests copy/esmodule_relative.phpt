--TEST--
Test V8::executeModule() : Relative module resolution
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

$v8 = new V8Js();

// Set up module loader
$v8->setModuleLoader(function($module) {
    $modules = [
        'utils/math' => 'export function add(a, b) { return a + b; }',
        'utils/string' => 'export function upper(s) { return s.toUpperCase(); }',
        'app' => 'import { add } from "./utils/math"; import { upper } from "./utils/string"; export const result = upper("test"); export const sum = add(1, 2);',
    ];
    
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    
    throw new Exception("Module not found: " . $module);
});

// Set up custom resolver for relative paths
$v8->setModuleResolver(function($base, $specifier) {
    // Handle relative imports
    if (substr($specifier, 0, 2) === './') {
        $specifier = substr($specifier, 2);
        if ($base) {
            return $base . '/' . $specifier;
        }
        return $specifier;
    }
    
    if (substr($specifier, 0, 3) === '../') {
        // Handle parent directory
        $parts = explode('/', $base);
        array_pop($parts);
        return implode('/', $parts) . '/' . substr($specifier, 3);
    }
    
    return $specifier;
});

// Test relative imports
try {
    $result = $v8->executeModule('
        import { result, sum } from "app";
        export const output = result + " " + sum;
    ', 'main');
    
    echo "PASS: Relative imports\n";
} catch (V8JsException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

?>
===DONE===
--EXPECT--
PASS: Relative imports
===DONE===
