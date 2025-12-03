<?php
/**
 * Test module loader functionality and CommonJS support
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ModuleLoaderTest extends PHPIntegrationTest
{
    /**
     * Test basic module loader setup
     */
    public static function testBasicModuleLoader()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'test') {
                return 'exports.value = 42;';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var mod = require("test");
            mod.value;
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(42, $result);
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
            return 'exports.version = 1;';
        });
        
        $result1 = $v8->executeString('require("test").version;', 'test1.js', V8Js::FLAG_FORCE_ARRAY);
        self::assertEquals(1, $result1);
        
        // Replace with new loader
        $v8->setModuleLoader(function($module) {
            return 'exports.version = 2;';
        });
        
        $result2 = $v8->executeString('require("test2").version;', 'test2.js', V8Js::FLAG_FORCE_ARRAY);
        self::assertEquals(2, $result2);
    }
    
    /**
     * Test module normaliser
     */
    public static function testModuleNormaliser()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Normaliser that adds .js extension
        $v8->setModuleNormaliser(function($base, $module) {
            if (strpos($module, '.js') === false) {
                return $module . '.js';
            }
            return $module;
        });
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'mymodule.js') {
                return 'exports.name = "normalized";';
            }
            return null;
        });
        
        $result = $v8->executeString('require("mymodule").name;', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        self::assertEquals('normalized', $result);
    }
    
    /**
     * Test module loader with path resolution
     */
    public static function testModulePathResolution()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $modules = [
            'utils/math' => 'exports.add = function(a, b) { return a + b; };',
            'utils/string' => 'exports.upper = function(s) { return s.toUpperCase(); };',
        ];
        
        $v8->setModuleLoader(function($module) use ($modules) {
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var math = require("utils/math");
            var str = require("utils/string");
            [math.add(2, 3), str.upper("hello")];
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(5, $result[0]);
        self::assertEquals('HELLO', $result[1]);
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
            if ($module === 'bridge') {
                return 'exports.callPHP = function(x) { return PHP.phpFunction(x); };';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var bridge = require("bridge");
            bridge.callPHP(21);
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(42, $result);
    }
    
    /**
     * Test module returning object
     */
    public static function testModuleReturningObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'config') {
                return 'module.exports = { host: "localhost", port: 8080 };';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var config = require("config");
            config.host + ":" + config.port;
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('localhost:8080', $result);
    }
    
    /**
     * Test module returning function
     */
    public static function testModuleReturningFunction()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'factory') {
                return 'module.exports = function(name) { return "Hello " + name; };';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var greet = require("factory");
            greet("World");
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('Hello World', $result);
    }
    
    /**
     * Test module with internal state
     */
    public static function testModuleWithState()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'counter') {
                return '
                    var count = 0;
                    exports.increment = function() { return ++count; };
                    exports.get = function() { return count; };
                ';
            }
            return null;
        });
        
        $result = $v8->executeString('
            var counter = require("counter");
            counter.increment();
            counter.increment();
            counter.get();
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals(2, $result);
    }
    
    /**
     * Test module loader that throws exception
     */
    public static function testModuleLoaderException()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->setModuleLoader(function($module) {
            if ($module === 'error') {
                throw new Exception('Module not found');
            }
            return null;
        });
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('require("error");', 'main.js', V8Js::FLAG_FORCE_ARRAY);
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
        $v8->setModuleNormaliser(function($base, $module) {
            if ($module[0] === '.') {
                $basePath = dirname($base);
                return $basePath . '/' . $module;
            }
            return $module;
        });
        
        $v8->setModuleLoader(function($module) {
            $modules = [
                'lib/index' => 'exports.value = require("./utils").helper();',
                'lib/utils' => 'exports.helper = function() { return "works"; };',
            ];
            return $modules[$module] ?? null;
        });
        
        $result = $v8->executeString('
            var lib = require("lib/index");
            lib.value;
        ', 'main.js', V8Js::FLAG_FORCE_ARRAY);
        
        self::assertEquals('works', $result);
    }
}
