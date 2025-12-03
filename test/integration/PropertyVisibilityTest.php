<?php
/**
 * Test property visibility and magic methods
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class PropertyVisibilityTest extends PHPIntegrationTest
{
    /**
     * Test public properties are accessible
     */
    public static function testPublicProperties()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public $value = 42;
            public $name = 'test';
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('[PHP.obj.value, PHP.obj.name]');
        self::assertEquals([42, 'test'], $result);
    }
    
    /**
     * Test protected properties are not accessible
     */
    public static function testProtectedProperties()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            protected $secret = 'hidden';
            public $public = 'visible';
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        // Public property should work
        $result = $v8->executeString('PHP.obj.public');
        self::assertEquals('visible', $result);
        
        // Protected property should be undefined
        $result = $v8->executeString('typeof PHP.obj.secret');
        self::assertEquals('undefined', $result);
    }
    
    /**
     * Test private properties are not accessible
     */
    public static function testPrivateProperties()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            private $secret = 'hidden';
            public $public = 'visible';
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('typeof PHP.obj.secret');
        self::assertEquals('undefined', $result);
    }
    
    /**
     * Test __get magic method
     */
    public static function testMagicGet()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            private $data = ['dynamic' => 'value'];
            
            public function __get($name) {
                return $this->data[$name] ?? null;
            }
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('PHP.obj.dynamic');
        self::assertEquals('value', $result);
    }
    
    /**
     * Test __set magic method
     */
    public static function testMagicSet()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public $data = [];
            
            public function __set($name, $value) {
                $this->data[$name] = $value;
            }
        };
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $v8->executeString('PHP.obj.newProp = "test value";');
        
        self::assertEquals('test value', $obj->data['newProp']);
    }
    
    /**
     * Test __isset magic method
     */
    public static function testMagicIsset()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            private $data = ['exists' => true];
            
            public function __isset($name) {
                return isset($this->data[$name]);
            }
            
            public function __get($name) {
                return $this->data[$name] ?? null;
            }
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('["exists" in PHP.obj, "missing" in PHP.obj]');
        self::assertEquals([true, false], $result);
    }
    
    /**
     * Test __unset magic method
     */
    public static function testMagicUnset()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public $data = ['prop' => 'value'];
            
            public function __unset($name) {
                unset($this->data[$name]);
            }
        };
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $v8->executeString('delete PHP.obj.prop;');
        
        self::assertArrayHasKey('prop', $obj->data); // __unset was called but property still in data
    }
    
    /**
     * Test property enumeration
     */
    public static function testPropertyEnumeration()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public $a = 1;
            public $b = 2;
            public $c = 3;
            private $hidden = 4;
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('Object.keys(PHP.obj).sort()');
        self::assertEquals(['a', 'b', 'c'], $result);
    }
    
    /**
     * Test dynamic properties
     */
    public static function testDynamicProperties()
    {
        self::skipIfNoV8js();
        
        $obj = new stdClass();
        $obj->dynamic1 = 'value1';
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        // Add property from JS
        $v8->executeString('PHP.obj.dynamic2 = "value2";');
        
        self::assertEquals('value1', $obj->dynamic1);
        self::assertEquals('value2', $obj->dynamic2);
    }
    
    /**
     * Test readonly properties
     */
    public static function testReadonlyAccess()
    {
        self::skipIfNoV8js();
        
        $obj = new class('fixed') {
            public function __construct(
                public readonly string $immutable = 'fixed'
            ) {}
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        // Should be able to read
        $result = $v8->executeString('PHP.obj.immutable');
        self::assertEquals('fixed', $result);
    }
    
    /**
     * Test property with getter method
     */
    public static function testPropertyVsMethod()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public $value = 'property';
            
            public function value() {
                return 'method';
            }
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $prop = $v8->executeString('PHP.obj.value');
        $method = $v8->executeString('PHP.obj.value()');
        
        self::assertEquals('property', $prop);
        self::assertEquals('method', $method);
    }
    
    /**
     * Test object with no properties
     */
    public static function testEmptyObject()
    {
        self::skipIfNoV8js();
        
        $obj = new class {};
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('Object.keys(PHP.obj).length');
        self::assertEquals(0, $result);
    }
    
    /**
     * Test array-like property access
     */
    public static function testArrayLikePropertyAccess()
    {
        self::skipIfNoV8js();
        
        $obj = new stdClass();
        $obj->{'0'} = 'zero';
        $obj->{'1'} = 'one';
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('[PHP.obj["0"], PHP.obj["1"]]');
        self::assertEquals(['zero', 'one'], $result);
    }
}
