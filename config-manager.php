<?php

/**
 * Plugin Name: Config Manager
 * Description: Loader for different types of configuration stored in files in themes’ directory
 * Version: 0.2
 * Author: seb@wemakecustom.com
 * Author URI: http://www.wemakecustom.com
 */

add_action('plugins_loaded', array('WMC\Wordpress\ConfigManager\BaseManager', 'initSystem'));
