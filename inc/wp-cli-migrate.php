<?php
defined('ABSPATH') || die;

if (defined('WP_CLI') && WP_CLI) {
    define('WPUCONTACTFORMS_MIGRATE_STR', 'Migrate WPUContactForms attachments to the protected system.');

    WP_CLI::add_command('wpucontactforms-migrate', function ($args = array()) {
        echo "############\n";
        echo WPUCONTACTFORMS_MIGRATE_STR . "\n";
        echo "############\n";

        /* Ensure folders are ready */
        $wpucontactforms = new wpucontactforms();
        $wp_upload_dir = wp_get_upload_dir();
        $wpucontactforms->setup_upload_protection($wp_upload_dir);

        /* Select all post not yet migrated */
        global $wpdb;
        $q_pt = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type='%s'", wpucontactforms_savepost__get_post_type());
        $q_att_ok = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s", '_wpucontactforms_att');
        $q_att = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type='attachment' AND ID NOT IN(" . $q_att_ok . ") AND post_parent IN (" . $q_pt . ") ");

        if (empty($q_att)) {
            echo "No attachments are using the old system.\n";
            return;
        }

        $wp_upload_dir = wp_get_upload_dir();
        $dir = $wp_upload_dir['basedir'];

        $folder_name = apply_filters('wpucontactforms__uploads_dir', 'wpucontactforms');
        $base_new_dir = $wp_upload_dir['basedir'] . '/' . $folder_name;
        $base_old_dir = $wp_upload_dir['basedir'];

        $total_migrations = count($q_att);

        foreach ($q_att as $ii => $post_id) {
            echo ($ii + 1) . "/" . $total_migrations . "\n";
            $att_metadata = get_post_meta($post_id, '_wp_attachment_metadata', 1);
            $att_file = get_post_meta($post_id, '_wp_attached_file', 1);
            $new_att_file = $folder_name . '/' . $att_file;

            $old_file = $base_old_dir . '/' . $att_file;
            $old_dir = dirname($old_file);
            $old_file_rel = str_replace(ABSPATH, '', $old_file);
            $new_file = $base_new_dir . '/' . $att_file;
            $new_dir = dirname($new_file);
            $new_file_rel = str_replace(ABSPATH, '', $new_file);

            if (!file_exists($old_file)) {
                echo "Error : File does not exists : \n" . $old_file;
                continue;
            }

            if (!is_dir($new_dir)) {
                mkdir($new_dir, 0755, true);
            }

            /* Move attachment */
            rename($old_file, $new_file);
            $wpdb->query($wpdb->prepare("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s)", $old_file_rel, $new_file_rel));

            /* Move sizes */
            foreach ($att_metadata['sizes'] as $size) {
                $old_size_file = $old_dir . '/' . $size['file'];
                $old_size_file_rel = str_replace(ABSPATH, '', $old_size_file);
                $new_size_file = $new_dir . '/' . $size['file'];
                $new_size_file_rel = str_replace(ABSPATH, '', $new_size_file);

                if (file_exists($old_size_file)) {
                    rename($old_size_file, $new_size_file);
                    $wpdb->query($wpdb->prepare("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s)", $old_size_file_rel, $new_size_file_rel));
                }
            }

            /* Update post meta */
            $att_metadata['file'] = $new_att_file;
            update_post_meta($post_id, '_wp_attachment_metadata', $att_metadata);
            update_post_meta($post_id, '_wp_attached_file', $new_att_file);
            add_post_meta($post_id, '_wpucontactforms_att', '1');

        }

    }, array(
        'shortdesc' => WPUCONTACTFORMS_MIGRATE_STR,
        'synopsis' => array()
    ));
}
