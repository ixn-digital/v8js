--TEST--
Test V8::executeModule() : Dynamic import() support
--SKIPIF--
<?php require_once(dirname(__FILE__) . '/skipif.inc'); ?>
--FILE--
<?php

// Test 1: Basic dynamic import
echo "Test 1: Basic Dynamic Import\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    $modules = [
        'utils' => 'export const value = 42; export function helper() { return "help"; }',
    ];
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    throw new Exception("Module not found: " . $module);
});

try {
    $result = $v8->executeModule('
        const module = await import("utils");
        export const imported = module.value;
        export const helperResult = module.helper();
    ', 'main');
    var_dump($result->imported);
    var_dump($result->helperResult);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 2: Conditional dynamic import
echo "\nTest 2: Conditional Dynamic Import\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    $modules = [
        'feature-a' => 'export const name = "Feature A"; export const version = 1;',
        'feature-b' => 'export const name = "Feature B"; export const version = 2;'
    ];
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    throw new Exception("Module not found: " . $module);
});

try {
    $result = $v8->executeModule('
        const condition = true;
        const featureModule = condition ? "feature-a" : "feature-b";
        const module = await import(featureModule);
        export const featureName = module.name;
        export const featureVersion = module.version;
    ', 'main');
    var_dump($result->featureName);
    var_dump($result->featureVersion);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 3: Dynamic import in function
echo "\nTest 3: Dynamic Import in Function\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    $modules = [
        'data' => 'export const users = ["Alice", "Bob", "Charlie"];'
    ];
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    throw new Exception("Module not found: " . $module);
});

try {
    $result = $v8->executeModule('
        async function loadData() {
            const module = await import("data");
            return module.users;
        }
        export const users = await loadData();
    ', 'main');
    var_dump($result->users);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 4: Multiple dynamic imports
echo "\nTest 4: Multiple Dynamic Imports\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    $modules = [
        'module-1' => 'export const value1 = 10;',
        'module-2' => 'export const value2 = 20;',
        'module-3' => 'export const value3 = 30;'
    ];
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    throw new Exception("Module not found: " . $module);
});

try {
    $result = $v8->executeModule('
        const [mod1, mod2, mod3] = await Promise.all([
            import("module-1"),
            import("module-2"),
            import("module-3")
        ]);
        export const sum = mod1.value1 + mod2.value2 + mod3.value3;
    ', 'main');
    var_dump($result->sum);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 5: Dynamic import error handling
echo "\nTest 5: Dynamic Import Error Handling\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    if ($module === 'existing') {
        return 'export const value = 123;';
    }
    throw new Exception("Module not found: " . $module);
});

try {
    $result = $v8->executeModule('
        const module = await import("nonexistent");
        export const loaded = true;
    ', 'main');
    echo "FAIL: Should have thrown exception\n";
} catch (Exception $e) {
    // Error is properly propagated to PHP
    echo "Caught expected error: ";
    var_dump(strpos($e->getMessage(), 'Module not found') !== false);
}

// Test 6: Dynamic import with relative paths
echo "\nTest 6: Dynamic Import with Relative Paths\n";
$v8 = new V8Js();
$v8->setModuleLoader(function($module) {
    $modules = [
        'lib/helper' => 'export const help = "Available";'
    ];
    if (isset($modules[$module])) {
        return $modules[$module];
    }
    throw new Exception("Module not found: " . $module);
});

$v8->setModuleResolver(function($base, $specifier) {
    if (substr($specifier, 0, 2) === './') {
        $specifier = substr($specifier, 2);
        return $base ? $base . '/' . $specifier : $specifier;
    }
    return $specifier;
});

try {
    $result = $v8->executeModule('
        const module = await import("./helper");
        export const help = module.help;
    ', 'lib/main');
    var_dump($result->help);
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

?>
===DONE===
--EXPECT--
Test 1: Basic Dynamic Import
int(42)
string(4) "help"

Test 2: Conditional Dynamic Import
string(9) "Feature A"
int(1)

Test 3: Dynamic Import in Function
array(3) {
  [0]=>
  string(5) "Alice"
  [1]=>
  string(3) "Bob"
  [2]=>
  string(7) "Charlie"
}

Test 4: Multiple Dynamic Imports
int(60)

Test 5: Dynamic Import Error Handling
Caught expected error: bool(true)

Test 6: Dynamic Import with Relative Paths
string(9) "Available"
===DONE===
