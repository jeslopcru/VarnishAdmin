<?php


namespace VarnishAdmin\Tests;


class StubSocket
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