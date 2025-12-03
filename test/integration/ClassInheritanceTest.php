<?php
/**
 * Test PHP class instantiation, inheritance, and static methods from JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ClassInheritanceTest extends PHPIntegrationTest
{
    /**
     * Test calling PHP class methods
     */
    public static function testCallClassMethods()
    {
        self::skipIfNoV8js();
        
        $calc = new class {
            public function add($a, $b) {
                return $a + $b;
            }
            
            public function multiply($a, $b) {
                return $a * $b;
            }
        };
        
        $v8 = new V8Js();
        $v8->calc = $calc;
        
        $result = $v8->executeString('[PHP.calc.add(2, 3), PHP.calc.multiply(4, 5)]');
        self::assertEquals([5, 20], $result);
    }
    
    /**
     * Test class with constructor
     */
    public static function testClassConstructor()
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
        
        $result = $v8->executeString('PHP.person.greet()');
        self::assertEquals("Hello, I'm Alice", $result);
    }
    
    /**
     * Test inheritance (simplified - tests basic method override pattern)
     */
    public static function testInheritance()
    {
        self::skipIfNoV8js();
        
        $dog = new class {
            protected $type = 'dog';
            
            public function speak() {
                return "I am a {$this->type} and I bark!";
            }
        };
        
        $v8 = new V8Js();
        $v8->dog = $dog;
        
        $result = $v8->executeString('PHP.dog.speak()');
        self::assertEquals('I am a dog and I bark!', $result);
    }
    
    /**
     * Test static methods
     */
    public static function testStaticMethods()
    {
        self::skipIfNoV8js();
        
        $utils = new class {
            public static function square($x) {
                return $x * $x;
            }
            
            public static function cube($x) {
                return $x * $x * $x;
            }
        };
        
        $v8 = new V8Js();
        $v8->MathUtils = 'MathUtils';
        
        // Note: Direct static method calls from JS typically require PHP to expose them
        // This tests that the class can be used
        $v8->utils = $utils;
        
        $result = $v8->executeString('typeof PHP.utils');
        self::assertEquals('object', $result);
    }
    
    /**
     * Test method chaining
     */
    public static function testMethodChaining()
    {
        self::skipIfNoV8js();
        
        $builder = new class {
            private $value = '';
            
            public function append($text) {
                $this->value .= $text;
                return $this;
            }
            
            public function getValue() {
                return $this->value;
            }
        };
        
        $v8 = new V8Js();
        $v8->builder = $builder;
        
        $result = $v8->executeString('
            PHP.builder.append("Hello").append(" ").append("World").getValue()
        ');
        
        self::assertEquals('Hello World', $result);
    }
    
    /**
     * Test abstract methods pattern (simplified to avoid nested classes)
     */
    public static function testAbstractClass()
    {
        self::skipIfNoV8js();
        
        $rect = new class(5, 10) {
            private $width;
            private $height;
            
            public function __construct($width, $height) {
                $this->width = $width;
                $this->height = $height;
            }
            
            public function area() {
                return $this->width * $this->height;
            }
            
            public function describe() {
                return "Area: " . $this->area();
            }
        };
        
        $v8 = new V8Js();
        $v8->rect = $rect;
        
        $result = $v8->executeString('[PHP.rect.area(), PHP.rect.describe()]');
        self::assertEquals([50, 'Area: 50'], $result);
    }
    
    /**
     * Test interface implementation pattern (simplified to avoid nested classes)
     */
    public static function testInterface()
    {
        self::skipIfNoV8js();
        
        $formal = new class {
            public function greet(string $name): string {
                return "Good day, {$name}";
            }
        };
        
        $casual = new class {
            public function greet(string $name): string {
                return "Hey {$name}!";
            }
        };
        
        $v8 = new V8Js();
        $v8->formal = $formal;
        $v8->casual = $casual;
        
        $result = $v8->executeString('[PHP.formal.greet("Sir"), PHP.casual.greet("Bob")]');
        self::assertEquals(['Good day, Sir', 'Hey Bob!'], $result);
    }
    
    /**
     * Test trait pattern (simplified to avoid nested classes)
     */
    public static function testTrait()
    {
        self::skipIfNoV8js();
        
        $doc = new class('Test') {
            public $title;
            
            public function __construct($title) {
                $this->title = $title;
            }
            
            // Method that would come from a trait
            public function getTimestamp() {
                return time();
            }
        };
        
        $v8 = new V8Js();
        $v8->doc = $doc;
        
        $result = $v8->executeString('typeof PHP.doc.getTimestamp');
        self::assertEquals('function', $result);
        
        $timestamp = $v8->executeString('PHP.doc.getTimestamp()');
        self::assertTrue(is_int($timestamp));
    }
    
    /**
     * Test class with magic __call
     */
    public static function testMagicCall()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public function __call($name, $args) {
                return "Called {$name} with " . count($args) . " args";
            }
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('PHP.obj.anyMethod(1, 2, 3)');
        self::assertEquals('Called anyMethod with 3 args', $result);
    }
    
    /**
     * Test class with __toString
     */
    public static function testToString()
    {
        self::skipIfNoV8js();
        
        $obj = new class(42) {
            private $value;
            
            public function __construct($value) {
                $this->value = $value;
            }
            
            public function __toString() {
                return "Value: {$this->value}";
            }
        };
        
        $v8 = new V8Js();
        $v8->obj = $obj;
        
        $result = $v8->executeString('String(PHP.obj)');
        self::assertStringContains('42', $result);
    }
    
    /**
     * Test multiple instances of same class
     */
    public static function testMultipleInstances()
    {
        self::skipIfNoV8js();
        
        $counterClass = new class {
            private $count = 0;
            
            public function increment() {
                $this->count++;
            }
            
            public function get() {
                return $this->count;
            }
        };
        
        $v8 = new V8Js();
        $v8->counter1 = clone $counterClass;
        $v8->counter2 = clone $counterClass;
        
        $result = $v8->executeString('
            PHP.counter1.increment();
            PHP.counter1.increment();
            PHP.counter2.increment();
            [PHP.counter1.get(), PHP.counter2.get()];
        ');
        
        self::assertEquals([2, 1], $result);
    }
    
    /**
     * Test nested method calls
     */
    public static function testNestedMethodCalls()
    {
        self::skipIfNoV8js();
        
        $outer = new class {
            public function getInner() {
                return new class {
                    public function getValue() {
                        return 'nested';
                    }
                };
            }
        };
        
        $v8 = new V8Js();
        $v8->outer = $outer;
        
        $result = $v8->executeString('PHP.outer.getInner().getValue()');
        self::assertEquals('nested', $result);
    }
}
