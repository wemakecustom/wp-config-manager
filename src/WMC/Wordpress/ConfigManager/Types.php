<?php

namespace WMC\Wordpress\ConfigManager;

class Types extends BaseManager
{
    private $exported = array();

    public static function registerHook()
    {
        if (is_admin()) {
            $manager = new static();
            add_action('wpcf_after_init', array($manager, 'sync'));
            // Hook on import-export page
            add_action('load-types_page_wpcf-import-export', array($manager, 'export'), 0 /* priority */);

            return $manager;
        } else {
            return false;
        }
    }

    /**
     * Compare checksums of types and import accordingly
     */
    public function sync()
    {
        $index   = array();
        $configs = $this->getConfigs();
        $current = get_option('types-manager-index', array());
        $changed = false;

        foreach ($configs as $export_type => $types) {
            if (empty($types['__key']))  continue;
            $__key = $types['__key'];

            foreach ($types as $id => $data) {
                if ($id == '__key') continue;

                $index[$export_type][$id] = md5(serialize($data));

                if (!isset($current[$export_type][$id]) || $current[$export_type][$id] != $index[$export_type][$id]) {
                    $this->import($export_type, $__key, $id, $data);
                    $changed = true;
                }
            }
        }

        if ($changed) {
            require_once WPCF_INC_ABSPATH . '/fields.php';
            require_once WPCF_INC_ABSPATH . '/import-export.php';

            wpcf_init_custom_types_taxonomies();
            flush_rewrite_rules();
            update_option('types-manager-index', $index);
        }
    }

    /**
     * Import a single type
     */
    private function import($export_type, $__key, $id, $data)
    {
        require_once WPCF_INC_ABSPATH . '/fields.php';
        require_once WPCF_INC_ABSPATH . '/import-export.php';

        // Import function checks for this
        $_POST['items'][$export_type][$id] = 1;
        $_POST['overwrite-groups'] = $_POST['overwrite-types'] = $_POST['overwrite-fields'] = $_POST['overwrite-tax'] = 1;

        $xml = self::toXml(array(
            $export_type => array(
                $id => $data,
                '__key' => $__key
            )
        ), 'types');
        $updated = wpcf_admin_import_data($xml, false);

        // Undo our hack
        unset($_POST['items'][$export_type][$id], $_POST['overwrite-groups'], $_POST['overwrite-types'], $_POST['overwrite-fields'], $_POST['overwrite-tax']);

        return $updated;
    }

    private static function toXml($data, $root_element)
    {
        require_once WPCF_EMBEDDED_ABSPATH . '/common/array2xml.php';
        $xml = new \ICL_Array2XML();

        return $xml->array2xml($data, $root_element);
    }

    /**
     * Replace export process by saving each type in a separate file
     */
    public function export()
    {
        if (empty($_POST['export'])) {
            $this->addMessage('updated', "Types will be saved to your theme’s directory and imported automatically.");

            return; // Not exporting
        }
        $_POST = array(); // Cancel export process

        $data = wpcf_admin_export_selected_data( array(), 'all', 'array');

        foreach ($data as $export_type => $exported) {
            $tagname = $exported['__key'];

            foreach ($exported as $id => $type) {
                if (empty($type['id']) && empty($type['ID'])) continue; // not a type
                if ($export_type == 'groups') {
                    // Use name instead of ID for groups
                    $type['ID'] = $id = $type['__types_id'];
                }

                // We provide our own checksums, but still leave them or the import will complain
                $type['hash'] = '';
                $type['checksum'] = '';

                $type_data = array($export_type => array($id => $type, '__key' => $tagname));
                self::writeConfigs("types/$export_type/$id", $type_data);

                $index[$export_type][$id] = md5(serialize($type));
                $notices[] = $type['__types_title'];
            }
        }

        update_option('types-manager-index', $index);
        $this->addMessage('updated', "The following types were exported: " . implode(', ', $notices));

        // Ensure plugin is loaded
        self::writeConfigs("plugins/types", array(
            'plugins' => array('wp-types' => 'Types'),
        ));
    }
}
