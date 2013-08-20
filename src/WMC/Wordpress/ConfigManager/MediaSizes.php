<?php

namespace WMC\Wordpress\ConfigManager;

/**
 * id:              # Must be all in lowercase, with no spaces
 *   width:  200    # (int) (optional) The post thumbnail width in pixels.
 *   height: 140    # (int) (optional) The post thumbnail height in pixels.
 *   crop:   true   # (boolean) (optional) Crop the image or not. False - Soft proportional crop mode ; True - Hard crop mode.
 *
 * @see http://codex.wordpress.org/Function_Reference/add_image_size
 */
class MediaSizes extends BaseManager
{
    private static $defaults = array(
        'width' => 0,
        'height' => 0,
        'crop' => false,
    );

    public static function registerHook()
    {
        $manager = new static();
        add_action('init', array($manager, 'register'), 100);

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $media_sizes = $this->getConfigs();
        if (!$media_sizes) return;

        foreach ($media_sizes as $id => $media_size) {
            if (!empty($media_size) && is_array($media_size)) {
                add_image_size($id, $media_size['width'], $media_size['height'], $media_size['crop']);
            }
        }

        // List media sizes on /wp-admin/options-media.php
        add_action('admin_init', array($this, 'register_media_settings'));
    }

    protected function filterConfigs(array &$configs)
    {
        foreach ($configs as $id => &$config) {
            if (!empty($config) && is_array($config)) {
                static::array_defaults($config, self::$defaults);
            } else {
                unset($configs[$id]);
            }
        }
    }

    public function register_media_settings()
    {
        add_settings_field('media_size_list', __('Additional medias'), array($this, 'show_media_settings'), 'media', 'default');
    }

    public function show_media_settings()
    {
        foreach ($GLOBALS['_wp_additional_image_sizes'] as $id => $media) {
            echo "<p><b>$id</b>: {$media['width']}x{$media['height']}", $media['crop'] ? ', cropped' : '';
        }
    }
}
