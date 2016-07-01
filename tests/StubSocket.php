<?php


namespace VarnishAdmin\Tests;

use VarnishAdmin\Socket;

class StubSocket extends Socket
{
    public $codeMock;

    public function openSocket($host, $port, $timeout)
    {
    }

    public function read(&$code)
    {
        $code = $this->codeMock;
    }
}
