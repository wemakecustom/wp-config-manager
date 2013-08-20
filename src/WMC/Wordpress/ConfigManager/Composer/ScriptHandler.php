<?php

namespace WMC\Wordpress\ConfigManager\Composer;

use WMC\Wordpress\ConfigManager\BaseManager;

class ScriptHandler
{
    public static function clearCache(Event $event)
    {
        $composer = $event->getComposer();
        $io       = $event->getIO();
        $extras   = $composer->getPackage()->getExtra();
        $web_dir  = getcwd() . '/' . (empty($extras['web-dir']) ? 'htdocs' : $extras['web-dir']);

        require_once $web_dir . '/wp-load.php';

        $io->write('<info>Clearing cache of Config Manager.</info>');
        BaseManager::clearCache();
    }
}