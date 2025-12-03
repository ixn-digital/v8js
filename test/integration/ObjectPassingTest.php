<?php
/**
 * Test object passing between PHP and JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ObjectPassingTest extends PHPIntegrationTest
{
    /**
     * Test passing a PHP object to JavaScript and back
     */
    public static function testPassObjectPHPToJSToPHP()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Create a PHP object
        $obj = new stdClass();
        $obj->name = 'John';
        $obj->age = 30;
        
        $v8->myObject = $obj;
        
        // Access the object from JavaScript and return it
        $result = $v8->executeString('
            var obj = PHP.myObject;
            obj.name + " is " + obj.age + " years old";
        ');
        
        self::assertEquals('John is 30 years old', $result);
    }
    
    /**
     * Test modifying PHP object properties from JavaScript
     */
    public static function testModifyObjectFromJS()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $obj = new stdClass();
        $obj->counter = 0;
        
        $v8->obj = $obj;
        
        // Modify from JavaScript
        $v8->executeString('
            PHP.obj.counter = 42;
        ');
        
        self::assertEquals(42, $obj->counter);
    }
    
    /**
     * Test passing an object back from PHP method call
     */
    public static function testObjectReturnedFromPHPMethod()
    {
        self::skipIfNoV8js();
        
        $factory = new class {
            public function getObject() {
                $obj = new stdClass();
                $obj->value = 'test';
                return $obj;
            }
        };
        
        $v8 = new V8Js();
        $v8->factory = $factory;
        
        $result = $v8->executeString('
            var obj = PHP.factory.getObject();
            obj.value;
        ');
        
        self::assertEquals('test', $result);
    }
    
    /**
     * Test passing arrays as objects
     */
    public static function testArrayAsObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->data = ['name' => 'Alice', 'age' => 25];
        
        $result = $v8->executeString('
            PHP.data.name + " " + PHP.data.age;
        ');
        
        self::assertEquals('Alice 25', $result);
    }
    
    /**
     * Test that JavaScript objects can be read from PHP
     */
    public static function testJSObjectReturnedToPHP()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $result = $v8->executeString('
            var obj = { x: 10, y: 20 };
            obj;
        ');
        
        self::assertInstanceOf('V8Object', $result);
        self::assertEquals(10, $result->x);
        self::assertEquals(20, $result->y);
    }
    
    /**
     * Test passing nested objects
     */
    public static function testNestedObjects()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $outer = new stdClass();
        $inner = new stdClass();
        $inner->value = 'nested';
        $outer->inner = $inner;
        
        $v8->obj = $outer;
        
        $result = $v8->executeString('
            PHP.obj.inner.value;
        ');
        
        self::assertEquals('nested', $result);
    }
}
