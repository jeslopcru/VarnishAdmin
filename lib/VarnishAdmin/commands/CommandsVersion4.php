<?php
namespace VarnishAdmin\commands;

class CommandsVersion4 extends Commands
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

    /**
     * @return string
     */
    public function getBackendList()
    {
        return self::BACKEND . '.list';
    }
}
