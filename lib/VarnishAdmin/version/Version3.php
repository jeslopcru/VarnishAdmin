<?php
namespace VarnishAdmin\version;

class Version3 extends Version
{
    const URL = '.url';
    
    public function getPurgeUrlCommand()
    {
        $command = self::BAN . self::URL;
        return $command;
    }
}
