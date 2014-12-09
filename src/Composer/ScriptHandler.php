<?php

namespace WMC\Wordpress\ConfigManager\Composer;

use Composer\Script\Event;
use WMC\Wordpress\ConfigManager\BaseManager;

class ScriptHandler
{
    public static function clearCache(Event $event)
    {
        $composer = $event->getComposer();
        $io       = $event->getIO();
        $extras   = $composer->getPackage()->getExtra();
        $web_dir  = getcwd() . '/' . (empty($extras['web-dir']) ? 'htdocs' : $extras['web-dir']);

        $io->write('<info>Clearing cache of Config Manager</info>');

        if (!defined('ABSPATH')) {
            define('ABSPATH', $web_dir . '/');
        }

        BaseManager::clearCache();
    }
}
