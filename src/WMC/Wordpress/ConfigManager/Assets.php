<?php

namespace WMC\Wordpress\ConfigManager;

/**
 * css:
 *   name:
 *       url: <?php echo $theme_uri ?>/css/name.css
 *       conditional: lte IE 8
 *       enqueue: 1
 *       admin: 0   # Only in non-admin pages, default
 *       # admin: 1 # Only in admin pages
 *       # admin: ~ # Always
 *       deps:
 *           - foobar
 *       media: screen
 * js:
 *   name:
 *       url: <?php echo $theme_uri ?>/js/name.js
 *       conditional: lte IE 8
 *       enqueue: 1
 *       admin: 0
 *       deps:
 *           - foobar
 *       footer: 1
 */
class Assets extends BaseManager
{
    private static $defaults = array(
        'url'     => null,
        'deps'    => array(),
        'version' => false,
        'footer'  => false,
        'enqueue' => false,
        'media'   => 'all',
        'admin'   => false,
    );

    private $queue = array();

    public static function registerHook()
    {
        $manager = new static();

        add_action('init', array($manager, 'register')); // Register soon so it is callable by the templates
        $enqueue_action = is_admin() ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts';
        add_action($enqueue_action, array($manager, 'setVersion'));
        add_action($enqueue_action, array($manager, 'enqueue'));

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $assets = $this->getConfigs();

        foreach (array('css' => 'wp_styles', 'js' => 'wp_scripts') as $mode => $global) {
            if (!isset($assets[$mode])) continue;

            foreach ($assets[$mode] as $id => $params) {
                if (null !== $params['admin'] && $params['admin'] != is_admin()) continue;

                if (preg_match('!^//!', $params['url'])) {
                    // Wordpress is not compatible with // urls, fix them
                    $params['url'] = (empty($_SERVER['HTTPS']) ? 'http:' : 'https:') . $params['url'];
                }

                switch ($mode) {
                    case 'js':
                        wp_deregister_script($id); // Make sure it replaces existing
                        wp_register_script($id, $params['url'], $params['deps'], $params['version'], $params['footer']);
                        break;

                    case 'css':
                        wp_deregister_style($id); // Make sure it replaces existing
                        wp_register_style($id, $params['url'], $params['deps'], $params['version'], $params['media']);
                        break;
                }

                if (!empty($params['conditional'])) {
                    // Relies on this patch http://core.trac.wordpress.org/ticket/16024
                    $GLOBALS[$global]->add_data($id, 'conditional', $params['conditional']);
                }
                if ($params['enqueue']) {
                    $this->queue[$mode][] = $id;
                }
            }
        }
    }

    protected function filterConfigs(array &$configs)
    {
        foreach (array('css', 'js') as $mode) {
            if (!isset($configs[$mode])) continue;

            foreach ($configs[$mode] as $id => &$config) {
                static::array_defaults($config, self::$defaults);

                if (empty($config['url']) || !is_string($config['url'])) {
                    $this->addMessage('error', "Parameter url is required for asset $id");
                    unset($configs[$id]);
                    continue;
                }
            }
        }
    }

    /**
     * Enqueue all assets that were marked with enqueue: 1
     */
    public function enqueue()
    {
        foreach (array('js' => 'wp_enqueue_script', 'css' => 'wp_enqueue_style') as $mode => $method) {
            if (isset($this->queue[$mode])) {
                array_map($method, $this->queue[$mode]);
            }
        }
    }

    /**
     * Remove the ?ver=x.x.x parameter
     */
    public function setVersion()
    {
        $GLOBALS['wp_styles']->default_version  = false;
        $GLOBALS['wp_scripts']->default_version = false;
    }
}
