<?php

/*
Plugin Name: WPU Contact forms
Plugin URI: https://github.com/WordPressUtilities/wpucontactforms
Version: 0.5.3
Description: Contact forms
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpucontactforms {

    private $plugin_version = '0.5.3';

    public function __construct($options = array()) {
        global $wpucontactforms_forms;
        if (!isset($options['id'])) {
            return;
        }
        if (in_array($options['id'], $wpucontactforms_forms)) {
            return;
        }
        load_plugin_textdomain('wpucontactforms', false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $wpucontactforms_forms[] = $options['id'];

        $this->set_options($options);
        add_action('template_redirect', array(&$this,
            'post_contact'
        ), 10, 1);
        add_action('wpucontactforms_content', array(&$this,
            'page_content'
        ), 10, 2);

        if ($this->options['contact__settings']['ajax_enabled']) {
            add_action('wp_ajax_wpucontactforms', array(&$this,
                'ajax_action'
            ));
            add_action('wp_ajax_nopriv_wpucontactforms', array(&$this,
                'ajax_action'
            ));
            add_action('wp_enqueue_scripts', array(&$this,
                'form_scripts'
            ));
        }
    }

    public function form_scripts() {
        wp_enqueue_script('jquery-form');
        wp_enqueue_script('wpucontactforms-front', plugins_url('assets/front.js', __FILE__), array(
            'jquery'
        ), $this->plugin_version, true);

        // Pass Ajax Url to script.js
        wp_localize_script('wpucontactforms-front', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function set_options($options) {

        $this->is_successful = false;
        $this->has_upload = false;
        $this->content_contact = '';
        $this->default_field = array(
            'value' => '',
            'type' => 'text',
            'validation' => '',
            'validation_regexp' => '',
            'validation_pattern' => '',
            'html_before' => '',
            'html_after' => '',
            'box_class' => '',
            'placeholder' => '',
            'required' => 0,
            'datas' => array(
                __('No', 'wpucontactforms'),
                __('Yes', 'wpucontactforms')
            )
        );

        // Default options
        $default_options = array(
            'id' => 'default',
            'name' => 'Default',
            'contact__success' => apply_filters('wpucontactforms_success', '<p class="contact-success">' . __('Thank you for your message!', 'wpucontactforms') . '</p>'),
            'contact__settings' => array()
        );
        $this->options = array_merge($default_options, $options);

        // Settings
        $contact__settings = apply_filters('wpucontactforms_settings', array(
            'ajax_enabled' => true,
            'box_class' => 'box',
            'display_form_after_submit' => true,
            'label_text_required' => '<em>*</em>',
            'submit_class' => 'cssc-button cssc-button--default',
            'submit_label' => __('Submit', 'wpucontactforms'),
            'group_submit_class' => '',
            'box_tagname' => 'div',
            'group_tagname' => 'div',
            'group_class' => 'cssc-form cssc-form--default float-form',
            'file_types' => array(
                'image/png',
                'image/jpg',
                'image/jpeg',
                'image/gif'
            ),
            'max_file_size' => 2 * 1024 * 1024,
            'attach_to_post' => get_the_ID()
        ));

        $this->options['contact__settings'] = array_merge($contact__settings, $this->options['contact__settings']);

        $this->contact_fields = apply_filters('wpucontactforms_fields', array(
            'contact_name' => array(
                'label' => __('Name', 'wpucontactforms'),
                'required' => 1
            ),
            'contact_email' => array(
                'label' => __('Email', 'wpucontactforms'),
                'type' => 'email',
                'required' => 1
            ),
            'contact_message' => array(
                'label' => __('Message', 'wpucontactforms'),
                'type' => 'textarea',
                'required' => 1
            )
        ), $this->options);

        // Testing missing settings
        foreach ($this->contact_fields as $id => $field) {

            // Merge with default field.
            $this->contact_fields[$id] = array_merge(array(), $this->default_field, $field);

            $this->contact_fields[$id]['id'] = $id;

            // Validation = type by default
            if (empty($this->contact_fields[$id]['validation'])) {
                $this->contact_fields[$id]['validation'] = $this->contact_fields[$id]['type'];
            }

            if ($this->contact_fields[$id]['type'] == 'file') {
                $this->has_upload = true;
            }

            // Default label
            if (!isset($field['label'])) {
                $this->contact_fields[$id]['label'] = ucfirst(str_replace('contact_', '', $id));
            }

            if (!isset($field['datas'])) {
                $this->contact_fields[$id]['datas'] = $this->default_field['datas'];
            }

            if (!is_array($field['datas'])) {
                $values = explode("\n", get_option($field['datas']));
                $this->contact_fields[$id]['datas'] = array();
                foreach ($values as $val) {
                    $val = trim($val);
                    $this->contact_fields[$id]['datas'][md5($val)] = $val;
                }
            }
        }

    }

    public function page_content($hide_wrapper = false, $form_id = false) {

        if (!$form_id || $form_id != $this->options['id']) {
            return '';
        }

        $content_form = '';

        // Display contact form
        $content_form .= '<form class="wpucontactforms__form" action="" aria-live="assertive" method="post" ' . ($this->has_upload ? 'enctype="multipart/form-data' : '') . '"><' . $this->options['contact__settings']['group_tagname'] . ' class="' . $this->options['contact__settings']['group_class'] . '">';
        foreach ($this->contact_fields as $field) {
            $content_form .= $this->field_content($field);
        }

        /* Quick honeypot */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="screen-reader-text">';
        $content_form .= '<label>If you are human, leave this empty</label>';
        $content_form .= '<input tabindex="-1" name="hu-man-te-st" type="text"/>';
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_class'] . '">';
        $default_hidden_fields = array(
            'form_id' => $form_id,
            'control_stripslashes' => '&quot;',
            'wpucontactforms_send' => '1',
            'action' => 'wpucontactforms'
        );
        $hidden_fields = apply_filters('wpucontactforms_hidden_fields', $default_hidden_fields, $this->options);
        foreach ($hidden_fields as $name => $value) {
            $content_form .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        }
        $content_form .= '<button class="' . $this->options['contact__settings']['submit_class'] . '" type="submit">' . $this->options['contact__settings']['submit_label'] . '</button>
        </' . $this->options['contact__settings']['box_tagname'] . '>';

        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        $content_form .= '</form>';
        if ($this->is_successful && !$this->options['contact__settings']['display_form_after_submit']) {
            $content_form = '';
        }

        if ($hide_wrapper !== true) {
            echo '<div class="wpucontactforms-form-wrapper">';
        }
        echo $this->content_contact;
        echo $content_form;
        if ($hide_wrapper !== true) {
            echo '</div>';
        }
    }

    public function field_content($field) {
        $content = '';
        $id = $field['id'];
        $id_html = $this->options['id'] . '_' . $id;
        $field_id_name = ' id="' . $id_html . '" name="' . $id . '" aria-labelledby="label-' . $id . '" aria-required="' . ($field['required'] ? 'true' : 'false') . '" ';
        // Required
        if ($field['required']) {
            $field_id_name .= ' required="required"';
        }
        // Placeholder
        $placeholder = __('Select a value');
        if (!empty($field['placeholder'])) {
            $field_id_name .= ' placeholder="' . esc_attr($field['placeholder']) . '"';
            $placeholder = $field['placeholder'];
        }
        // Validation
        if (isset($field['validation_pattern']) && !empty($field['validation_pattern'])) {
            $field_id_name .= ' pattern="' . $field['validation_pattern'] . '"';
        }
        // Value
        $field_val = 'value="' . $field['value'] . '"';

        // Additional HTML
        $after_checkbox = isset($field['html_after_checkbox']) ? $field['html_after_checkbox'] : '';
        $before_checkbox = isset($field['html_before_checkbox']) ? $field['html_before_checkbox'] : '';

        $label_content = '';
        if (isset($field['label'])) {
            $label_content = $field['label'] . ' ' . ($field['required'] ? $this->options['contact__settings']['label_text_required'] : '');
        }
        if (!empty($label_content)) {
            $content .= '<label id="label-' . $id . '" for="' . $id_html . '">' . $label_content . '</label>';
        }
        switch ($field['type']) {
        case 'select':
            $content .= '<select  ' . $field_id_name . '>';
            $content .= '<option value="" disabled selected style="display:none;">' . esc_html($placeholder) . '</option>';
            foreach ($field['datas'] as $key => $val) {
                $content .= '<option ' . (!empty($field['value']) && $field['value'] == $key ? 'selected="selected"' : '') . ' value="' . esc_attr($key) . '">' . $val . '</option>';
            }
            $content .= '</select>';
            break;
        case 'file':
            $content .= '<input type="file" accept="' . implode(',', $this->options['contact__settings']['file_types']) . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'checkbox':
            $content = '<label id="label-' . $id . '" class="label-checkbox">' . $before_checkbox . '<input type="' . $field['type'] . '" ' . $field_id_name . ' value="1" />' . $after_checkbox . ' ' . $label_content . '</label>';
            break;
        case 'text':
        case 'url':
        case 'email':
            $content .= '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'textarea':
            $content .= '<textarea cols="30" rows="5" ' . $field_id_name . '>' . $field['value'] . '</textarea>';
            break;
        }

        return $field['html_before'] . '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' ' . $field['box_class'] . '">' . $content . '</' . $this->options['contact__settings']['box_tagname'] . '>' . $field['html_after'];
    }

    public function post_contact() {

        // Checking before post
        if (empty($_POST) || !isset($_POST['wpucontactforms_send'])) {
            return false;
        }

        if (!isset($_POST['form_id']) || $_POST['form_id'] != $this->options['id']) {
            return false;
        }

        // Initial settings
        $this->msg_errors = array();

        do_action('wpucontactforms_beforesubmit_contactform', $this);

        // Checking for PHP Conf
        if (isset($_POST['control_stripslashes']) && $_POST['control_stripslashes'] == '\"') {
            foreach ($_POST as $id => $field) {
                $_POST[$id] = stripslashes($field);
            }
        }

        // Checking bots
        if (!isset($_POST['hu-man-te-st']) || !empty($_POST['hu-man-te-st'])) {
            return;
        }

        $this->contact_fields = $this->extract_value_from_post($_POST, $this->contact_fields);

        if (isset($this->contact_fields['contact_message'])) {
            $contact_message = apply_filters('wpucontactforms_message', $this->contact_fields['contact_message']['value']);
            if (is_array($contact_message)) {
                foreach ($contact_message as $msg) {
                    $this->msg_errors[] = $msg;
                }
            } else {
                $this->contact_fields['contact_message']['value'] = $contact_message;
            }
        }

        if (!empty($this->msg_errors)) {
            $this->content_contact .= '<p class="contact-error"><strong>' . __('Error:', 'wpucontactforms') . '</strong><br />' . implode('<br />', $this->msg_errors) . '</p>';
            return;
        }

        $this->content_contact = '';

        // Setting success message
        $this->content_contact .= $this->options['contact__success'];

        // Trigger success action
        do_action('wpucontactforms_submit_contactform', $this);

        $this->is_successful = true;

    }

    public function extract_value_from_post($post, $contact_fields) {
        foreach ($contact_fields as $id => $field) {

            $tmp_value = '';
            if (isset($post[$id])) {
                $tmp_value = trim(htmlentities(strip_tags($post[$id])));
            }

            if ($field['type'] == 'file') {
                if (isset($_FILES[$id]) && $_FILES[$id]['error'] == 0) {
                    $tmp_value = $_FILES[$id]['tmp_name'];
                }
            }

            if ($field['type'] == 'checkbox' && empty($tmp_value)) {
                $tmp_value = '0';
            }

            if ($tmp_value != '') {
                if ($field['type'] == 'file') {
                    $field_ok = $this->validate_field_file($_FILES[$id], $field);
                } else {
                    $field_ok = $this->validate_field($tmp_value, $field);
                }

                if (!$field_ok) {
                    $this->msg_errors[] = sprintf(__('The field "%s" is not correct', 'wpucontactforms'), $field['label']);
                } else {

                    if ($field['type'] == 'select') {
                        $tmp_value = $field['datas'][$tmp_value];
                    }

                    if ($field['type'] == 'checkbox') {
                        $tmp_value = ($tmp_value == '1') ? __('Yes') : __('No');
                    }

                    if ($field['type'] == 'file') {
                        $tmp_value = $this->upload_file_return_att_id($_FILES[$id], $field);
                    }

                    $contact_fields[$id]['value'] = $tmp_value;
                }
            } else {
                if ($field['required']) {
                    $this->msg_errors[] = sprintf(__('The field "%s" is required', 'wpucontactforms'), $field['label']);
                }
            }
        }
        return $contact_fields;
    }

    public function upload_file_return_att_id($file, $field) {

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload($field['id'], $this->options['contact__settings']['attach_to_post']);

        if (is_wp_error($attachment_id)) {
            return false;
        } else {
            return $attachment_id;
        }
    }

    public function validate_field_file($file, $field) {

        // Max size
        if ($file['size'] >= $this->options['contact__settings']['max_file_size']) {
            return false;
        }

        // Type
        if (!in_array($file['type'], $this->options['contact__settings']['file_types'])) {
            return false;
        }

        return true;
    }

    public function validate_field($tmp_value, $field) {
        $zero_one = array('0', '1');
        switch ($field['validation']) {
        case 'select':
            return array_key_exists($tmp_value, $field['datas']);
            break;
        case 'checkbox':
            return in_array($tmp_value, $zero_one);
            break;
        case 'email':
            return filter_var($tmp_value, FILTER_VALIDATE_EMAIL) !== false;
            break;
        case 'url':
            return filter_var($tmp_value, FILTER_VALIDATE_URL) !== false;
            break;
        case 'regexp':
            return isset($field['validation_regexp']) && (preg_match($field['validation_regexp'], $tmp_value) == 1);
            break;
        }
        return true;
    }

    public function ajax_action() {
        if (!isset($_POST['form_id']) || $_POST['form_id'] != $this->options['id']) {
            return;
        }
        $this->post_contact();
        $this->page_content(true, $this->options['id']);
        die;
    }
}

/* ----------------------------------------------------------
  Init
---------------------------------------------------------- */

$wpucontactforms_forms = array();

add_action('init', 'launch_wpucontactforms_default');
function launch_wpucontactforms_default() {
    new wpucontactforms();
}

/* ----------------------------------------------------------
  Shortcode for form
---------------------------------------------------------- */

add_shortcode('wpucontactforms_form', 'wpucontactforms__content');
function wpucontactforms__content($atts) {
    $id = isset($atts['id']) ? $atts['id'] : 'default';
    do_action('wpucontactforms_content', false, $id);
}

/* ----------------------------------------------------------
  Antispam
---------------------------------------------------------- */

/* Count number of links
 -------------------------- */

add_filter('wpucontactforms_message', 'wpucontactforms_message___maxlinks', 10, 1);
function wpucontactforms_message___maxlinks($message) {
    $maxlinks_nb = apply_filters('wpucontactforms_message___maxlinks__nb', 5);
    $http_count = substr_count($message, 'http');
    if ($http_count > $maxlinks_nb) {
        return array(
            sprintf(__('No more than %s links, please', 'wpucontactforms'), $maxlinks_nb)
        );
    }
    return $message;
}

/* ----------------------------------------------------------
  Success actions
---------------------------------------------------------- */

/* Send mail
-------------------------- */

add_action('wpucontactforms_submit_contactform', 'wpucontactforms_submit_contactform__sendmail', 10, 1);
function wpucontactforms_submit_contactform__sendmail($form) {

    $sendmail_intro = apply_filters('wpucontactforms__sendmail_intro', '<p>' . __('Message from your contact form', 'wpucontactforms') . '</p>', $form);
    $sendmail_subject = apply_filters('wpucontactforms__sendmail_subject', __('Message from your contact form', 'wpucontactforms'), $form);

    // Send mail
    $mail_content = $sendmail_intro;
    $attachments_to_destroy = array();
    $more = array(
        'attachments' => array()
    );

    // Target Email
    $target_email = get_option('admin_email');
    $wpu_opt_email = get_option('wpu_opt_email');
    if (is_email($wpu_opt_email)) {
        $target_email = $wpu_opt_email;
    }
    $target_email = apply_filters('wpucontactforms_email', $target_email, $form->options);

    foreach ($form->contact_fields as $id => $field) {

        if ($field['type'] == 'file') {

            // Store attachment id
            $attachments_to_destroy[] = $form->contact_fields[$id]['value'];

            // Add to mail attachments
            $more['attachments'][] = get_attached_file($form->contact_fields[$id]['value']);
            continue;
        }

        // Emptying values
        $mail_content .= '<hr /><p><strong>' . $field['label'] . '</strong>:<br />' . $field['value'] . '</p>';
    }

    if (function_exists('wputh_sendmail')) {
        wputh_sendmail($target_email, $sendmail_subject, $mail_content, $more);
    } else {
        wp_mail($target_email, $sendmail_subject, $mail_content, '', $more['attachments']);
    }

    // Delete temporary attachments
    if (apply_filters('wpucontactforms__sendmail_delete_attachments', true)) {
        foreach ($attachments_to_destroy as $att_id) {
            wp_delete_attachment($att_id);
        }
    }
}

/* Save post
-------------------------- */

add_action('init', 'wpucontactforms_submit_contactform__savepost__objects');
function wpucontactforms_submit_contactform__savepost__objects() {
    add_action('wpucontactforms_submit_contactform', 'wpucontactforms_submit_contactform__savepost', 10, 1);

    // Create a new taxonomy
    register_taxonomy(
        'contact_form',
        array('contact_message'),
        array(
            'label' => __('Form'),
            'show_admin_column' => true
        )
    );

    // Create a new post type
    register_post_type('contact_message',
        array(
            'labels' => array(
                'name' => __('Messages'),
                'singular_name' => __('Message')
            ),
            'menu_icon' => 'dashicons-email-alt',
            'public' => true,
            'taxonomies' => array('contact_form'),
            'publicly_queryable' => false,
            'has_archive' => false
        )
    );

    add_filter('manage_taxonomies_for_activity_columns', 'activity_type_columns');

}

function wpucontactforms_submit_contactform__savepost($form) {

    $taxonomy = apply_filters('wpucontactforms__createpost_taxonomy', 'contact_form', $form);
    $post_content = '';
    $post_metas = array();
    $attachments = array();

    foreach ($form->contact_fields as $id => $field) {

        if ($field['type'] == 'file') {
            $attachments[] = $form->contact_fields[$id]['value'];
            continue;
        }

        $post_content .= '<p><strong>' . $field['label'] . '</strong>:<br />' . $field['value'] . '</p><hr />';
        $post_metas[$id] = $field['value'];
    }

    // Create post
    $post_id = wp_insert_post(array(
        'post_title' => apply_filters('wpucontactforms__createpost_post_title', 'New email', $form),
        'post_type' => apply_filters('wpucontactforms__createpost_post_type', 'contact_message', $form),
        'post_content' => apply_filters('wpucontactforms__createpost_post_content', $post_content, $form),
        'post_author' => apply_filters('wpucontactforms__createpost_postauthor', 1, $form),
        'post_status' => 'publish'
    ));

    // Add metas
    foreach ($post_metas as $id => $value) {
        update_post_meta($post_id, $id, $value);
    }

    // Add term
    $term = wp_insert_term(
        $form->options['name'],
        $taxonomy,
        array(
            'slug' => $form->options['id']
        )
    );

    $term_id = 0;
    if (!is_wp_error($term) && isset($term['term_id'])) {
        $term_id = $term['term_id'];
    }

    if (is_wp_error($term)) {
        $term_id_tmp = $term->get_error_data();
        if (is_numeric($term_id_tmp)) {
            $term_id = $term_id_tmp;
        }
    }

    if (is_numeric($term_id) && $term_id) {
        wp_set_post_terms($post_id, array($term_id), $taxonomy);
    }

    // Link attachments to the new post
    foreach ($attachments as $att_id) {
        wp_update_post(array(
            'ID' => $att_id,
            'post_parent' => $post_id
        ));
    }
}
