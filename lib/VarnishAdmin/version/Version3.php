<?php
namespace VarnishAdmin\version;


class Version3 implements Version
{
    private $purgeCommand;
    private $quit;
    private $purgeUrlCommand;
    private $start;
    private $status;
    private $stop;

    /**
     * Version3 constructor.
     */
    public function __construct()
    {
        $this->quit = 'quit';
        $this->purgeCommand = 'ban';
        $this->purgeUrlCommand = $this->purgeCommand . '.url';
        $this->start = 'start';
        $this->status = 'status';
        $this->stop = 'stop';
    }


    public function getPurgeCommand()
    {
        return $this->purgeCommand;
    }

    public function getQuit()
    {
        return $this->quit;
    }

    public function getPurgeUrlCommand()
    {
        return $this->purgeUrlCommand;
    }

    /**
     * @return string
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getStop()
    {
        return $this->stop;
    }
}