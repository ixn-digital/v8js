<?php
/**
 * Test ES module functionality with import/export
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ModuleLoaderTest extends PHPIntegrationTest
{
    /**
     * Test basic ES module import
     */
    public static function testBasicModuleLoader()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'subdir/module.mjs') {
                return 'export const value = 99;';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import { value } from "./subdir/module.mjs";
            export { value };
        ', 'main.mjs');
        
        self::assertEquals(99, $ns->value);
    }
    
    /**
     * Test replacing module loader
     */
    public static function testReplaceModuleLoader()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Set first loader
        $v8->setModuleLoader(function($module) {
            if ($module === 'test1.mjs') {
                return 'export const version = 1;';
            }
            return null;
        });
        
        $ns1 = $v8->executeModule('import { version } from "test1.mjs"; export { version };', 'main1.mjs');
        self::assertEquals(1, $ns1->version);
        
        // Replace loader
        $v8->setModuleLoader(function($module) {
            if ($module === 'test2.mjs') {
                return 'export const version = 2;';
            }
            return null;
        });
        
        $ns2 = $v8->executeModule('import { version } from "test2.mjs"; export { version };', 'main2.mjs');
        self::assertEquals(2, $ns2->version);
    }
    
    /**
     * Test module normaliser
     */
    public static function testModuleNormaliser()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Normaliser that adds .mjs extension
        $v8->setModuleResolver(function($base, $module) {
            if (strpos($module, '.mjs') === false) {
                return $module . '.mjs';
            }
            return $module;
        });
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'mymodule.mjs') {
                return 'export const name = "normalized";';
            }
            return null;
        });
        
        $ns = $v8->executeModule('import { name } from "mymodule"; export { name };', 'main.mjs');
        self::assertEquals('normalized', $ns->name);
    }
    
    /**
     * Test module loader with path resolution
     */
    public static function testModulePathResolution()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'utils/math.mjs' => 'export const add = (a, b) => a + b;',
            'utils/string.mjs' => 'export const upper = (s) => s.toUpperCase();',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { add } from "utils/math.mjs";
            import { upper } from "utils/string.mjs";
            export const sum = add(2, 3);
            export const uppercased = upper("hello");
        ', 'main.mjs');
        
        self::assertEquals(5, $ns->sum);
        self::assertEquals('HELLO', $ns->uppercased);
    }
    
    /**
     * Test module with PHP callback
     */
    public static function testModuleWithPHPCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->phpFunction = function($x) {
            return $x * 2;
        };
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'bridge.mjs') {
                return 'export const callPHP = (x) => PHP.phpFunction(x);';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import { callPHP } from "bridge.mjs";
            export const result = callPHP(21);
        ', 'main.mjs');
        
        self::assertEquals(42, $ns->result);
    }
    
    /**
     * Test module with default export
     */
    public static function testModuleReturningObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'config.mjs') {
                return 'export default { host: "localhost", port: 8080 };';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import config from "config.mjs";
            export const url = config.host + ":" + config.port;
        ', 'main.mjs');
        
        self::assertEquals('localhost:8080', $ns->url);
    }
    
    /**
     * Test module exporting function
     */
    public static function testModuleReturningFunction()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'factory.mjs') {
                return 'export default (name) => "Hello " + name;';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import greet from "factory.mjs";
            export const greeting = greet("World");
        ', 'main.mjs');
        
        self::assertEquals('Hello World', $ns->greeting);
    }
    
    /**
     * Test module with internal state
     */
    public static function testModuleWithState()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'counter.mjs') {
                return '
                    let count = 0;
                    export const increment = () => ++count;
                    export const get = () => count;
                ';
            }
            return null;
        });
        
        $ns = $v8->executeModule('
            import { increment, get } from "counter.mjs";
            increment();
            increment();
            export const count = get();
        ', 'main.mjs');
        
        self::assertEquals(2, $ns->count);
    }
    
    /**
     * Test module loader that throws exception
     */
    public static function testModuleLoaderException()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'error.mjs') {
                throw new Exception('Module not found');
            }
            return null;
        });
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeModule('import "./error.mjs"; export const x = 1;', 'main.mjs');
        });
    }
    
    /**
     * Test normaliser with relative paths
     */
    public static function testNormaliserRelativePaths()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Normaliser handles relative paths
        $v8->setModuleResolver(function($base, $module) {
            if ($module[0] === '.' && $module[1] === '/') {
                // ./file.mjs relative to base
                // base is already the directory (e.g., "lib" for "lib/index.mjs")
                if (empty($base) || $base === '.') {
                    return substr($module, 2); // Remove ./
                }
                return $base . '/' . substr($module, 2);
            }
            return $module;
        });
        
        $v8->setModuleLoader(function($module) {
            $modules = [
                'lib/index.mjs' => 'import { helper } from "./utils.mjs"; export const value = helper();',
                'lib/utils.mjs' => 'export const helper = () => "works";',
            ];
            return $modules[$module] ?? null;
        });
        
        $ns = $v8->executeModule('
            import { value } from "lib/index.mjs";
            export { value };
        ', 'main.mjs');
        
        self::assertEquals('works', $ns->value);
    }
}
