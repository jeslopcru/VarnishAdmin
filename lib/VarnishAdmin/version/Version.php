<?php
namespace VarnishAdmin\version;

interface Version
{
    public function getPurgeCommand();

    public function getQuit();

    public function getPurgeUrlCommand();

    public function getStart();

    public function getStatus();

    public function getStop();
}