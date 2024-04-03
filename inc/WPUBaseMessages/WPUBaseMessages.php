<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Messages
Description: A class to handle messages in WordPress
Version: 1.3.4
Class URI: https://github.com/WordPressUtilities/wpubaseplugin
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') || die;

class WPUBaseMessages {

    private $transient_msg;
    private $transient_prefix;
    private $notices_categories = array(
        'notice',
        'updated',
        'update-nag',
        'error'
    );

    public function __construct($prefix = '') {
        if (wp_doing_cron()) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        $current_user = wp_get_current_user();
        if (is_object($current_user)) {
            $prefix .= $current_user->ID;
        }

        // Set Messages
        $this->transient_prefix = sanitize_title(basename(__FILE__)) . $prefix;
        $this->transient_msg = $this->transient_prefix . '__messages';

        // Add hook
        add_action('admin_notices', array(&$this,
            'admin_notices'
        ));
    }

    /* Set notices messages */
    public function set_message($id, $message, $group = '') {
        if (wp_doing_cron()) {
            return;
        }
        $messages = (array) get_transient($this->transient_msg);
        if (!in_array($group, $this->notices_categories)) {
            $group = $this->notices_categories[0];
        }
        $messages[$group][$id] = $message;
        set_transient($this->transient_msg, $messages);
    }

    /* Display notices */
    public function admin_notices() {
        if (wp_doing_cron()) {
            return;
        }
        $messages = (array) get_transient($this->transient_msg);
        if (!empty($messages)) {
            foreach ($messages as $group_id => $group) {
                if (is_array($group)) {
                    foreach ($group as $message) {
                        echo '<div class="' . $group_id . ' notice is-dismissible"><p>' . $message . '</p></div>';
                    }
                }
            }
        }

        // Empty messages
        delete_transient($this->transient_msg);
    }
}
