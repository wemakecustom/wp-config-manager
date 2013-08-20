<?php

namespace WMC\Wordpress\ConfigManager;

use Symfony\Component\Yaml\Yaml;

abstract class BaseManager
{
    private static $managers = array();
    private static $messages = array();
    private static $cached_configs = false;
    const CACHE_FILE = /* ABSPATH . */ 'wp-content/config-manager.json';

    protected $name; // Must be set
    protected $theme;
    private $configs = null;

    public function __construct()
    {
        if (empty($this->name)) {
            $parts = explode('\\', get_called_class());
            $name = array_pop($parts);
            $name = preg_replace('/([a-z])([A-Z])/', "$1_$2", $name);
            $this->name = strtolower($name);
        }

        $this->theme     = wp_get_theme();

        self::$managers[$this->name] = $this;
    }

    /**
     * Shows notices of all ConfigManagers at once
     */
    public static function showMessages()
    {
        foreach (self::$messages as $class => $messages) {
            foreach ($messages as $message) {
                echo
                "<div class=\"$class\">
                    <p>$message</p>
                </div>";
            }
        }
    }

    /**
     * Return an array of classes that extend BaseManager
     * By default, returns all plugins in lib directory
     */
    private static function getPlugins()
    {
        $plugins = array();

        foreach (glob(__DIR__ . '/*.php') as $file) {
            if ($file != __FILE__) {
                require_once $file;
                $plugins[] = 'WMC\Wordpress\ConfigManager\\' . basename($file, '.php');
            }
        }

        return apply_filters('config_manager_plugins', $plugins);
    }

    public static function initSystem()
    {
        foreach (self::getPlugins() as $class) {
            if (class_exists($class, true) && is_subclass_of($class, __CLASS__)) {
                $class::registerHook();
            }
        }

        add_action('admin_notices', array(__CLASS__, 'showMessages'));

        if (!WP_DEBUG) {
            self::preloadConfigs();
        }
    }

    private static function preloadConfigs()
    {
        $cache_file = ABSPATH . self::CACHE_FILE;
        if (file_exists($cache_file)) {
            $cache = json_decode(file_get_contents($cache_file), true);

            foreach (self::$managers as $name => $manager) {
                if (isset($cache[$name])) {
                    $manager->configs = $cache[$name];
                }
            }
        } else {
            $cache = array();

            foreach (self::$managers as $name => $manager) {
                $cache[$name] = $manager->getConfigs();
            }

            file_put_contents($cache_file, json_encode($cache));
        }
    }

    public static function clearCache()
    {
        $cache_file = ABSPATH . self::CACHE_FILE;
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }

    protected function addMessage($class, $message)
    {
        $name = array_pop(explode('\\', get_class($this)));
        self::$messages[$class][] = "<b>$name:</b> $message";
    }

    /**
     * Checks if a config is available from the cache.
     * If not, calls the real config loader
     *
     * @param $name string Config name, represents the name of the file. Ex: wp-content/themes/$theme/config/$name.yml
     * @return array
     */
    public function getConfigs()
    {
        if (null !== $this->configs) {
            return $this->configs;
        }

        $this->configs = array();
        $this->loadConfigs($this->theme);
        $this->filterConfigs($this->configs);

        return $this->configs;
    }

    /**
     * @param $name string Ex: sidebar or types/types/slide
     * @param $data array
     * @param $theme WP_Theme (optional) defaults to current theme
     * @return string md5sum or false on failure
     */
    public static function writeConfigs($name, array $data, \WP_Theme $theme = null)
    {
        $theme = $theme ?: wp_get_theme();

        static::filterSaveConfigs($data);
        $yml = Yaml::dump($data, 5);
        // Replace harcoded strings that are gonna be parsed on import
        $yml = static::replaceHarcodedStrings($yml, $theme);

        $file = $theme->get_stylesheet_directory() . "/config/$name.yml";
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return false;
            }
        }

        if (file_put_contents($file, trim($yml) . PHP_EOL)) {
            self::clearCache();

            return md5_file($file);
        } else {
            return false;
        }
    }

    /**
     * Replaces hardcoded strings like site_url and theme directories by variables
     * On import, PHP is executed
     * @param $yml string
     * @return string
     */
    protected static function replaceHarcodedStrings($yml, $theme = null)
    {
        $theme = $theme ?: wp_get_theme();

        $mappings = array(
            $theme->get_stylesheet_directory_uri() => '<?php echo $theme_uri; ?>',
            $theme->get_stylesheet_directory()     => '<?php echo $theme_dir; ?>',
            site_url()                             => '<?php echo site_url(); ?>',
            ABSPATH                                => '<?php echo ABSPATH; ?>',
        );

        return str_replace(array_keys($mappings), array_values($mappings), $yml);
    }

    /**
     * Sort configs by key and convert numbers to int, recursively
     * This is to reduce file differences
     */
    protected static function filterSaveConfigs(array &$configs)
    {
        ksort($configs);
        foreach ($configs as &$config) {
            if (is_array($config) && !empty($config)) {
                static::filterSaveConfigs($config);
            } elseif (is_numeric($config)) {
                $config = (int) $config;
            }
        }
    }

    /**
     * Loads recursively the config file from a theme and its parents
     *
     * @param $theme WP_Theme Current theme
     * @return array
     */
    private function loadConfigs(\WP_Theme $theme)
    {
        if (false !== $theme->parent()) {
            $this->loadConfigs($theme->parent());
        }

        $theme_dir = $theme->get_stylesheet_directory();
        $path = "$theme_dir/config/" . $this->name;

        if (is_dir($path)) {
            $this->loadDir($path, $theme);
        }
        if (is_file("$path.yml")) {
            $this->loadFile("$path.yml", $theme);
        }
    }

    private function loadDir($dir, \WP_Theme $theme)
    {
        if (is_dir($dir)) {
            foreach (glob("$dir/*") as $file) {
                $this->loadDir($file, $theme);
            }
        } else {
            $this->loadFile($dir, $theme);
        }
    }

    private function loadFile($file, \WP_Theme $theme)
    {
        if (!is_file($file) || !preg_match('/\.ya?ml$/', $file)) return;

        $theme_dir = $theme->get_stylesheet_directory();
        $theme_uri = $theme->get_stylesheet_directory_uri();

        ob_start();
        include($file);
        $content = ob_get_clean();

        self::array_overwrite($this->configs, Yaml::parse($content));
    }

    /**
     * Allows sub-classes to modify configs as they are loaded, before caching occurs
     */
    protected function filterConfigs(array &$configs)
    {
    }

    /**
     * Equivalent of array_merge, but overwrites an existing key instead of merging.
     *
     * @param $original array Array to be overwritten
     * @param $overwrite array Array to merge into $original
     */
    protected static function array_overwrite(&$original, $overwrite)
    {
        // Not included in function signature so we can return silently if not an array
        if (!is_array($overwrite)) {
            return;
        }
        if (!is_array($original)) {
            $original = $overwrite;
        }

        foreach ($overwrite as $key => $value) {
            if (array_key_exists($key, $original) && is_array($value)) {
                self::array_overwrite($original[$key], $overwrite[$key]);
            } else {
                $original[$key] = $value;
            }
        }
    }

    protected static function array_defaults(&$original, $defaults)
    {
        static::array_overwrite($defaults, $original);
        $original = $defaults;
    }

    protected static function error($msg, $level = E_USER_ERROR)
    {
        trigger_error($this->name . ": $msg", $level);
    }
}
