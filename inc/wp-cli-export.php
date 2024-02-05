<?php
defined('ABSPATH') || die;

if (defined('WP_CLI') && WP_CLI) {
    define('WPUCONTACTFORMS_EXPORT_STR', 'Export messages from WPU Contact forms.');

    WP_CLI::add_command('wpucontactforms-export', function ($args = array(), $assoc_args = array()) {
        $wpucontactforms = new wpucontactforms();
        $wpucontactforms->trigger_export($assoc_args);
    }, array(
        'shortdesc' => WPUCONTACTFORMS_EXPORT_STR,
        'synopsis' => array()
    ));

}
