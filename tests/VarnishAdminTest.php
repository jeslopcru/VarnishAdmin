<?php

use VarnishAdmin\VarnishAdminSocket;

class VarnishAdminTest extends PHPUnit_Framework_TestCase
{
    public function testNothing()
    {
        $admin = new VarnishAdminSocket();

        $this->assertTrue(true);
    }
}
