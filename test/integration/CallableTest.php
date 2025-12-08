<?php
/**
 * Test __call
 */

require_once __DIR__ . '/../PHPIntegrationTest.php';

class Core {
    public function __call($field, $arguments)
    {
        if (\method_exists($this, 'get_'.$field) ) {
            return $this->{'get_'.$field}();
        }

        return false;
    }
}

class CoreEntity extends Core{

}

class Menu extends CoreEntity {
    public $items = null;

    public function get_items()
    {
        if (\is_array($this->items)) {
            return $this->items;
        }

        return ['test1'];
    }
}


class MultipleModulesTest extends PHPIntegrationTest
{
    /**
     * Test loading multiple different modules
     */
    public static function testCallNoReassign()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->menu = new Menu();

        $value = $v8->executeString('
            let value = PHP.menu.items();
            value[0];
        ');
                
        
        self::assertEquals('test1', $value);
    }
    
    public static function testCallReassign()
    {
        self::skipIfNoV8js();
        
        $v8 = new V8Js();
        
        $v8->menu = new Menu();

        $value = $v8->executeString('
            let value = PHP.menu.items;
            value = value();
            value[0];
        ');
                
        
        self::assertEquals('test1', $value);
    }
    
}
