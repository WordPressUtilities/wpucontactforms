<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpucontactforms_options',
    'wpucontactforms__cron_hook_croninterval',
    'wpucontactforms__cron_hook_lastexec'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}

/* Delete all posts */
$allposts = get_posts(array(
    'post_type' => 'messages',
    'numberposts' => -1,
    'fields' => 'ids'
));
foreach ($allposts as $p) {
    wp_delete_post($p, true);
}
