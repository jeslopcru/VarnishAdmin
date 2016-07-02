<?php
namespace VarnishAdmin\version;

class Version4 extends Version
{
    const NUMBER = 4;
    const URL = ' req.url ~';

    public function getPurgeUrlCommand()
    {
        $command = self::BAN . self::URL;
        return $command;
    }

    /**
     * @return string
     */
    public function getVersionNumber()
    {
        return self::NUMBER;
    }
}
