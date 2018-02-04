<?php
namespace VarnishAdmin\Tests;

use Exception;
use PHPUnit_Framework_TestCase;
use VarnishAdmin;

class VarnishAdminSocketTest extends PHPUnit_Framework_TestCase
{
    /** @var VarnishAdminSocketFake */
    public $admin;
    public $stubSocket;

    public function setUp()
    {
        $this->admin = new VarnishAdminSocketFake();
        $this->stubSocket = new StubSocket();
        $this->admin->setSocket($this->stubSocket);
    }

    public function testConstructDefaultValues()
    {
        $this->assertSame($this->admin->getServerAddress()->getHost(), '127.0.0.1');
        $this->assertSame($this->admin->getServerAddress()->getPort(), 6082);
        $this->assertSame($this->admin->version, 3);
    }

    public function testConstructVersion4Values()
    {
        $admin = new VarnishAdminSocketFake('127.0.0.1', 6082, '4.0.2');
        $this->assertSame($admin->getServerAddress()->getHost(), '127.0.0.1');
        $this->assertSame($admin->getServerAddress()->getPort(), 6082);
        $this->assertSame($admin->version, 4);
    }

    /**
     * @expectedException Exception
     */
    public function testConstructNoSupportedVarnishVersion()
    {
        new VarnishAdminSocketFake(1, 1, 9);
    }

    public function testCloseConnection()
    {
        $this->admin->close();
        $this->assertNull($this->admin->getSocket());
    }

    public function testConnectOk()
    {
        $this->stubSocket->codeMock = 200;
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws Exception
     * @expectedException Exception
     * @expectedExceptionMessage Authentication required; see VarnishAdminSocket::setSecret
     */
    public function testConnectAuthenticationRequiredNotSecretDefined()
    {
        $this->stubSocket->codeMock = 107;
        $this->admin->secret = false;
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws Exception
     * @expectedException Exception
     * @expectedExceptionMessage Authentication failed
     */
    public function testConnectAuthenticationFailed()
    {
        $this->stubSocket->codeMock = 107;
        $this->admin->secret = true;
        $this->admin->commandResultException = 'Authentication failed';
        $this->assertNull($this->admin->connect());
    }

    /**
     * @throws Exception
     * @expectedException Exception
     * @expectedExceptionMessage Bad response from varnishadm on 127.0.0.1:6082
     */
    public function testConnectBadResponse()
    {
        $this->stubSocket->codeMock = 503;
        $this->admin->secret = true;
        $this->admin->commandResultException = sprintf(
            'Bad response from varnishadm on %s:%s',
            $this->admin->host,
            $this->admin->port
        );
        $this->assertNull($this->admin->connect());
    }

    public function testPurgeCommand()
    {
        $result = $this->admin->purge('expr');
        $this->assertEquals('ban expr', $result);
    }

    public function testPurgeUrlCommand()
    {
        $result = $this->admin->purgeUrl('http://example.com');
        $this->assertEquals('ban.url http://example.com', $result);
    }

    public function testPurgeUrlVarnish4Command()
    {
        $admin = new VarnishAdminSocketFake(1, 1, 4);
        $result = $admin->purgeUrl('http://example.com');
        $this->assertEquals('ban req.url ~ http://example.com', $result);
    }

    public function testQuit()
    {
        $this->admin->quit();
        $this->assertNull($this->admin->getSocket());
        $this->assertContains('quit', $this->admin->commandExecuted);
    }

    public function testStart()
    {
        $this->assertEquals(true, $this->admin->start());
        $this->assertContains('start', $this->admin->commandExecuted);
    }

    public function testStartWhenRunning()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, @$this->admin->start());
    }

    public function testStatusNotRunning()
    {
        $this->admin->isRunningMock = false;
        $this->assertEquals(false, $this->admin->status());
    }

    /**
     * @expectedException Exception;
     */
    public function testStatusNotRunningWithException()
    {
        $this->admin->isRunningMock = false;
        $this->admin->commandResultException = new Exception();
        $this->assertEquals(false, $this->admin->status());
    }

    public function testStatusRunning()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, $this->admin->status());
    }

    public function testSetSecret()
    {
        $this->admin->setSecret('secret');
        $this->assertEquals('secret', $this->admin->secret);
    }

    public function testStop()
    {
        $this->admin->isRunningMock = true;
        $this->assertEquals(true, $this->admin->stop());
        $this->assertContains('stop', $this->admin->commandExecuted);
    }

    public function testStopWhenNotRunning()
    {
        $this->assertEquals(true, @$this->admin->stop());
    }
}
