<?php

namespace WMC\Wordpress\ConfigManager;

/**
 * id:                                                       # Must be all in lowercase, with no spaces
 *   name:          '<?php _e( "Sidebar" ); ?>'
 *   description:   ~                                        # Text description of what/where the sidebar is. Shown on widget management screen.
 *   class:         ~                                        # CSS class name to assign to the widget HTML.
 *   before_widget: '<li id="%1$s" class="widget %2$s">'     # HTML to place before every widget. Uses sprintf for variable substitution
 *   after_widget:  '</li>\n'                                # HTML to place after every widget.
 *   before_title:  '<h2 class="widget-title">'               # HTML to place before every title.
 *   after_title:   '</h2>\n'                                # HTML to place after every title.
 */
class Sidebars extends BaseManager
{
    private static $defaults = array(
        'name'          => null,
        'description'   => '',
        'class'         => null,
        'before_widget' => '<li id="%1$s" class="widget %2$s">',
        'after_widget'  => '</li>\n',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>\n',
    );

    public static function registerHook()
    {
        $manager = new static();
        add_action('widgets_init', array($manager, 'register'));

        return $manager;
    }

    protected function filterConfigs(array &$configs)
    {
        foreach ($configs as $id => &$config) {
            static::array_defaults($config, self::$defaults);

            if (empty($config['name'])) {
                $config['name'] = ucwords(preg_replace('/[\-_]/', ' ', $id));
            }

            $config['id'] = $id;
        }
    }

    public function register()
    {
        $sidebars = $this->getConfigs();
        array_map('register_sidebar', $sidebars);
    }
}
