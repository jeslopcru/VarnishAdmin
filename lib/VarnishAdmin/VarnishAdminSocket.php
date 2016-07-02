<?php

namespace VarnishAdmin;

use Exception;
use VarnishAdmin\version\Version;
use VarnishAdmin\version\Version3;
use VarnishAdmin\version\Version4;

class VarnishAdminSocket implements VarnishAdmin
{
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 6082;
    const DEFAULT_VERSION = '3';
    const THIRD_VERSION = 3;
    const FOURTH_VERSION = 4;
    const DEFAULT_TIMEOUT = 5;
    /**
     * Host on which varnishadm is listening.
     *
     * @var string
     */
    protected $host;
    /**
     * Port on which varnishadm is listening, usually 6082.
     *
     * @var int port
     */
    protected $port;
    /**
     * Secret to use in authentication challenge.
     *
     * @var string
     */
    protected $secret;
    /**
     * Major version of Varnish top which you're connecting; 3 or 4.
     *
     * @var int
     */
    protected $version;
    protected $purgeCommand;
    protected $quit;
    protected $purgeUrlCommand;
    /** @var  Version */
    private $command;
    private $socket;

    /**
     * Constructor.
     *
     * @param string $host
     * @param int $port
     * @param string $version
     *
     * @throws \Exception
     */
    public function __construct($host = null, $port = null, $version = null)
    {
        $this->setHost($host);
        $this->setPort($port);
        $this->setVersion($version);

        $this->checkSupportedVersion();
        $this->setDefaultCommands();
        $this->socket = new Socket();
    }

    private function setHost($host)
    {
        $this->host = $host;
        if (empty($this->host)) {
            $this->host = self::DEFAULT_HOST;
        }
    }

    private function setPort($port)
    {
        $this->port = $port;
        if (empty($this->port)) {
            $this->port = self::DEFAULT_PORT;
        }
    }

    private function setVersion($version)
    {
        if (empty($version)) {
            $version = self::DEFAULT_VERSION;
        }
        $versionSplit = explode('.', $version, self::THIRD_VERSION);
        $this->version = isset($versionSplit[0]) ? (int)$versionSplit[0] : self::THIRD_VERSION;
    }

    private function checkSupportedVersion()
    {
        if (!$this->isFourthVersion() && !$this->isThirdVersion()) {
            throw new \Exception('Only versions 3 and 4 of Varnish are supported');
        }
    }

    /**
     * @return bool
     */
    private function isFourthVersion()
    {
        return $this->version == self::FOURTH_VERSION;
    }

    private function isThirdVersion()
    {
        return $this->version == self::THIRD_VERSION;
    }

    private function setDefaultCommands()
    {
        $this->quit = 'quit';
        $this->purgeCommand = 'ban';

        if ($this->isFourthVersion()) {
            $this->command = new Version4();
        }

        if ($this->isThirdVersion()) {
            $this->command = new Version3();

        }
    }

    /**
     * Connect to admin socket.
     *
     * @param int $timeout in seconds, defaults to 5; used for connect and reads
     * @return string the banner, in case you're interested
     * @throws Exception
     * @throws \Exception
     */
    public function connect($timeout = null)
    {
        if (empty($timeout)) {
            $timeout = self::DEFAULT_TIMEOUT;
        }
        $this->socket->openSocket($this->host, $this->port, $timeout);
        // connecting should give us the varnishadm banner with a 200 code, or 107 for auth challenge
        $banner = $this->socket->read($code);
        if ($code === 107) {
            if (!$this->secret) {
                throw new \Exception('Authentication required; see VarnishAdminSocket::setSecret');
            }
            try {
                $challenge = substr($banner, 0, 32);
                $response = hash('sha256', $challenge . "\n" . $this->secret . $challenge . "\n");
                $banner = $this->command('auth ' . $response, $code, 200);
            } catch (\Exception $ex) {
                throw new \Exception('Authentication failed');
            }
        }
        if ($code !== 200) {
            throw new \Exception(sprintf('Bad response from varnishadm on %s:%s', $this->host, $this->port));
        }

        return $banner;
    }

    /**
     * Write a command to the socket with a trailing line break and get response straight away.
     *
     * @param string $cmd
     * @param $code
     * @param int $ok
     * @return string
     * @throws Exception
     * @internal param $string
     */
    protected function command($cmd, $code = '', $ok = 200)
    {
        if (!$this->host) {
            return null;
        }
        $cmd && $this->socket->write($cmd);
        $this->socket->write("\n");
        $response = $this->socket->read($code);
        if ($code !== $ok) {
            $response = implode("\n > ", explode("\n", trim($response)));
            throw new Exception(sprintf("%s command responded %d:\n > %s", $cmd, $code, $response), $code);
        }

        return $response;
    }

    /**
     * Shortcut to purge function.
     *
     * @see https://www.varnish-cache.org/docs/4.0/users-guide/purging.html
     *
     * @param string $expr is a purge expression in form "<field> <operator> <arg> [&& <field> <oper> <arg>]..."
     *
     * @return string
     */
    public function purge($expr)
    {
        return $this->command($this->command->getPurgeCommand() . ' ' . $expr);
    }

    /**
     * Shortcut to purge.url function.
     *
     * @see https://www.varnish-cache.org/docs/4.0/users-guide/purging.html
     *
     * @param string $url is a url to purge
     *
     * @return string
     */
    public function purgeUrl($url)
    {
        return $this->command($this->command->getPurgeUrlCommand() . ' ' . $url);
    }

    /**
     * Graceful close, sends quit command.
     */
    public function quit()
    {
        try {
            $this->command($this->command->getQuit(), null, 500);
        } catch (Exception $Ex) {
            // silent fail - force close of socket
        }
        $this->close();
    }

    /**
     * Brutal close, doesn't send quit command to varnishadm.
     */
    public function close()
    {
        $this->socket->close();
        $this->socket = null;
    }

    /**
     * @return bool
     */
    public function start()
    {
        if ($this->status()) {
            $this->generateErrorMessage(sprintf('varnish host already started on %s:%s',
                $this->host, $this->port));

            return true;
        }
        $this->command($this->command->getStart());

        return true;
    }

    public function status()
    {
        try {
            $response = $this->command($this->command->getStatus());

            return $this->isRunning($response);
        } catch (\Exception $Ex) {
            return false;
        }
    }

    protected function isRunning($response)
    {
        if (!preg_match('/Child in state (\w+)/', $response, $result)) {
            return false;
        }

        return $result[1] === 'running' ? true : false;
    }

    private function generateErrorMessage($msg)
    {
        trigger_error($msg, E_USER_NOTICE);
    }

    /**
     * Set authentication secret.
     * Warning: may require a trailing newline if passed to varnishadm from a text file.
     *
     * @param string
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @return bool
     */
    public function stop()
    {
        if (!$this->status()) {
            $this->generateErrorMessage(sprintf('varnish host already stopped on %s:%s',
                $this->host, $this->port));

            return true;
        }

        $this->command($this->command->getStop());

        return true;
    }

    /**
     * @return Socket
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param Socket $socket
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;
    }
}
