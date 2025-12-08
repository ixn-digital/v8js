<?php
/**
 * Test loading multiple ES modules in a single V8Js instance
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
            'math.mjs' => 'export const add = (a, b) => a + b;',
            'string.mjs' => 'export const concat = (a, b) => a + b;',
            'array.mjs' => 'export const sum = (arr) => arr.reduce((a, b) => a + b, 0);',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { add } from "math.mjs";
            import { concat } from "string.mjs";
            import { sum } from "array.mjs";
            
            export const addResult = add(2, 3);
            export const concatResult = concat("Hello", "World");
            export const sumResult = sum([1, 2, 3, 4, 5]);
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(5, $ns['addResult']);
        self::assertEquals('HelloWorld', $ns['concatResult']);
        self::assertEquals(15, $ns['sumResult']);
    }
    
    /**
     * Test module caching - same module imported twice
     */
    public static function testModuleCaching()
    {
        self::skipIfNoV8js();
        
        $loadCount = 0;
        
        $v8 = new V8Js();
        $v8->setModuleLoader(function($module) use (&$loadCount) {
            if ($module === 'cached.mjs') {
                $loadCount++;
                return 'export const value = 42;';
            }
            return null;
        });
        
        $v8->executeModule('
            import { value as v1 } from "cached.mjs";
            import { value as v2 } from "cached.mjs";
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
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
            if ($module === 'stateful.mjs') {
                return '
                    let state = { counter: 0 };
                    export const increment = () => { state.counter++; };
                    export const get = () => state.counter;
                ';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import { increment as inc1, get as get1 } from "stateful.mjs";
            import { increment as inc2, get as get2 } from "stateful.mjs";
            
            inc1();
            inc1();
            
            // Both imports should share the same state
            export const count = get2();
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(2, $ns['count']);
    }
    
    /**
     * Test interdependent modules
     */
    public static function testInterdependentModules()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'util.mjs' => 'export const double = (x) => x * 2;',
            'calculator.mjs' => '
                import { double } from "util.mjs";
                export const compute = (x) => double(x) + 10;
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { compute } from "calculator.mjs";
            export const result = compute(5);
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(20, $ns['result']); // (5 * 2) + 10
    }
    
    /**
     * Test circular module dependencies
     */
    public static function testCircularDependencies()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'a.mjs' => '
                export const name = "moduleA";
                import { name as bName } from "b.mjs";
                export const getBName = () => bName;
            ',
            'b.mjs' => '
                export const name = "moduleB";
                import { name as aName } from "a.mjs";
                export const getAName = () => aName;
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { name as aName, getBName } from "a.mjs";
            import { name as bName, getAName } from "b.mjs";
            export const aNameValue = aName;
            export const bNameValue = bName;
            export const bFromA = getBName();
            export const aFromB = getAName();
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('moduleA', $ns['aNameValue']);
        self::assertEquals('moduleB', $ns['bNameValue']);
        self::assertEquals('moduleB', $ns['bFromA']);
        self::assertEquals('moduleA', $ns['aFromB']);
    }
    
    /**
     * Test many modules
     */
    public static function testManyModules()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if (preg_match('/^module(\d+)\.mjs$/', $module, $matches)) {
                $num = $matches[1];
                return "export const value = {$num};";
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import { value as v1 } from "module1.mjs";
            import { value as v2 } from "module2.mjs";
            import { value as v3 } from "module3.mjs";
            import { value as v4 } from "module4.mjs";
            import { value as v5 } from "module5.mjs";
            import { value as v6 } from "module6.mjs";
            import { value as v7 } from "module7.mjs";
            import { value as v8 } from "module8.mjs";
            import { value as v9 } from "module9.mjs";
            import { value as v10 } from "module10.mjs";
            
            export const sum = v1 + v2 + v3 + v4 + v5 + v6 + v7 + v8 + v9 + v10;
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(55, $ns['sum']); // Sum of 1..10
    }
    
    /**
     * Test module with nested imports
     */
    public static function testNestedRequires()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'level1.mjs' => '
                import { value } from "level2.mjs";
                export const load = () => value;
            ',
            'level2.mjs' => '
                import { data } from "level3.mjs";
                export const value = data;
            ',
            'level3.mjs' => '
                export const data = "deep";
            ',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { load } from "level1.mjs";
            export const result = load();
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('deep', $ns['result']);
    }
    
    /**
     * Test named exports vs default export
     */
    public static function testExportsVsModuleExports()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'named.mjs' => 'export const a = 1; export const b = 2;',
            'default.mjs' => 'export default { x: 10, y: 20 };',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { a, b } from "named.mjs";
            import mod from "default.mjs";
            export const val1 = a;
            export const val2 = b;
            export const val3 = mod.x;
            export const val4 = mod.y;
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(1, $ns['val1']);
        self::assertEquals(2, $ns['val2']);
        self::assertEquals(10, $ns['val3']);
        self::assertEquals(20, $ns['val4']);
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
            'eager.mjs' => 'PHP.track("eager"); export const value = 1;',
            'lazy.mjs' => 'PHP.track("lazy"); export const value = 2;',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        // Only import eager module
        $v8->executeModule('
            import { value } from "eager.mjs";
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
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
            'mod1.mjs' => 'const secret = "module1"; export const get = () => secret;',
            'mod2.mjs' => 'const secret = "module2"; export const get = () => secret;',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { get as get1 } from "mod1.mjs";
            import { get as get2 } from "mod2.mjs";
            export const secret1 = get1();
            export const secret2 = get2();
        ', 'main.mjs', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('module1', $ns['secret1']);
        self::assertEquals('module2', $ns['secret2']);
    }
}
