<?php
/**
 * Test generator support between PHP and JavaScript
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class GeneratorTest extends PHPIntegrationTest
{
    /**
     * Test PHP generator passed to JavaScript
     */
    public static function testPHPGeneratorToJS()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->getNumbers = function() {
            yield 1;
            yield 2;
            yield 3;
        };
        
        $result = $v8->executeString('
            var gen = PHP.getNumbers();
            var values = [];
            var result;
            while (!(result = gen.next()).done) {
                values.push(result.value);
            }
            values;
        ');
        
        self::assertEquals([1, 2, 3], $result);
    }
    
    /**
     * Test generator with keys
     */
    public static function testGeneratorWithKeys()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->getKeyValues = function() {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'c' => 3;
        };
        
        $result = $v8->executeString('
            var gen = PHP.getKeyValues();
            var result = gen.next();
            result.value;
        ');
        
        self::assertEquals(1, $result);
    }
    
    /**
     * Test generator iteration
     */
    public static function testGeneratorIteration()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->range = function($start, $end) {
            for ($i = $start; $i <= $end; $i++) {
                yield $i;
            }
        };
        
        $result = $v8->executeString('
            var gen = PHP.range(5, 8);
            var sum = 0;
            for (var item of gen) {
                sum += item;
            }
            sum;
        ');
        
        self::assertEquals(26, $result); // 5 + 6 + 7 + 8
    }
    
    /**
     * Test generator that yields objects
     */
    public static function testGeneratorYieldingObjects()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->getObjects = function() {
            $obj1 = new stdClass();
            $obj1->id = 1;
            yield $obj1;
            
            $obj2 = new stdClass();
            $obj2->id = 2;
            yield $obj2;
        };
        
        $result = $v8->executeString('
            var gen = PHP.getObjects();
            var first = gen.next().value;
            var second = gen.next().value;
            [first.id, second.id];
        ');
        
        self::assertEquals([1, 2], $result);
    }
    
    /**
     * Test generator with early return
     */
    public static function testGeneratorEarlyReturn()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->getValues = function() {
            yield 1;
            yield 2;
            return;
            yield 3; // Should not be reached
        };
        
        $result = $v8->executeString('
            var gen = PHP.getValues();
            var values = [];
            for (var val of gen) {
                values.push(val);
            }
            values;
        ');
        
        self::assertEquals([1, 2], $result);
    }
    
    /**
     * Test infinite generator with break
     */
    public static function testInfiniteGenerator()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->infinite = function() {
            $i = 0;
            while (true) {
                yield $i++;
            }
        };
        
        $result = $v8->executeString('
            var gen = PHP.infinite();
            var values = [];
            var result;
            while ((result = gen.next()) && values.length < 5) {
                values.push(result.value);
            }
            values;
        ');
        
        self::assertEquals([0, 1, 2, 3, 4], $result);
    }
    
    /**
     * Test generator with conditional yields
     */
    public static function testConditionalGenerator()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->evenNumbers = function($max) {
            for ($i = 0; $i <= $max; $i++) {
                if ($i % 2 === 0) {
                    yield $i;
                }
            }
        };
        
        $result = $v8->executeString('
            var gen = PHP.evenNumbers(10);
            var values = [];
            for (var val of gen) {
                values.push(val);
            }
            values;
        ');
        
        self::assertEquals([0, 2, 4, 6, 8, 10], $result);
    }
    
    /**
     * Test generator state preservation
     */
    public static function testGeneratorState()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->fibonacci = function() {
            $a = 0;
            $b = 1;
            yield $a;
            yield $b;
            while (true) {
                $c = $a + $b;
                yield $c;
                $a = $b;
                $b = $c;
            }
        };
        
        $result = $v8->executeString('
            var gen = PHP.fibonacci();
            var values = [];
            for (var i = 0; i < 7; i++) {
                values.push(gen.next().value);
            }
            values;
        ');
        
        self::assertEquals([0, 1, 1, 2, 3, 5, 8], $result);
    }
    
    /**
     * Test multiple generators independently
     */
    public static function testMultipleGenerators()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->counter = function() {
            for ($i = 1; $i <= 3; $i++) {
                yield $i;
            }
        };
        
        $result = $v8->executeString('
            var gen1 = PHP.counter();
            var gen2 = PHP.counter();
            
            var a = gen1.next().value;
            var b = gen2.next().value;
            var c = gen1.next().value;
            var d = gen2.next().value;
            
            [a, b, c, d];
        ');
        
        self::assertEquals([1, 1, 2, 2], $result);
    }
}
