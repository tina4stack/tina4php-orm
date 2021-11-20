<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;

require_once "./vendor/autoload.php";

class ORMTest extends TestCase
{
    function testClassExists() {
        $this->assertEquals(class_exists("Tina4\ORM"), true, "Class does not exist");
    }

}