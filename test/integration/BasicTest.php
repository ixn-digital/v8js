<?php
/**
 * Test basic V8js functionality and data type conversions
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class BasicTest extends PHPIntegrationTest
{
    /**
     * Test basic JavaScript execution
     */
    public static function testBasicExecution()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $result = $v8->executeString('2 + 2');
        
        self::assertEquals(4, $result);
    }
    
    /**
     * Test string passing
     */
    public static function testStringPassing()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->str = 'Hello';
        
        $result = $v8->executeString('PHP.str + " World"');
        
        self::assertEquals('Hello World', $result);
    }
    
    /**
     * Test number types
     */
    public static function testNumberTypes()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->int = 42;
        $v8->float = 3.14;
        
        $result = $v8->executeString('PHP.int + PHP.float');
        
        self::assertEquals(45.14, $result);
    }
    
    /**
     * Test boolean values
     */
    public static function testBooleans()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->trueVal = true;
        $v8->falseVal = false;
        
        $result = $v8->executeString('PHP.trueVal && !PHP.falseVal');
        
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
     * Test array passing
     */
    public static function testArrays()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->arr = [1, 2, 3, 4, 5];
        
        $result = $v8->executeString('
            var sum = 0;
            for (var i = 0; i < PHP.arr.length; i++) {
                sum += PHP.arr[i];
            }
            sum;
        ');
        
        self::assertEquals(15, $result);
    }
    
    /**
     * Test associative array (as object)
     */
    public static function testAssociativeArray()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->data = ['name' => 'Alice', 'age' => 30];
        
        $result = $v8->executeString('PHP.data.name + " is " + PHP.data.age');
        
        self::assertEquals('Alice is 30', $result);
    }
    
    /**
     * Test return values from JavaScript
     */
    public static function testReturnValues()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        // Return string
        $str = $v8->executeString('"test"');
        self::assertEquals('test', $str);
        
        // Return number
        $num = $v8->executeString('123');
        self::assertEquals(123, $num);
        
        // Return boolean
        $bool = $v8->executeString('true');
        self::assertTrue($bool);
        
        // Return null
        $null = $v8->executeString('null');
        self::assertNull($null);
    }
    
    /**
     * Test variable assignment in JavaScript
     */
    public static function testVariableAssignment()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->executeString('var x = 10;');
        $result = $v8->executeString('x * 2');
        
        self::assertEquals(20, $result);
    }
    
    /**
     * Test multiple execute calls
     */
    public static function testMultipleExecutions()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->executeString('var counter = 0;');
        $v8->executeString('counter++;');
        $v8->executeString('counter++;');
        $result = $v8->executeString('counter');
        
        self::assertEquals(2, $result);
    }
}
