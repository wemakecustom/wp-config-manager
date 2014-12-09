<?php

namespace WMC\Wordpress\ConfigManager;

class ThemeOptions extends BaseManager
{

    public static function registerHook()
    {
        $manager = new static();

        add_action('after_setup_theme', array($manager, 'register'));
        add_action('customize_save', array($manager, 'customize_save'));

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $configs = $this->getConfigs();

        foreach ($configs as $key => $config) {
            if (empty($config)) continue;

            $method = 'register_' . str_replace('-', '_', $key);
            if (method_exists($this, $method)) {
                $this->$method($config);
            }
        }
    }

    protected function register_mods($configs)
    {
        self::array_defaults($configs, $this->get_default_theme_options());
        $mods = get_theme_mods();

        foreach ($configs as $key => $value) {
            if (!array_key_exists($key, $mods) || $value != $mods[$key]) {
                set_theme_mod($key, $value);
            }
        }
    }

    /**
     * Hooks into the customize_save action to save modifications in theme_options/mods
     */
    public function customize_save(\WP_Customize_Manager $manager)
    {
        foreach ( $manager->settings() as $key => $setting ) {
            if ($setting->type == 'theme_mod') {
                // We have to call this early to make sure it is saved
                $setting->save();
            }
        }
        $mods = get_theme_mods();
        if ($mods) {
            ksort($mods);
            static::writeConfigs("theme_options/mods", array('mods' => $mods));
        }
    }

    protected function register_support($configs)
    {
        foreach ($configs as $support => $config) {
            if (empty($config)) {
                $config = array();
            }

            add_theme_support($support, $config);
        }
    }

    protected function register_menu_options($configs)
    {
        if (!empty($configs['show_home'])) {
            add_filter( 'wp_page_menu_args', function($args) use ($configs) {
                $args['show_home'] = $configs['show_home'];

                return $args;
            });
        }
    }

    protected function register_customize_options($configs)
    {
        add_action('customize_register', array($this, 'customize_register'));
    }

    private function get_default_theme_options()
    {
        $defaults = array();
        $configs = $this->getConfigs();

        foreach ($configs['customize_options'] as $section_id => $section) {
            foreach ($section['settings'] as $setting_id => $setting) {
                if ($setting['type'] == 'theme_mod') {
                    $defaults[$section_id][$setting_id] = isset($setting['default']) ? $setting['default'] : null;
                }
            }
        }

        return $defaults;
    }

    public function customize_register($wp_customize)
    {
        $configs = $this->getConfigs();

        foreach ($configs['customize_options'] as $section_id => $section) {
            $wp_customize->add_section($section_id, array(
                'title'    => $section['name'],
                'priority' => $section['priority'],
            ));

            foreach ($section['settings'] as $setting_id => $setting) {
                $id = $section_id . "[$setting_id]";

                $wp_customize->add_setting($id, array(
                    'default'    => $setting['default'],
                    'type'       => $setting['option_type'],
                ));
                $wp_customize->add_control($id, array(
                    'label'      => $setting['name'],
                    'section'    => $section_id,
                    'type'       => $setting['type'],
                    'choices'    => isset($setting['choices']) ? $setting['choices'] : null,
                ));
            }
        }
    }

    /**
     * @todo
     */
    protected function filterConfigs(array &$configs)
    {
    }
}
