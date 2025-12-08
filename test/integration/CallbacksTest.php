<?php
/**
 * Test JavaScript callbacks and closures from PHP
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class CallbacksTest extends PHPIntegrationTest
{
    /**
     * Test calling a JavaScript function from PHP
     */
    public static function testCallJSFunctionFromPHP()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->callback = function($x) {
            return $x * 2;
        };
        
        $result = $v8->executeString('PHP.callback(21);');
        
        self::assertEquals(42, $result);
    }
    
    /**
     * Test JavaScript callback with object parameter
     */
    public static function testJSCallbackWithObject()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $called = false;
        $receivedValue = null;
        
        $v8->callback = function($obj) use (&$called, &$receivedValue) {
            $called = true;
            $receivedValue = $obj->value;
            return 'processed';
        };
        
        $result = $v8->executeString('
            PHP.callback({ value: "test" });
        ');
        
        self::assertTrue($called);
        self::assertEquals('test', $receivedValue);
        self::assertEquals('processed', $result);
    }
    
    /**
     * Test that V8Function can be identified
     */
    public static function testV8FunctionType()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->test = function($callback) {
            return is_a($callback, 'V8Function');
        };
        
        $result = $v8->executeString('
            PHP.test(function(x) { return x + 1; });
        ');
        
        self::assertTrue($result);
    }
    
    /**
     * Test calling a V8Function from PHP
     */
    public static function testCallV8Function()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $jsFunc = null;
        
        $v8->capture = function($func) use (&$jsFunc) {
            $jsFunc = $func;
        };
        
        $v8->executeString('
            PHP.capture(function(a, b) { return a + b; });
        ');
        
        self::assertNotNull($jsFunc);
        self::assertInstanceOf('V8Function', $jsFunc);
        
        // Call the captured function
        $result = $jsFunc(10, 20);
        self::assertEquals(30, $result);
    }
    
    /**
     * Test JavaScript object with callback method
     */
    public static function testJSObjectWithCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->test = function($obj) {
            return $obj->method('hello');
        };
        
        $result = $v8->executeString('
            PHP.test({
                method: function(str) {
                    return str + " world";
                }
            });
        ');
        
        self::assertEquals('hello world', $result);
    }
    
    /**
     * Test closure with captured variables
     */
    public static function testClosureWithCapturedVars()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $multiplier = 3;
        
        $v8->multiply = function($x) use ($multiplier) {
            return $x * $multiplier;
        };
        
        $result = $v8->executeString('PHP.multiply(7);');
        
        self::assertEquals(21, $result);
    }
    
    /**
     * Test multiple callbacks
     */
    public static function testMultipleCallbacks()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $calls = [];
        
        $v8->log = function($msg) use (&$calls) {
            $calls[] = $msg;
        };
        
        $v8->executeString('
            PHP.log("first");
            PHP.log("second");
            PHP.log("third");
        ');
        
        self::assertCount(3, $calls);
        self::assertEquals(['first', 'second', 'third'], $calls);
    }
    
    /**
     * Test callback that throws exception
     */
    public static function testCallbackException()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->throwError = function() {
            throw new Exception('PHP error');
        };
        
        self::assertThrows(V8JsException::class, function() use ($v8) {
            $v8->executeString('PHP.throwError();');
        });
    }
    
    /**
     * Test static method callback
     */
    public static function testStaticMethodCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->processor = function($str, $num) {
            return self::staticProcessor($str, $num);
        };
        
        $result = $v8->executeString('PHP.processor("test", 42);');
        self::assertEquals('test-42', $result);
    }
    
    private static function staticProcessor($str, $num)
    {
        return $str . '-' . $num;
    }
    
    /**
     * Test instance method callback with array syntax
     */
    public static function testInstanceMethodCallback()
    {
        self::skipIfNoV8js();
        
        $helper = new class {
            public function process($x) {
                return $x * 10;
            }
        };
        
        $v8 = new V8Js();
        $v8->callback = function($x) use ($helper) {
            return $helper->process($x);
        };
        
        $result = $v8->executeString('PHP.callback(5);');
        self::assertEquals(50, $result);
    }
    
    /**
     * Test $this method callback
     */
    public static function testThisMethodCallback()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            private $value = 100;
            
            public function getValue() {
                return $this->value;
            }
            
            public function addValue($x) {
                return $this->value + $x;
            }
            
            public function getCallbacks() {
                return [
                    'getValue' => [$this, 'getValue'],
                    'addValue' => [$this, 'addValue']
                ];
            }
        };
        
        $v8 = new V8Js();
        $v8->getValue = function() use ($obj) {
            return $obj->getValue();
        };
        $v8->addValue = function($x) use ($obj) {
            return $obj->addValue($x);
        };
        
        $result1 = $v8->executeString('PHP.getValue();');
        $result2 = $v8->executeString('PHP.addValue(50);');
        
        self::assertEquals(100, $result1);
        self::assertEquals(150, $result2);
    }
    
    /**
     * Test callable string (function name)
     */
    public static function testCallableString()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        $v8->strlen = function($str) { return strlen($str); };
        $v8->max = function(...$args) { return max(...$args); };
        
        $result1 = $v8->executeString('PHP.strlen("hello");');
        $result2 = $v8->executeString('PHP.max(10, 25, 15);');
        
        self::assertEquals(5, $result1);
        self::assertEquals(25, $result2);
    }
    
    /**
     * Test anonymous function with complex logic
     */
    public static function testAnonymousFunctionComplex()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->fibonacci = function($n) {
            if ($n <= 1) return $n;
            $a = 0;
            $b = 1;
            for ($i = 2; $i <= $n; $i++) {
                $temp = $a + $b;
                $a = $b;
                $b = $temp;
            }
            return $b;
        };
        
        $result = $v8->executeString('PHP.fibonacci(10);');
        self::assertEquals(55, $result);
    }
    
    /**
     * Test arrow function (PHP 7.4+)
     */
    public static function testArrowFunction()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $multiplier = 5;
        $v8->multiply = fn($x) => $x * $multiplier;
        
        $result = $v8->executeString('PHP.multiply(8);');
        self::assertEquals(40, $result);
    }
    
    /**
     * Test callback with reference parameter
     */
    public static function testCallbackWithReference()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $counter = 0;
        $v8->increment = function() use (&$counter) {
            return ++$counter;
        };
        
        $v8->executeString('
            PHP.increment();
            PHP.increment();
            PHP.increment();
        ');
        
        self::assertEquals(3, $counter);
    }
    
    /**
     * Test callback returning different types
     */
    public static function testCallbackReturnTypes()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->returnNull = function() { return null; };
        $v8->returnBool = function() { return true; };
        $v8->returnInt = function() { return 42; };
        $v8->returnFloat = function() { return 3.14; };
        $v8->returnString = function() { return 'hello'; };
        $v8->returnArray = function() { return [1, 2, 3]; };
        $v8->returnObject = function() { return (object)['key' => 'value']; };
        
        $results = $v8->executeString('[
            PHP.returnNull(),
            PHP.returnBool(),
            PHP.returnInt(),
            PHP.returnFloat(),
            PHP.returnString(),
            PHP.returnArray(),
            typeof PHP.returnObject()
        ]');
        
        self::assertNull($results[0]);
        self::assertTrue($results[1]);
        self::assertEquals(42, $results[2]);
        self::assertEquals(3.14, $results[3]);
        self::assertEquals('hello', $results[4]);
        self::assertEquals([1, 2, 3], $results[5]);
        self::assertEquals('object', $results[6]);
    }
    
    /**
     * Test callback with variadic parameters
     */
    public static function testCallbackVariadic()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->sum = function(...$args) {
            return array_sum($args);
        };
        
        $v8->concat = function(...$args) {
            return implode('-', $args);
        };
        
        $result1 = $v8->executeString('PHP.sum(1, 2, 3, 4, 5);');
        $result2 = $v8->executeString('PHP.concat("a", "b", "c");');
        
        self::assertEquals(15, $result1);
        self::assertEquals('a-b-c', $result2);
    }
    
    /**
     * Test callback with default parameters
     */
    public static function testCallbackDefaultParams()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->greet = function($name = 'World', $prefix = 'Hello') {
            return "$prefix, $name!";
        };
        
        $result1 = $v8->executeString('PHP.greet();');
        $result2 = $v8->executeString('PHP.greet("Alice");');
        $result3 = $v8->executeString('PHP.greet("Bob", "Hi");');
        
        self::assertEquals('Hello, World!', $result1);
        self::assertEquals('Hello, Alice!', $result2);
        self::assertEquals('Hi, Bob!', $result3);
    }
    
    /**
     * Test callback with type hints
     */
    public static function testCallbackTypeHints()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->processString = function(string $str): string {
            return strtoupper($str);
        };
        
        $v8->processInt = function(int $num): int {
            return $num * 2;
        };
        
        $result1 = $v8->executeString('PHP.processString("hello");');
        $result2 = $v8->executeString('PHP.processInt(21);');
        
        self::assertEquals('HELLO', $result1);
        self::assertEquals(42, $result2);
    }
    
    /**
     * Test invokable object (__invoke)
     */
    public static function testInvokableObject()
    {
        self::skipIfNoV8js();
        
        $invokable = new class {
            private $multiplier = 3;
            
            public function __invoke($x) {
                return $x * $this->multiplier;
            }
        };
        
        $v8 = new V8Js();
        $v8->callable = $invokable;
        
        $result = $v8->executeString('PHP.callable(7);');
        self::assertEquals(21, $result);
    }
    
    /**
     * Test callback returning a callback
     */
    public static function testCallbackReturningCallback()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->getAdder = function($x) {
            return function($y) use ($x) {
                return $x + $y;
            };
        };
        
        $v8->test = function($adder, $value) {
            return $adder($value);
        };
        
        $result = $v8->executeString('
            var add5 = PHP.getAdder(5);
            PHP.test(add5, 10);
        ');
        
        self::assertEquals(15, $result);
    }
    
    /**
     * Test callback with array access
     */
    public static function testCallbackWithArrayAccess()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->mapArray = function($arr, $callback) {
            return array_map($callback, $arr);
        };
        
        $result = $v8->executeString('
            PHP.mapArray([1, 2, 3, 4, 5], function(x) { return x * x; });
        ');
        
        self::assertEquals([1, 4, 9, 16, 25], $result);
    }
    
    /**
     * Test callback with object method passed from JS
     */
    public static function testCallbackWithJSObjectMethod()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->execute = function($obj) {
            return $obj->calculate(10, 20);
        };
        
        $result = $v8->executeString('
            PHP.execute({
                calculate: function(a, b) {
                    return a + b;
                }
            });
        ');
        
        self::assertEquals(30, $result);
    }
    
    /**
     * Test nested callbacks
     */
    public static function testNestedCallbacks()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->outer = function($callback) {
            return $callback(function($x) {
                return $x * 2;
            });
        };
        
        $result = $v8->executeString('
            PHP.outer(function(inner) {
                return inner(21);
            });
        ');
        
        self::assertEquals(42, $result);
    }
    
    /**
     * Test callback with mixed parameter types
     */
    public static function testCallbackMixedParams()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->process = function($str, $num, $bool, $arr, $obj) {
            return [
                'string' => gettype($str),
                'number' => gettype($num),
                'boolean' => gettype($bool),
                'array' => gettype($arr),
                'object' => gettype($obj),
                'values' => [$str, $num, $bool, count($arr), isset($obj->key)]
            ];
        };
        
        $result = $v8->executeString('
            PHP.process("test", 42, true, [1, 2, 3], { key: "value" });
        ');
        
        self::assertEquals('string', $result->string);
        self::assertEquals('integer', $result->number);
        self::assertEquals('boolean', $result->boolean);
        self::assertEquals('array', $result->array);
        self::assertEquals('object', $result->object);
        self::assertEquals(['test', 42, true, 3, true], $result->values);
    }
    
    /**
     * Test callback on class instance method
     */
    public static function testCallbackOnClassInstance()
    {
        self::skipIfNoV8js();
        
        $calculator = new class {
            private $history = [];
            
            public function add($a, $b) {
                $result = $a + $b;
                $this->history[] = "add($a, $b) = $result";
                return $result;
            }
            
            public function multiply($a, $b) {
                $result = $a * $b;
                $this->history[] = "multiply($a, $b) = $result";
                return $result;
            }
            
            public function getHistory() {
                return $this->history;
            }
        };
        
        $v8 = new V8Js();
        $v8->add = function($a, $b) use ($calculator) { return $calculator->add($a, $b); };
        $v8->multiply = function($a, $b) use ($calculator) { return $calculator->multiply($a, $b); };
        $v8->getHistory = function() use ($calculator) { return $calculator->getHistory(); };
        
        $v8->executeString('
            PHP.add(5, 3);
            PHP.multiply(4, 7);
            PHP.add(10, 20);
        ');
        
        $history = $v8->executeString('PHP.getHistory();');
        
        self::assertCount(3, $history);
        self::assertEquals('add(5, 3) = 8', $history[0]);
        self::assertEquals('multiply(4, 7) = 28', $history[1]);
        self::assertEquals('add(10, 20) = 30', $history[2]);
    }
    
    /**
     * Test first-class callable syntax (PHP 8.1+)
     */
    public static function testFirstClassCallable()
    {
        self::skipIfNoV8js();
        
        // Skip if PHP version doesn't support first-class callable
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            self::skip('First-class callable requires PHP 8.1+');
        }
        
        $v8 = new V8Js();
        
        // Using first-class callable syntax
        $v8->strUpper = strtoupper(...);
        $v8->arraySum = array_sum(...);
        
        $result1 = $v8->executeString('PHP.strUpper("hello world");');
        $result2 = $v8->executeString('PHP.arraySum([1, 2, 3, 4, 5]);');
        
        self::assertEquals('HELLO WORLD', $result1);
        self::assertEquals(15, $result2);
    }
    
    /**
     * Test callback with exception handling
     * Note: JS errors don't get caught by PHP try-catch in the callback
     */
    public static function testCallbackExceptionHandling()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->safe = function($value) {
            try {
                if ($value < 0) {
                    throw new Exception("Negative value: $value");
                }
                return ['success' => true, 'result' => $value * 2];
            } catch (Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        };
        
        $result1 = $v8->executeString('PHP.safe(21);');
        $result2 = $v8->executeString('PHP.safe(-5);');
        
        self::assertTrue($result1->success);
        self::assertEquals(42, $result1->result);
        
        self::assertFalse($result2->success);
        self::assertEquals('Negative value: -5', $result2->error);
    }
    
    /**
     * Test Closure::fromCallable
     */
    public static function testClosureFromCallable()
    {
        self::skipIfNoV8js();
        
        $obj = new class {
            public function process($x) {
                return $x * 3;
            }
        };
        
        $v8 = new V8Js();
        $v8->callback = Closure::fromCallable([$obj, 'process']);
        
        $result = $v8->executeString('PHP.callback(14);');
        self::assertEquals(42, $result);
    }
    
    /**
     * Test passing PHP callbacks to JS array methods
     */
    public static function testCallbackWithJSArrayMethods()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->double = function($x) { return $x * 2; };
        $v8->isEven = function($x) { return $x % 2 === 0; };
        $v8->sum = function($acc, $curr) { return $acc + $curr; };
        
        $result = $v8->executeString('
            var arr = [1, 2, 3, 4, 5];
            var mapped = arr.map(PHP.double);
            var filtered = arr.filter(PHP.isEven);
            var reduced = arr.reduce(PHP.sum, 0);
            
            [mapped, filtered, reduced];
        ');
        
        self::assertEquals([2, 4, 6, 8, 10], $result[0]);
        self::assertEquals([2, 4], $result[1]);
        self::assertEquals(15, $result[2]);
    }
}
