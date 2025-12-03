<?php
/**
 * Test loading multiple modules in a single V8Js instance
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class MultipleModulesTest extends PHPIntegrationTest
{
    /**
     * Test loading multiple different modules
     */
    public static function testMultipleModules()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'math' => 'exports.add = (a, b) => a + b;',
            'string' => 'exports.concat = (a, b) => a + b;',
            'array' => 'exports.sum = (arr) => arr.reduce((a, b) => a + b, 0);',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var math = require("math");
            var str = require("string");
            var arr = require("array");
            
            [
                math.add(2, 3),
                str.concat("Hello", "World"),
                arr.sum([1, 2, 3, 4, 5])
            ];
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(5, $result[0]);
        self::assertEquals('HelloWorld', $result[1]);
        self::assertEquals(15, $result[2]);
    }
    
    /**
     * Test module caching - same module required twice
     */
    public static function testModuleCaching()
    {
        self::skipIfNoV8js();
        
        $loadCount = 0;
        
        $v8 = new V8Js();
        $v8->setModuleLoader(function($module) use (&$loadCount) {
            if ($module === 'cached') {
                $loadCount++;
                return 'exports.value = 42;';
            }
            return null;
        });
        
        $v8->executeString('
            var mod1 = require("cached");
            var mod2 = require("cached");
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        // Module loader should only be called once due to caching
        self::assertEquals(1, $loadCount);
    }
    
    /**
     * Test cached module returns same instance
     */
    public static function testCachedModuleSameInstance()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'stateful') {
                return '
                    var state = { counter: 0 };
                    exports.increment = function() { state.counter++; };
                    exports.get = function() { return state.counter; };
                ';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var mod1 = require("stateful");
            var mod2 = require("stateful");
            
            mod1.increment();
            mod1.increment();
            
            // mod2 should share the same state
            mod2.get();
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(2, $result);
    }
    
    /**
     * Test interdependent modules
     */
    public static function testInterdependentModules()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'util' => 'exports.double = (x) => x * 2;',
            'calculator' => '
                var util = require("util");
                exports.compute = (x) => util.double(x) + 10;
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var calc = require("calculator");
            calc.compute(5);
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(20, $result); // (5 * 2) + 10
    }
    
    /**
     * Test circular module dependencies
     */
    public static function testCircularDependencies()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'a' => '
                exports.name = "moduleA";
                var b = require("b");
                exports.getBName = function() { return b.name; };
            ',
            'b' => '
                exports.name = "moduleB";
                var a = require("a");
                exports.getAName = function() { return a.name; };
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var a = require("a");
            var b = require("b");
            [a.name, b.name, a.getBName(), b.getAName()];
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('moduleA', $result[0]);
        self::assertEquals('moduleB', $result[1]);
        self::assertEquals('moduleB', $result[2]);
        self::assertEquals('moduleA', $result[3]);
    }
    
    /**
     * Test many modules
     */
    public static function testManyModules()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if (preg_match('/^module(\d+)$/', $module, $matches)) {
                $num = $matches[1];
                return "exports.value = {$num};";
            }
            return null;
        });
        
        $result = $v8->executeString('
            var sum = 0;
            for (var i = 1; i <= 10; i++) {
                var mod = require("module" + i);
                sum += mod.value;
            }
            sum;
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(55, $result); // Sum of 1..10
    }
    
    /**
     * Test module with nested requires
     */
    public static function testNestedRequires()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'level1' => '
                exports.load = function() {
                    return require("level2").value;
                };
            ',
            'level2' => '
                exports.value = require("level3").data;
            ',
            'level3' => '
                exports.data = "deep";
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var mod = require("level1");
            mod.load();
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('deep', $result);
    }
    
    /**
     * Test module exports object vs module.exports
     */
    public static function testExportsVsModuleExports()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'exports' => 'exports.a = 1; exports.b = 2;',
            'module_exports' => 'module.exports = { x: 10, y: 20 };',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var exp = require("exports");
            var mod = require("module_exports");
            [exp.a, exp.b, mod.x, mod.y];
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals([1, 2, 10, 20], $result);
    }
    
    /**
     * Test module with immediate execution
     */
    public static function testModuleImmediateExecution()
    {
        self::skipIfNoV8js();
        
        $executed = [];
        
        $v8 = new V8Js();
        $v8->track = function($name) use (&$executed) {
            $executed[] = $name;
        };
        
        $modules = [
            'eager' => 'PHP.track("eager"); exports.value = 1;',
            'lazy' => 'PHP.track("lazy"); exports.value = 2;',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        // Only require eager module
        $v8->executeString('
            var eager = require("eager");
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        // Only eager should have been executed
        self::assertEquals(['eager'], $executed);
    }
    
    /**
     * Test module scope isolation
     */
    public static function testModuleScopeIsolation()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'mod1' => 'var secret = "module1"; exports.get = function() { return secret; };',
            'mod2' => 'var secret = "module2"; exports.get = function() { return secret; };',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var mod1 = require("mod1");
            var mod2 = require("mod2");
            [mod1.get(), mod2.get()];
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(['module1', 'module2'], $result);
    }
}
