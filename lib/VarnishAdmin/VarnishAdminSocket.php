<?php

namespace VarnishAdmin;

use Exception;

class VarnishAdminSocket implements VarnishAdmin
{
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 6082;
    const DEFAULT_VERSION = '3.04';
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
    /**
     * Socket pointer.
     */
    private $fp;

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

        //default command values
        $this->quit = 'quit';
        $this->purgeCommand = 'ban';

        //Different directives depends Varnish version
        if ($this->version == 4) {
            $this->purgeUrlCommand = $this->purgeCommand . ' req.url ~';
        } elseif ($this->version == 3) {
            $this->purgeUrlCommand = $this->purgeCommand . '.url';
        } else {
            throw new \Exception('Only versions 3 and 4 of Varnish are supported');
        }
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
        $versionSplit = explode('.', $version, 3);
        $this->version = isset($versionSplit[0]) ? (int)$versionSplit[0] : 3;
    }

    /**
     * Connect to admin socket.
     *
     * @param int $timeout in seconds, defaults to 5; used for connect and reads
     * @return string the banner, in case you're interested
     * @throws Exception
     * @throws \Exception
     */
    public function connect($timeout = 5)
    {
        $this->openSocket($timeout);
        // connecting should give us the varnishadm banner with a 200 code, or 107 for auth challenge
        $banner = $this->read($code);
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
     * @param integer $timeout
     * @throws Exception
     */
    protected function openSocket($timeout)
    {
        $errno = null;
        $errstr = null;
        $this->fp = fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        if (!is_resource($this->fp)) {
            // error would have been raised already by fsockopen
            throw new Exception(sprintf('Failed to connect to varnishadm on %s:%s; "%s"', $this->host, $this->port,
                $errstr));
        }
        // set socket options
        stream_set_blocking($this->fp, 1);
        stream_set_timeout($this->fp, $timeout);
    }

    /**
     * @param $code
     * @return string
     * @throws Exception
     * @internal param reference $int for reply code
     */
    protected function read(&$code)
    {
        $code = null;
        $len = null;
        // get bytes until we have either a response code and message length or an end of file
        // code should be on first line, so we should get it in one chunk
        while (!feof($this->fp)) {
            $response = fgets($this->fp, 1024);
            if (!$response) {
                $meta = stream_get_meta_data($this->fp);
                if ($meta['timed_out']) {
                    throw new Exception(sprintf('Timed out reading from socket %s:%s', $this->host, $this->port));
                }
            }
            if (preg_match('/^(\d{3}) (\d+)/', $response, $r)) {
                $code = (int)$r[1];
                $len = (int)$r[2];
                break;
            }
        }
        if (is_null($code)) {
            throw new Exception('Failed to get numeric code in response');
        }
        $response = '';
        while (!feof($this->fp) && strlen($response) < $len) {
            $response .= fgets($this->fp, 1024);
        }

        return $response;
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
        $cmd && $this->write($cmd);
        $this->write("\n");
        $response = $this->read($code);
        if ($code !== $ok) {
            $response = implode("\n > ", explode("\n", trim($response)));
            throw new Exception(sprintf("%s command responded %d:\n > %s", $cmd, $code, $response), $code);
        }

        return $response;
    }

    /**
     * Write data to the socket input stream.
     *
     * @param $data
     * @return bool
     * @throws Exception
     * @internal param $string
     */
    private function write($data)
    {
        $bytes = fputs($this->fp, $data);
        if ($bytes !== strlen($data)) {
            throw new Exception(sprintf('Failed to write to varnishadm on %s:%s', $this->host, $this->port));
        }

        return true;
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
        return $this->command($this->purgeCommand . ' ' . $expr);
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
        return $this->command($this->purgeUrlCommand . ' ' . $url);
    }

    /**
     * Graceful close, sends quit command.
     */
    public function quit()
    {
        try {
            $this->command('quit', null, 500);
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
        is_resource($this->fp) && fclose($this->fp);
        $this->fp = null;
    }

    /**
     * @return bool
     */
    public function start()
    {
        if ($this->status()) {
            $this->generateErrorMassage(sprintf('varnish host already started on %s:%s',
                $this->host, $this->port));

            return true;
        }
        $this->command('start');

        return true;
    }

    /**
     * Test varnish child status.
     *
     * @return bool whether child is alive
     */
    public function status()
    {
        try {
            $response = $this->command('status');

            return $this->isRunning($response);
        } catch (\Exception $Ex) {
            return false;
        }
    }

    /**
     * @param null|string $response
     */
    protected function isRunning($response)
    {
        if (!preg_match('/Child in state (\w+)/', $response, $r)) {
            return false;
        }

        $result = $r[1] === 'running' ? true : false;

        return $result;
    }

    protected function generateErrorMassage($msg)
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
            $this->generateErrorMassage(sprintf('varnish host already stopped on %s:%s',
                $this->host, $this->port));

            return true;
        }

        $this->command('stop');

        return true;
    }
}
