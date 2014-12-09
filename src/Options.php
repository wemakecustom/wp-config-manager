<?php

namespace WMC\Wordpress\ConfigManager;

/**
 * option-id: option-value
 */
class Options extends BaseManager
{
    protected static $watchedOptions = array(
        'blogname',
        'blogdescription',
    );

    public static function registerHook()
    {
        $manager = new static();

        add_action('admin_init', array($manager, 'register'));
        add_action('customize_save', array($manager, 'customize_save'));

        foreach (self::$watchedOptions as $option) {
            add_action('update_option_' . $option, array($manager, 'options_save'));
        }

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $configs = $this->getConfigs();

        foreach ($configs as $key => $value) {
            if (get_option($key) != $value) {
                update_option($key, $value);
            }
        }
    }

    protected function options_merge_save($options)
    {
        if (!$options) {
            return;
        }

        $options = array_merge($this->getConfigs(), $options);

        $split = array();

        foreach ($options as $option => $value) {
            switch ($option) {
                case 'blogname':
                case 'blogdescription':
                    $split['title'][$option] = $value;
                    break;

                default:
                    $split['customize'][$option] = $value;
                    break;
            }
        }

        foreach ($split as $group => $groupOptions) {
            ksort($groupOptions);
            static::writeConfigs("options/${group}", $groupOptions);
        }
    }

    public function options_save()
    {
        $options = array();

        foreach (self::$watchedOptions as $option) {
            $options[$option] = get_option($option);
        }

        $this->options_merge_save($options);
    }

    /**
     * Hooks into the customize_save action to save modifications in options/customize
     */
    public function customize_save(\WP_Customize_Manager $manager)
    {
        $options = array();
        foreach ( $manager->settings() as $key => $setting ) {
            if ($setting->type == 'option') {
                // We have to call this early to make sure it is saved
                $setting->save();

                if (preg_match('/^[a-z0-9_\-]+/i', $key, $matches)) {
                    // Match the option name, stripping []
                    // This is to get the base option name for array values
                    $key = $matches[0];
                    // Fetch the whole value at once
                    $options[$key] = get_option($key);
                }
            }
        }

        $this->options_merge_save($options);
    }
}
