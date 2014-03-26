<?php

namespace Ctc\Tests\DummyTest;

class DummyTest extends \PHPUnit_Framework_TestCase
{
    public function testDummy()
    {
        $a = 1;

        // Assert
        $this->assertEquals(1, $a);
    }
}
