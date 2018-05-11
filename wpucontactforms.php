<?php

/*
Plugin Name: WPU Contact forms
Plugin URI: https://github.com/WordPressUtilities/wpucontactforms
Version: 0.13.4
Description: Contact forms
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpucontactforms {

    private $plugin_version = '0.13.4';

    private $has_recaptcha = false;

    public function __construct($options = array()) {
        global $wpucontactforms_forms;
        if (!isset($options['id'])) {
            return;
        }
        if (!is_array($wpucontactforms_forms)) {
            $wpucontactforms_forms = array();
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
        add_action('wp_ajax_wpucontactforms_autofill', array(&$this,
            'ajax_action_autofill'
        ));
        add_action('wp_ajax_nopriv_wpucontactforms_autofill', array(&$this,
            'ajax_action_autofill'
        ));

        $this->has_recaptcha = $this->options['contact__settings']['recaptcha_enabled'] && $this->options['contact__settings']['recaptcha_sitekey'] && $this->options['contact__settings']['recaptcha_privatekey'];
        if ($this->has_recaptcha) {
            add_action('wp_head', array(&$this,
                'load_recaptcha'
            ));
        }

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

    public function load_recaptcha() {
        echo "<script src='https://www.google.com/recaptcha/api.js'></script>";
    }

    public function form_scripts() {
        wp_enqueue_script('jquery-form');
        wp_enqueue_script('wpucontactforms-front', plugins_url('assets/front.js', __FILE__), array(
            'jquery'
        ), $this->plugin_version, true);
        wp_enqueue_style('wpucontactforms-frontcss', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version, 'all');

        // Pass Ajax Url to script.js
        wp_localize_script('wpucontactforms-front', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function set_options($options) {

        $this->is_successful = false;
        $this->has_upload = false;
        $this->content_contact = '';
        $this->default_field = array(
            'box_class' => '',
            'classname' => '',
            'html_after' => '',
            'html_after_input' => '',
            'html_before' => '',
            'html_before_input' => '',
            'input_inside_label' => true,
            'placeholder' => '',
            'preload_value' => 0,
            'required' => 0,
            'type' => 'text',
            'validation' => '',
            'validation_pattern' => '',
            'validation_regexp' => '',
            'value' => '',
            'datas' => array(
                __('No', 'wpucontactforms'),
                __('Yes', 'wpucontactforms')
            )
        );

        // Default options
        $default_options = array(
            'id' => 'default',
            'name' => 'Default',
            'contact__display_form_after_success' => apply_filters('wpucontactforms_display_form_after_success', true),
            'contact__success' => apply_filters('wpucontactforms_success', '<p class="contact-success">' . __('Thank you for your message!', 'wpucontactforms') . '</p>'),
            'contact__settings' => array()
        );
        $this->options = array_merge($default_options, $options);

        // Settings
        $contact__settings = apply_filters('wpucontactforms_settings', array(
            'ajax_enabled' => true,
            'attach_to_post' => get_the_ID(),
            'box_class' => 'box',
            'box_tagname' => 'div',
            'content_before_wrapper_open' => '',
            'content_after_wrapper_open' => '',
            'content_before_content_form' => '',
            'content_after_content_form' => '',
            'content_before_wrapper_close' => '',
            'content_after_wrapper_close' => '',
            'content_before_recaptcha' => '',
            'content_after_recaptcha' => '',
            'display_form_after_submit' => true,
            'group_class' => 'cssc-form cssc-form--default float-form',
            'group_submit_class' => 'box--submit',
            'group_tagname' => 'div',
            'input_class' => 'input-text',
            'label_text_required' => '<em>*</em>',
            'max_file_size' => 2 * 1024 * 1024,
            'recaptcha_enabled' => false,
            'recaptcha_sitekey' => false,
            'recaptcha_privatekey' => false,
            'submit_class' => 'cssc-button cssc-button--default',
            'submit_label' => __('Submit', 'wpucontactforms'),
            'submit_type' => 'button',
            'contact_fields' => array(
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
            ),
            'file_types' => array(
                'image/png',
                'image/jpg',
                'image/jpeg',
                'image/gif'
            )
        ));

        $this->options['contact__settings'] = array_merge($contact__settings, $this->options['contact__settings']);

        $this->contact_fields = apply_filters('wpucontactforms_fields', $this->options['contact__settings']['contact_fields'], $this->options);

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
                $field['datas'] = $this->default_field['datas'];
            }

            if (!is_array($field['datas'])) {
                $values = explode("\n", get_option($field['datas']));
                $this->contact_fields[$id]['datas'] = array();
                foreach ($values as $val) {
                    $val = trim($val);
                    $this->contact_fields[$id]['datas'][md5($val)] = $val;
                }
            }

            if (!isset($field['custom_validation'])) {
                $this->contact_fields[$id]['custom_validation'] = array(&$this, 'default_validation');
            }

            if (!isset($field['preload_value'])) {
                $field['preload_value'] = false;
            }

            /* Preloading value from URL param */
            if (empty($field['value']) && $field['preload_value'] && !isset($field['autofill']) && !isset($_POST[$id]) && isset($_GET[$id]) && $this->validate_field($_GET[$id], $field)) {
                $this->contact_fields[$id]['value'] = $_GET[$id];
            }

        }

    }

    public function page_content($hide_wrapper = false, $form_id = false) {

        if (!$form_id || $form_id != $this->options['id']) {
            return '';
        }

        $form_autofill = false;
        $content_form = '';
        $content_fields = '';
        foreach ($this->contact_fields as $field) {
            $content_fields .= $this->field_content($field);
            if (isset($field['autofill'])) {
                $form_autofill = true;
            }
        }

        // Display contact form
        $content_form .= '<form class="wpucontactforms__form" action="" aria-live="assertive" method="post" ' . ($this->has_upload ? 'enctype="multipart/form-data' : '') . '"  data-autofill="' . ($form_autofill ? '1' : '0') . '"><' . $this->options['contact__settings']['group_tagname'] . ' class="' . $this->options['contact__settings']['group_class'] . '">';
        $content_form .= $content_fields;

        /* Quick honeypot */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="screen-reader-text">';
        $content_form .= '<label>If you are human, leave this empty</label>';
        $content_form .= '<input tabindex="-1" name="hu-man-te-st" type="text"/>';
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        if ($this->has_recaptcha) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' box-recaptcha">';
            $content_form .= $this->options['contact__settings']['content_before_recaptcha'];
            $content_form .= '<div class="g-recaptcha" data-callback="wpucontactforms_recaptcha_callback" data-expired-callback="wpucontactforms_recaptcha_callback_expired" data-sitekey="' . $this->options['contact__settings']['recaptcha_sitekey'] . '"></div>';
            $content_form .= $this->options['contact__settings']['content_after_recaptcha'];
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_class'] . '">';
        $hidden_fields = apply_filters('wpucontactforms_hidden_fields', array(
            'form_id' => $form_id,
            'control_stripslashes' => '&quot;',
            'wpucontactforms_send' => '1',
            'action' => 'wpucontactforms'
        ), $this->options);
        foreach ($hidden_fields as $name => $value) {
            $content_form .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        }
        if ($this->options['contact__settings']['submit_type'] == 'button') {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_class'] . '" type="submit"><span>' . $this->options['contact__settings']['submit_label'] . '</span></button>';
        } else {
            $content_form .= '<input class="' . $this->options['contact__settings']['submit_class'] . '" type="submit" value="' . esc_attr($this->options['contact__settings']['submit_label']) . '">';
        }
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        $content_form .= '</form>';
        if ($this->is_successful && !$this->options['contact__settings']['display_form_after_submit']) {
            $content_form = '';
        }

        if ($hide_wrapper !== true) {
            echo $this->options['contact__settings']['content_before_wrapper_open'];
            echo '<div class="wpucontactforms-form-wrapper">';
            echo $this->options['contact__settings']['content_after_wrapper_open'];
        }
        echo $this->content_contact;
        if (!$this->is_successful || ($this->is_successful && $this->options['contact__display_form_after_success'])) {
            echo $this->options['contact__settings']['content_before_content_form'];
            echo $content_form;
            echo $this->options['contact__settings']['content_after_content_form'];
        }
        if ($hide_wrapper !== true) {
            echo $this->options['contact__settings']['content_before_wrapper_close'];
            echo '</div>';
            echo $this->options['contact__settings']['content_after_wrapper_close'];
        }
    }

    public function field_content($field) {
        $content = '';
        $id = $field['id'];
        $id_html = $this->options['id'] . '_' . $id;
        $field_id_name = '';
        if ($field['type'] != 'radio') {
            $field_id_name .= ' id="' . $id_html . '" aria-labelledby="label-' . $id . '"';
        }

        $field_id_name .= ' name="' . $id . '"  aria-required="' . ($field['required'] ? 'true' : 'false') . '" ';
        // Required
        if ($field['required']) {
            $field_id_name .= ' required="required"';
        }
        // Classname
        $classname = '';
        if ($this->options['contact__settings']['input_class']) {
            $classname = $this->options['contact__settings']['input_class'];
        }
        if ($field['classname']) {
            $classname = $field['classname'];
        }
        if ($classname) {
            $field_id_name .= ' class="' . esc_attr($classname) . '"';
        }
        // Placeholder
        $placeholder = __('Select a value', 'wpucontactforms');
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

        $content .= $field['html_before_input'];

        switch ($field['type']) {
        case 'select':
            $content .= '<select  ' . $field_id_name . '>';
            $content .= '<option value="" disabled selected style="display:none;">' . esc_html($placeholder) . '</option>';
            foreach ($field['datas'] as $key => $val) {
                $content .= '<option ' . (!empty($field['value']) && $field['value'] == $key ? 'selected="selected"' : '') . ' value="' . esc_attr($key) . '">' . $val . '</option>';
            }
            $content .= '</select>';
            break;
        case 'radio':
            foreach ($field['datas'] as $key => $val) {
                $label_for = 'input-' . $id . $key;
                $input_radio_label_before = '<label for="' . $label_for . '" id="label-' . $id . $key . '" class="label-checkbox">';
                if ($field['input_inside_label']) {
                    $content .= $input_radio_label_before;
                }
                $content .= $before_checkbox . '<input type="' . $field['type'] . '" id="' . $label_for . '" ' . $field_id_name . ' ' . (!empty($field['value']) && $field['value'] == $key ? 'checked="checked"' : '') . ' value="' . $key . '" />' . $after_checkbox . ' ';
                if (!$field['input_inside_label']) {
                    $content .= $input_radio_label_before;
                }
                $content .= $val . '</label>';
            }
            break;
        case 'file':
            $content .= '<input type="file" accept="' . implode(',', $this->get_accepted_file_types($field)) . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'checkbox':
            $content = '<label id="label-' . $id . '" class="label-checkbox">' . $before_checkbox . '<input type="' . $field['type'] . '" ' . $field_id_name . ' value="1" />' . $after_checkbox . ' ' . $label_content . '</label>';
            break;
        case 'text':
        case 'tel':
        case 'url':
        case 'email':
            $content .= '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'textarea':
            $content .= '<textarea cols="30" rows="10" ' . $field_id_name . '>' . $field['value'] . '</textarea>';
            break;
        }

        $content .= $field['html_after_input'];

        $box_class_name = $this->options['contact__settings']['box_class'];
        $box_class = $box_class_name;
        $box_class .= ' ' . $box_class_name . '--' . $id;
        $box_class .= $field['box_class'];

        return $field['html_before'] . '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . trim($box_class) . '">' . $content . '</' . $this->options['contact__settings']['box_tagname'] . '>' . $field['html_after'];
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

        // Recaptcha
        if ($this->has_recaptcha) {
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array(
                    'secret' => $this->options['contact__settings']['recaptcha_privatekey'],
                    'response' => $_POST["g-recaptcha-response"]
                ),
                'cookies' => array()
            ));
            $body_response = wp_remote_retrieve_body($response);
            $body_response_json = json_decode($body_response);
            if (!is_object($body_response_json) || !isset($body_response_json->success) || !$body_response_json->success) {
                return;
            }
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

                    if ($field['type'] == 'select' || $field['type'] == 'radio') {
                        $contact_fields[$id]['value_select'] = $tmp_value;
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
        if (!in_array($file['type'], $this->get_accepted_file_types($field))) {
            return false;
        }

        return true;
    }

    public function default_validation($tmp_value, $field) {
        return true;
    }

    public function validate_field($tmp_value, $field) {

        /* Custom validation */
        if (!call_user_func_array($field['custom_validation'], array($tmp_value, $field))) {
            return false;
        }

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

    public function get_accepted_file_types($field) {
        $accepted_files = $this->options['contact__settings']['file_types'];
        if (isset($field['file_types']) && is_array($field['file_types'])) {
            $accepted_files = $field['file_types'];
        }
        return $accepted_files;
    }

    public function ajax_action() {
        if (!isset($_POST['form_id']) || $_POST['form_id'] != $this->options['id']) {
            return;
        }
        $this->post_contact();
        $this->page_content(true, $this->options['id']);
        die;
    }

    public function ajax_action_autofill() {
        if (!isset($_POST['form_id']) || $_POST['form_id'] != $this->options['id']) {
            return;
        }
        if (!is_user_logged_in()) {
            echo '{}';
        }
        $response = array();
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        foreach ($this->contact_fields as $id => $field) {
            if (!isset($field['autofill'])) {
                continue;
            }
            if ($field['autofill'] == 'user_email') {
                $response[$id] = $user_info->user_email;
            } elseif ($field['autofill'] == 'woocommerce_orders') {
                $response[$id] = array();
                $customer_orders = array();
                if (function_exists('wc_get_orders')) {
                    /* Add native filters */
                    $orders_account_query = apply_filters('woocommerce_my_account_my_orders_query', array('customer' => $user_id));
                    /* Add custom filters */
                    $orders_account_query = apply_filters('wpucontactforms_autofill_woocommerce_orders__query', $orders_account_query);
                    /* Launch query */
                    $customer_orders = wc_get_orders($orders_account_query);
                }
                if (!empty($customer_orders)) {
                    $response[$id][] = __('Select an order', 'wpucontactforms');
                    foreach ($customer_orders as $order) {
                        $order_id = $order->get_id();
                        $response[$id][$order_id] = sprintf(__('# %s', 'wpucontactforms'), $order_id);
                    }
                } else {
                    if (apply_filters('wpucontactforms_autofill_woocommerce_orders__default_text', true, $id)) {
                        $response[$id] = array(__('No order available', 'wpucontactforms'));
                    }
                }
            } else {
                $response[$id] = get_user_meta($user_id, $field['autofill'], 1);
            }
        }
        echo json_encode($response);
        die;
    }

}

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

function wpucontactform__set_html_field_content($field) {
    $field_content = $field['value'];

    /* Better presentation for text */
    if ($field['type'] == 'textarea') {
        $field_content = nl2br($field_content);
    }

    if ($field['type'] == 'file') {
        if (wp_attachment_is_image($field['value'])) {
            $field_content = wp_get_attachment_image($field['value']);
        } else {
            $field_content = __('See attached file', 'wpucontactforms');
        }
    }

    // Return layout
    return '<p><strong>' . $field['label'] . '</strong>:<br />' . $field_content . '</p>';
}

/* ----------------------------------------------------------
  Init
---------------------------------------------------------- */

$wpucontactforms_forms = array();

add_action('init', 'launch_wpucontactforms_default');
function launch_wpucontactforms_default() {
    new wpucontactforms(apply_filters('wpucontactforms_default_options', array()));
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
    $sendmail_subject = apply_filters('wpucontactforms__sendmail_subject', '[' . get_bloginfo('name') . ']' . __('Message from your contact form', 'wpucontactforms'), $form);

    // Send mail
    $mail_content = $sendmail_intro;
    $attachments_to_destroy = array();
    $more = array(
        'attachments' => array()
    );

    // Target Email
    $target_email = get_option('wpu_opt_email');
    if (!is_email($target_email)) {
        $target_email = get_option('admin_email');
    }
    $target_email = apply_filters('wpucontactforms_email', $target_email, $form->options, $form->contact_fields);

    foreach ($form->contact_fields as $id => $field) {

        if ($field['type'] == 'file') {

            // Store attachment id
            $attachments_to_destroy[] = $form->contact_fields[$id]['value'];

            // Add to mail attachments
            $more['attachments'][] = get_attached_file($form->contact_fields[$id]['value']);
        }

        // Emptying values
        $mail_content .= '<hr />' . wpucontactform__set_html_field_content($field);
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
    add_filter('wpucontactforms__sendmail_delete_attachments', '__return_false');

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
        }

        $post_content .= wpucontactform__set_html_field_content($field) . '<hr />';
        $post_metas[$id] = $field['value'];
    }

    $default_post_title = __('New message', 'wpucontactforms');
    $from_name = '';
    if (isset($form->contact_fields['contact_firstname'])) {
        $from_name = $form->contact_fields['contact_firstname']['value'];
    }
    if (isset($form->contact_fields['contact_name'])) {
        $from_name .= ' ' . $form->contact_fields['contact_name']['value'];
        $from_name = trim($from_name);
    }
    if (!empty($from_name)) {
        $default_post_title = sprintf(__('New message from %s', 'wpucontactforms'), $from_name);
    }

    // Create post
    $post_id = wp_insert_post(array(
        'post_title' => apply_filters('wpucontactforms__createpost_post_title', $default_post_title, $form),
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
