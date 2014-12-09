<?php

namespace WMC\Wordpress\ConfigManager;

/**
 * my-category:
 *   name: My Category
 *   description: ~
 *   taxonomy: category
 *   parent: my_parent_category
 */
class Categories extends BaseManager
{
    private static $defaults = array(
        'name' => null,
        'description' => '',
        'taxonomy' => 'category',
        'parent' => null,
    );

    public static function registerHook()
    {
        $manager = new static();
        add_action('wp_loaded', array($manager, 'register'), 100);

        return $manager;
    }

    /**
     * Registers all assets based on config/assets.yml of the theme (and its parents)
     */
    public function register()
    {
        $configs = $this->getConfigs();

        foreach ($configs as $slug => $category) {
            if (get_term_by('slug', $slug, $category['taxonomy']) !== false) {
                // Category exists
                continue;
            }

            $parent = 0;
            if (!empty($category['parent'])) {
                $p = get_term_by('slug', $category['parent'], $category['taxonomy']);
                if ($p) {
                    $parent = $p->term_id;
                } else {
                    $this->addMessage('error', "$slug: the parent {$category['parent']} does not exist.");
                }
            }

            $data = array(
                'description'=> $category['description'],
                'slug' => $slug,
                'parent'=> $parent,
            );

            wp_insert_term($category['name'], $category['taxonomy'], $data);

            $this->addMessage('updated', "Category {$category['name']} for taxonomy {$category['taxonomy']} has been added");
        }
    }

    protected function filterConfigs(array &$configs)
    {
        foreach ($configs as $id => &$config) {
            static::array_defaults($config, self::$defaults);

            if (empty($config['name'])) {
                $this->addMessage('error', 'Categories in categories.yml must have a name');
                unset($configs[$id]);
                continue;
            }

            if (!get_taxonomy($config['taxonomy'])) {
                $this->addMessage('error', "$slug: taxonomy {$config['taxonomy']} does not exist.");
                unset($configs[$id]);
                continue;
            }
        }
    }
}
