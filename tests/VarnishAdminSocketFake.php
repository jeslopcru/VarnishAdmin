<?php

namespace VarnishAdmin\Tests;


use Exception;
use VarnishAdmin\VarnishAdminSocket;

class VarnishAdminSocketFake extends VarnishAdminSocket
{
    public $host;
    public $port;
    public $fp;
    public $secret;
    public $version;

    //Mocks
    public $commandResultException;
    public $commandExecuted = array();
    public $isRunningMock;

    protected function command($cmd, $code = '', $ok = 200)
    {
        if (isset($this->commandResultException)) {
            throw new Exception($this->commandResultException);
        }
        $this->commandExecuted[] = $cmd;

        return $cmd;
    }

    protected function isRunning($response)
    {
        return $this->isRunningMock;
    }
}
