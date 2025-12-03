<?php
/**
 * Test ArrayAccess interface integration with JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class ArrayAccessTest extends PHPIntegrationTest
{
    /**
     * Test ArrayAccess objects in JavaScript
     */
    public static function testArrayAccessBasic()
    {
        self::skipIfNoV8js();
        self::skipIf(!ini_get('v8js.use_array_access'), 'v8js.use_array_access must be enabled');
        
        $arr = new class implements ArrayAccess, Countable {
            private $data = [1, 2, 3, 4, 5];
            
            public function offsetExists($offset): bool {
                return isset($this->data[$offset]);
            }
            
            public function offsetGet(mixed $offset): mixed {
                return $this->data[$offset] ?? null;
            }
            
            public function offsetSet(mixed $offset, mixed $value): void {
                $this->data[$offset] = $value;
            }
            
            public function offsetUnset(mixed $offset): void {
                unset($this->data[$offset]);
            }
            
            public function count(): int {
                return count($this->data);
            }
        };
        
        $v8 = new V8Js();
        $v8->arr = $arr;
        
        $result = $v8->executeString('PHP.arr[2];');
        self::assertEquals(3, $result);
    }
    
    /**
     * Test ArrayAccess with map/filter/reduce
     */
    public static function testArrayAccessWithJSMethods()
    {
        self::skipIfNoV8js();
        self::skipIf(!ini_get('v8js.use_array_access'), 'v8js.use_array_access must be enabled');
        
        $numbers = new class implements ArrayAccess, Countable {
            private $data = [10, 20, 30, 40, 50];
            
            public function offsetExists($offset): bool {
                return isset($this->data[$offset]);
            }
            
            public function offsetGet(mixed $offset): mixed {
                return $this->data[$offset] ?? null;
            }
            
            public function offsetSet(mixed $offset, mixed $value): void {
                $this->data[$offset] = $value;
            }
            
            public function offsetUnset(mixed $offset): void {
                unset($this->data[$offset]);
            }
            
            public function count(): int {
                return count($this->data);
            }
        };
        
        $v8 = new V8Js();
        $v8->numbers = $numbers;
        
        // Test that we can iterate and sum
        $result = $v8->executeString('
            var sum = 0;
            for (var i = 0; i < PHP.numbers.length; i++) {
                sum += PHP.numbers[i];
            }
            sum;
        ');
        
        self::assertEquals(150, $result);
    }
    
    /**
     * Test writing to ArrayAccess from JavaScript
     */
    public static function testArrayAccessWrite()
    {
        self::skipIfNoV8js();
        self::skipIf(!ini_get('v8js.use_array_access'), 'v8js.use_array_access must be enabled');
        
        $arr = new class implements ArrayAccess, Countable {
            public $data = [];
            
            public function offsetExists($offset): bool {
                return isset($this->data[$offset]);
            }
            
            public function offsetGet(mixed $offset): mixed {
                return $this->data[$offset] ?? null;
            }
            
            public function offsetSet(mixed $offset, mixed $value): void {
                $this->data[$offset] = $value;
            }
            
            public function offsetUnset(mixed $offset): void {
                unset($this->data[$offset]);
            }
            
            public function count(): int {
                return count($this->data);
            }
        };
        $v8 = new V8Js();
        $v8->arr = $arr;
        
        $v8->executeString('
            PHP.arr[0] = 100;
            PHP.arr[1] = 200;
            PHP.arr[2] = 300;
        ');
        
        self::assertCount(3, $arr->data);
        self::assertEquals(100, $arr->data[0]);
        self::assertEquals(200, $arr->data[1]);
        self::assertEquals(300, $arr->data[2]);
    }
    
    /**
     * Test ArrayAccess with custom logic
     */
    public static function testArrayAccessCustomLogic()
    {
        self::skipIfNoV8js();
        self::skipIf(!ini_get('v8js.use_array_access'), 'v8js.use_array_access must be enabled');
        
        // Array that returns reversed index values
        $reversed = new class implements ArrayAccess, Countable {
            private $size = 10;
            
            public function offsetExists($offset): bool {
                return $offset >= 0 && $offset < $this->size;
            }
            
            public function offsetGet(mixed $offset): mixed {
                return $this->size - 1 - $offset;
            }
            
            public function offsetSet(mixed $offset, mixed $value): void {
                // Not implemented
            }
            
            public function offsetUnset(mixed $offset): void {
                // Not implemented
            }
            
            public function count(): int {
                return $this->size;
            }
        };
        
        $v8 = new V8Js();
        $v8->reversed = $reversed;
        
        // Index 0 should return 9, index 9 should return 0
        $result = $v8->executeString('
            [PHP.reversed[0], PHP.reversed[5], PHP.reversed[9]];
        ');
        
        self::assertEquals(9, $result[0]);
        self::assertEquals(4, $result[1]);
        self::assertEquals(0, $result[2]);
    }
}
