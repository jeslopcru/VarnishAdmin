<?php
namespace VarnishAdmin\version;

class Version4 extends Version
{
    const URL = ' req.url ~';

    public function getPurgeUrlCommand()
    {
        $command = self::BAN . self::URL;
        return $command;
    }
}
