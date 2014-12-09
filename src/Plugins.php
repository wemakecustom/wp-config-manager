<?php

namespace WMC\Wordpress\ConfigManager;

class Plugins extends BaseManager
{

    public static function registerHook()
    {
        $manager = new static();

        add_action('required_plugins_register', array($manager, 'register'));

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $configs = $this->getConfigs();
        if (empty($configs['plugins'])) return;

        required_plugins_register($configs['plugins'], $configs['configs']);

        add_filter('plugin_action_links', array($this, 'plugin_action_links'), /* priority */ 20, /* arguments */ 2);
    }

    public function plugin_action_links($actions, $plugin_file)
    {
        if (empty($actions['deactivate'])) {
            return $actions;
        }

        if (preg_match('/^([a-z\-_0-9]+)\//i', $plugin_file, $matches)) {
            $id = $matches[1];
            $configs = $this->getConfigs();

            if (isset($configs['plugins'][$id]) && $configs['plugins'][$id]['required']) {
                unset($actions['deactivate']);
            }
        }

        return $actions;
    }

    protected function filterConfigs(array &$configs)
    {
        if (empty($configs['plugins'])) return;
        if (empty($configs['configs'])) $configs['configs'] = array();

        foreach ($configs['plugins'] as $slug => &$plugin) {
            if (!is_array($plugin)) {
                $plugin = array('name' => $plugin);
            }

            if (empty($plugin['slug'])) {
                $plugin['slug'] = $slug;
            }
            if (empty($plugin['name'])) {
                $plugin['name'] = ucwords(preg_replace('/[_\- ]+/', ' ', $slug));
            }
            if (!array_key_exists('required', $plugin)) {
                $plugin['required'] = true;
            }
            if (!array_key_exists('force_activation', $plugin)) {
                $plugin['force_activation'] = $plugin['required'];
            }
        }
    }
}
