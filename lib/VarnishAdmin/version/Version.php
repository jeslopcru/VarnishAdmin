<?php
namespace VarnishAdmin\version;

abstract class Version
{
    const QUIT = 'quit';
    const START = 'start';
    const STATUS = 'status';
    const STOP = 'stop';
    const BAN = 'ban';

    /**
     * @return string
     */
    public function getPurgeCommand()
    {
        return self::BAN;
    }

    /**
     * @return string
     */
    public function getQuit()
    {
        return self::QUIT;
    }

    /**
     * @return string
     */
    abstract public function getPurgeUrlCommand();

    /**
     * @return string
     */
    public function getStart()
    {
        return self::START;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return self::STATUS;
    }

    /**
     * @return string
     */
    public function getStop()
    {
        return self::STOP;
    }
}
