<?php
/**
 * Test passing various PHP data types to JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class TypeConversionTest extends PHPIntegrationTest
{
    /**
     * Test DateTime object conversion
     */
    public static function testDateTimeObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $dt = new DateTime('2025-12-03 10:30:00', new DateTimeZone('UTC'));
        $v8->date = $dt;
        
        // DateTime should be accessible as object
        $result = $v8->executeString('typeof PHP.date');
        self::assertEquals('object', $result);
        
        // Can call methods
        $timestamp = $v8->executeString('PHP.date.getTimestamp()');
        self::assertEquals($dt->getTimestamp(), $timestamp);
    }
    
    /**
     * Test SplFixedArray conversion
     */
    public static function testSplFixedArray()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $arr = new SplFixedArray(3);
        $arr[0] = 10;
        $arr[1] = 20;
        $arr[2] = 30;
        
        $v8->fixedArray = $arr;
        
        $result = $v8->executeString('PHP.fixedArray[1]');
        self::assertEquals(20, $result);
    }
    
    /**
     * Test stdClass object
     */
    public static function testStdClass()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $obj = new stdClass();
        $obj->name = 'Test';
        $obj->value = 42;
        
        $v8->obj = $obj;
        
        $result = $v8->executeString('[PHP.obj.name, PHP.obj.value]');
        self::assertEquals('Test', $result[0]);
        self::assertEquals(42, $result[1]);
    }
    
    /**
     * Test custom class with properties
     */
    public static function testCustomClass()
    {
        self::skipIfNoV8js();
        
        $person = new class('Alice', 30) {
            public $name;
            public $age;
            
            public function __construct($name, $age) {
                $this->name = $name;
                $this->age = $age;
            }
            
            public function greet() {
                return "Hello, I'm {$this->name}";
            }
        };
        
        $v8 = new V8Js();
        $v8->person = $person;
        
        $name = $v8->executeString('PHP.person.name');
        self::assertEquals('Alice', $name);
        
        $greeting = $v8->executeString('PHP.person.greet()');
        self::assertEquals("Hello, I'm Alice", $greeting);
    }
    
    /**
     * Test ArrayObject conversion
     */
    public static function testArrayObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $arr = new ArrayObject(['a' => 1, 'b' => 2, 'c' => 3]);
        $v8->arrayObj = $arr;
        
        $result = $v8->executeString('PHP.arrayObj.b');
        self::assertEquals(2, $result);
    }
    
    /**
     * Test closure conversion
     */
    public static function testClosure()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->add = function($a, $b) {
            return $a + $b;
        };
        
        $result = $v8->executeString('PHP.add(10, 20)');
        self::assertEquals(30, $result);
    }
    
    /**
     * Test numeric types
     */
    public static function testNumericTypes()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->intVal = 42;
        $v8->floatVal = 3.14159;
        $v8->negativeInt = -100;
        $v8->zero = 0;
        
        $result = $v8->executeString('
            [PHP.intVal, PHP.floatVal, PHP.negativeInt, PHP.zero]
        ');
        
        self::assertEquals(42, $result[0]);
        self::assertEquals(3.14159, $result[1]);
        self::assertEquals(-100, $result[2]);
        self::assertEquals(0, $result[3]);
    }
    
    /**
     * Test boolean conversion
     */
    public static function testBooleans()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->trueVal = true;
        $v8->falseVal = false;
        
        $result = $v8->executeString('PHP.trueVal === true && PHP.falseVal === false');
        self::assertTrue($result);
    }
    
    /**
     * Test null value
     */
    public static function testNull()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->nullVal = null;
        
        $result = $v8->executeString('PHP.nullVal === null');
        self::assertTrue($result);
    }
    
    /**
     * Test string encoding
     */
    public static function testStringEncoding()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->ascii = 'Hello';
        $v8->unicode = '你好世界';
        $v8->emoji = '🚀🎉';
        
        $result = $v8->executeString('[PHP.ascii, PHP.unicode, PHP.emoji]');
        self::assertEquals('Hello', $result[0]);
        self::assertEquals('你好世界', $result[1]);
        self::assertEquals('🚀🎉', $result[2]);
    }
    
    /**
     * Test empty string and whitespace
     */
    public static function testEmptyStrings()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->empty = '';
        $v8->space = ' ';
        $v8->newline = "\n";
        
        $lengths = $v8->executeString('[PHP.empty.length, PHP.space.length, PHP.newline.length]');
        self::assertEquals(0, $lengths[0]);
        self::assertEquals(1, $lengths[1]);
        self::assertEquals(1, $lengths[2]);
    }
    
    /**
     * Test associative arrays
     */
    public static function testAssociativeArrays()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->data = [
            'name' => 'John',
            'age' => 25,
            'active' => true
        ];
        
        $result = $v8->executeString('
            [PHP.data.name, PHP.data.age, PHP.data.active]
        ');
        
        self::assertEquals('John', $result[0]);
        self::assertEquals(25, $result[1]);
        self::assertTrue($result[2]);
    }
    
    /**
     * Test indexed arrays
     */
    public static function testIndexedArrays()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->numbers = [10, 20, 30, 40, 50];
        
        $result = $v8->executeString('
            PHP.numbers.reduce((sum, n) => sum + n, 0)
        ');
        
        self::assertEquals(150, $result);
    }
    
    /**
     * Test nested arrays
     */
    public static function testNestedArrays()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->matrix = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9]
        ];
        
        $result = $v8->executeString('PHP.matrix[1][2]');
        self::assertEquals(6, $result);
    }
    
    /**
     * Test mixed array (keys and indices)
     */
    public static function testMixedArray()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->mixed = [
            0 => 'first',
            'key' => 'value',
            1 => 'second'
        ];
        
        $first = $v8->executeString('PHP.mixed[0]');
        $key = $v8->executeString('PHP.mixed.key');
        
        self::assertEquals('first', $first);
        self::assertEquals('value', $key);
    }
}
