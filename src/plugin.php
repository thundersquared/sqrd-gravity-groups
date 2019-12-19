<?php
/**
 * Plugin Name: Register GF user to UM Group
 * Description: Gravity Forms and Ultimate Member bridge that links user to chosen group on registration.
 * Version: PLUGIN_VERSION
 * Author: thundersquared
 * Author URI: https://thundersquared.com/
 * Text Domain: sqrd-gravity-groups
 */

namespace sqrd\Features\GravityFormsUltimateMember;

use GFAPI;
use function UM;

defined('ABSPATH') || exit;

class RegisterToGroup
{
    public function __construct()
    {
        add_action('gform_user_registered', array($this, 'process_registration'), 10, 3);
        register_activation_hook(__FILE__, array($this, 'do_activation'));
    }

    public function do_activation()
    {
        foreach (array(
            'ultimate-member/ultimate-member.php',
            'um-groups/um-groups.php',
            'gravityforms/gravityforms.php',
            'gravityformsuserregistration/userregistration.php'
        ) as $plugin)
        {
            if (!is_plugin_active($plugin))
            {
                $this->deactivate();
            }
        }
    }

    private function deactivate()
    {
        if (is_plugin_active(plugin_basename(__FILE__)))
        {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(_('Ultimate Member, UM Groups, Gravity Forms and GF User Registration should all be activated first.'));
        }
    }

    public function process_registration($user_id, $feed, $entry)
    {
        global $wpdb;

        $field_id = null;

        // Retrieve related form
        $form_id = $entry['form_id'];
        $form = GFAPI::get_form($form_id);

        // Scan fields to find the Group ID field's ID
        foreach ($form['fields'] as $field)
        {
            if ($field['adminLabel'] === 'Group ID')
            {
                $field_id = $field['id'];
            }
        }

        // Stop processing if no ID found
        if (is_null($field_id))
        {
            return false;
        }

        // Select entry data given field ID
        $group_id = $entry[$field_id];
        $user_id2 = um_user('ID');

        // Get UM Groups API
        $api = UM()->Groups()->api();

        if ($api->has_joined_group($user_id, $group_id))
        {
            return false;
        }

        $arr_member = array(
            'user_id' => $user_id,
            'group_id' => $group_id,
        );

        $table_name = UM()->Groups()->setup()->db_groups_table;

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'group_id' => $group_id,
                'user_id1' => $user_id,
                'user_id2' => $user_id2,
                'status' => 'approved',
                'role' => 'member',
                'date_joined' => date('Y-m-d H:i:s', current_time('timestamp'))
            ),
            array(
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s'
            )
        );

        if ($inserted && $wpdb->insert_id)
        {
            $wpdb->query("
                UPDATE `$table_name` SET
                    `user_id1` = $user_id,
                    `user_id2` = $user_id2
                WHERE `id` = $wpdb->insert_id;");
        }

        $api->count_members($group_id, true);

        return true;
    }
}

new RegisterToGroup();
