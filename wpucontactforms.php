<?php

/*
Plugin Name: WPU Contact forms
Plugin URI: https://github.com/WordPressUtilities/wpucontactforms
Version: 1.6.0
Description: Contact forms
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpucontactforms {

    private $plugin_version = '1.6.0';
    private $humantest_classname = 'hu-man-te-st';
    private $first_init = true;
    private $has_recaptcha_v2 = false;
    private $has_recaptcha_v3 = false;

    public function __construct($options = array()) {
        global $wpucontactforms_forms;

        $this->first_init = !class_exists('\wpucontactforms\WPUBaseUpdate');

        if (!isset($options['id'])) {
            return;
        }
        if (!is_array($wpucontactforms_forms)) {
            $wpucontactforms_forms = array();
        }
        if (in_array($options['id'], $wpucontactforms_forms)) {
            return;
        }

        if ($this->first_init) {
            load_plugin_textdomain('wpucontactforms', false, dirname(plugin_basename(__FILE__)) . '/lang/');
        }

        $this->set_humantest_classname($options['id']);

        $wpucontactforms_forms[] = $options['id'];

        $this->set_options($options);
        if (isset($this->options['contact__settings']['admin_form']) && $this->options['contact__settings']['admin_form'] && !is_admin()) {
            return;
        }
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

        add_filter('ajax_query_attachments_args', array(&$this,
            'ajax_query_attachments_args'
        ), 10, 1);

        if ($this->first_init) {
            add_action('admin_menu', array(&$this, 'create_admin_form_submenus'));
            add_action('add_meta_boxes', array(&$this, 'register_meta_boxes'));
            include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
            $this->settings_update = new \wpucontactforms\WPUBaseUpdate(
                'WordPressUtilities',
                'wpucontactforms',
                $this->plugin_version);
        }

        $has_recaptcha = $this->options['contact__settings']['recaptcha_enabled'] && $this->options['contact__settings']['recaptcha_sitekey'] && $this->options['contact__settings']['recaptcha_privatekey'];
        $this->has_recaptcha_v2 = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'v2';
        $this->has_recaptcha_v3 = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'v3';
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
            add_action('admin_enqueue_scripts', array(&$this,
                'form_scripts'
            ));
        }
    }

    public function set_humantest_classname($form_id) {

        /* Init classname no bot test */
        $this->humantest_classname = md5($this->humantest_classname . get_bloginfo('name') . $form_id);
        $this->humantest_classname = apply_filters('wpucontactforms_humantest_classname', $this->humantest_classname);
    }

    public function form_scripts() {
        if (!$this->first_init) {
            return;
        }
        wp_enqueue_script('jquery-form');
        wp_enqueue_script('wpucontactforms-front', plugins_url('assets/front.js', __FILE__), array(
            'jquery'
        ), $this->plugin_version, true);
        wp_enqueue_style('wpucontactforms-frontcss', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version, 'all');
        if (is_admin()) {
            wp_enqueue_style('wpucontactforms-admincss', plugins_url('assets/admin.css', __FILE__), array(), $this->plugin_version, 'all');
        }

        // Pass Ajax Url to script.js
        wp_localize_script('wpucontactforms-front', 'wpucontactforms_obj', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'enable_custom_validation' => $this->options['contact__settings']['enable_custom_validation']
        ));
    }

    public function create_admin_form_submenus() {
        global $submenu;
        $forms = get_terms(array(
            'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
            'hide_empty' => false
        ));
        /* Display form submenus only if needed */
        if (!$forms || count($forms) < 2) {
            return;
        }

        $base_link = 'edit.php?post_type=' . wpucontactforms_savepost__get_post_type();
        foreach ($forms as $form) {
            $form_link = admin_url($base_link . '&taxonomy=' . wpucontactforms_savepost__get_taxonomy() . '&term=' . $form->slug);
            $submenu[$base_link][] = array('- ' . $form->name, 'edit_pages', $form_link);
        }
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

        $contact__success = '<p class="contact-success">' . __('Thank you for your message!', 'wpucontactforms') . '</p>';
        if (is_admin()) {
            $contact__success = '<div class="notice notice-success">' . $contact__success . '</div>';
        }

        // Default options
        $default_options = array(
            'id' => 'default',
            'name' => 'Default',
            'contact__display_form_after_success' => apply_filters('wpucontactforms_display_form_after_success', true),
            'contact__success' => apply_filters('wpucontactforms_success', $contact__success),
            'contact__settings' => array()
        );
        $this->options = array_merge($default_options, $options);

        // Settings
        $contact__settings = apply_filters('wpucontactforms_settings', array(
            'ajax_enabled' => true,
            'admin_form' => false,
            'attach_to_post' => get_the_ID(),
            'autocomplete' => '',
            'box_class' => 'box',
            'box_tagname' => 'div',
            'fieldgroup_class' => 'twoboxes',
            'fieldgroup_tagname' => 'div',
            'content_before_wrapper_open' => '',
            'content_after_wrapper_open' => '',
            'content_before_content_form' => '',
            'content_after_content_form' => '',
            'content_before_wrapper_close' => '',
            'content_after_wrapper_close' => '',
            'content_before_recaptcha' => '',
            'content_after_recaptcha' => '',
            'display_form_after_submit' => true,
            'enable_custom_validation' => false,
            'group_class' => 'cssc-form cssc-form--default float-form',
            'group_submit_class' => 'box--submit',
            'group_tagname' => 'div',
            'input_class' => 'input-text',
            'label_text_required' => '<em>*</em>',
            'max_file_size' => wp_max_upload_size(),
            'recaptcha_type' => 'v2',
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
        $this->contact_fields = apply_filters('wpucontactforms_contact_fields_before_display', $this->contact_fields);
        foreach ($this->contact_fields as $field) {
            $content_fields .= $this->field_content($field);
            if (isset($field['autofill'])) {
                $form_autofill = true;
            }
        }

        $is_preview_mode = $this->is_preview_form();

        $form_tag = $is_preview_mode ? 'div' : 'form';

        // Display contact form
        $content_form .= '<' . $form_tag . ' class="wpucontactforms__form" action="" aria-atomic="true" aria-live="assertive" method="post" ';
        $content_form .= ' ' . ($this->has_upload ? 'enctype="multipart/form-data"' : '');
        $content_form .= ' ' . ($this->options['contact__settings']['autocomplete'] ? 'autocomplete="' . esc_attr($this->options['contact__settings']['autocomplete']) . '"' : '');
        $content_form .= ' data-autofill="' . ($form_autofill ? '1' : '0') . '">';
        $content_form .= '<' . $this->options['contact__settings']['group_tagname'] . ' class="' . $this->options['contact__settings']['group_class'] . '">';
        $content_form .= $content_fields;

        /* Quick honeypot */
        if (!$is_preview_mode) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="screen-reader-text">';
            $content_form .= '<label id="label-' . $this->humantest_classname . '" for="input-' . $this->humantest_classname . '">' . __('If you are human, leave this empty', 'wpucontactforms') . '</label>';
            $content_form .= '<input aria-labelledby="label-' . $this->humantest_classname . '" id="input-' . $this->humantest_classname . '" tabindex="-1" name="' . $this->humantest_classname . '" type="text"/>';
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }

        if (!$is_preview_mode && $this->has_recaptcha_v2) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' box-recaptcha">';
            $content_form .= $this->options['contact__settings']['content_before_recaptcha'];
            $content_form .= '<div class="g-recaptcha" data-callback="wpucontactforms_recaptcha_callback" data-expired-callback="wpucontactforms_recaptcha_callback_expired" data-sitekey="' . $this->options['contact__settings']['recaptcha_sitekey'] . '"></div>';
            $content_form .= $this->options['contact__settings']['content_after_recaptcha'];
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }
        if (!$is_preview_mode && $this->has_recaptcha_v3) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' box-recaptcha-v3" data-sitekey="' . $this->options['contact__settings']['recaptcha_sitekey'] . '">';
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_class'] . '">';
        $content_form .= apply_filters('wpucontactforms_fields_submit_inner_before', '');
        $hidden_fields = apply_filters('wpucontactforms_hidden_fields', array(
            'form_id' => $form_id,
            'control_stripslashes' => '&quot;',
            'wpucontactforms_send' => '1',
            'action' => 'wpucontactforms'
        ), $this->options);
        if (!$is_preview_mode) {
            foreach ($hidden_fields as $name => $value) {
                $content_form .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
            }
        }
        if ($this->options['contact__settings']['submit_type'] == 'button') {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_class'] . '" type="submit"><span>' . $this->options['contact__settings']['submit_label'] . '</span></button>';
        } else {
            $content_form .= '<input class="' . $this->options['contact__settings']['submit_class'] . '" type="submit" value="' . esc_attr($this->options['contact__settings']['submit_label']) . '">';
        }
        $content_form .= apply_filters('wpucontactforms_fields_submit_inner_after', '');
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        $content_form .= '</' . $form_tag . '>';
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
        $is_preview_mode = $this->is_preview_form();

        if (!$is_preview_mode && isset($field['type'], $field['html']) && $field['type'] == 'html') {
            return $field['html'];
        }

        $content = '';
        $id = $field['id'];
        if ($is_preview_mode) {
            $id = 'preview_wpucontactforms__' . $id;
        }
        $id_html = $this->options['id'] . '_' . $id;
        $is_multiple = isset($field['multiple']) && $field['multiple'];
        if ($field['type'] == 'checkbox-list') {
            $is_multiple = true;
        }

        $input_multiple = ($field['type'] == 'file' && $is_multiple);

        $field_id_name = '';
        if ($field['type'] != 'radio' && $field['type'] != 'checkbox-list') {
            $field_id_name .= ' id="' . $id_html . '" aria-labelledby="label-' . $id . '"';
        }

        if ($input_multiple || $is_multiple) {
            $field_id_name .= ' name="' . $id . '[]"';
        } else {
            $field_id_name .= ' name="' . $id . '"';
        }

        $field_id_name .= '  aria-required="' . ($field['required'] ? 'true' : 'false') . '" ';

        // Required
        if ($field['required'] && !$is_preview_mode) {
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

        // Disabled
        if (isset($field['disabled']) && $field['disabled']) {
            $field_id_name .= ' disabled="disabled"';
        }

        // Autocomplete
        if (isset($field['autocomplete']) && $field['autocomplete']) {
            $field_id_name .= ' autocomplete="' . esc_attr($field['autocomplete']) . '"';
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

        // Extra attributes
        if (isset($field['attributes_extra']) && !empty($field['attributes_extra'])) {
            $field_id_name .= $field['attributes_extra'];
        }

        // Value
        $field_val = 'value="' . (is_string($field['value']) ? $field['value'] : '') . '"';

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
            $has_optgroup = false;
            foreach ($field['datas'] as $key => $val) {
                /* Optgroup : load value and ignore opt line */
                if (is_array($val) && isset($val['optgroup'])) {
                    $opt_displayed_value = $val['optgroup'];
                    /* Close if an optgroup was opened */
                    if ($has_optgroup) {
                        $content .= '</optgroup>';
                    }
                    $content .= '<optgroup label="' . esc_attr($val['optgroup']) . '">';
                    $has_optgroup = true;
                    continue;
                }
                $content .= '<option ' . (!empty($field['value']) && $field['value'] == $key ? 'selected="selected"' : '') . ' value="' . esc_attr($key) . '">' . $val . '</option>';
            }
            if ($has_optgroup) {
                $content .= '</optgroup>';
            }
            $content .= '</select>';
            break;
        case 'checkbox-list':
        case 'radio':
            $field_type = 'radio';
            $field_type_extra = '';
            if ($field['type'] == 'checkbox-list') {
                $field_type = 'checkbox';
                $field_type_extra = ' data-checkbox-list="1"';
            }
            foreach ($field['datas'] as $key => $val) {
                $label_for = $id_html . '__' . $key;
                $input_radio_label_before = '<label for="' . $label_for . '" id="label-' . $id . $key . '" class="label-checkbox">';
                if ($field['input_inside_label']) {
                    $content .= $input_radio_label_before;
                }
                $content .= $before_checkbox . '<input ' . $field_type_extra . ' type="' . $field_type . '" id="' . $label_for . '" ' . $field_id_name . ' ' . (!empty($field['value']) && $field['value'] == $key ? 'checked="checked"' : '') . ' value="' . $key . '" />' . $after_checkbox;
                if (!$field['input_inside_label']) {
                    $content .= $input_radio_label_before;
                } else {
                    $content .= ' ';
                }
                $content .= $val . '</label>';
            }
            break;
        case 'file':
            $content .= '<input ' . ($input_multiple ? 'multiple' : '') . ' type="file" accept="' . implode(',', $this->get_accepted_file_types($field)) . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'checkbox':
            $content = '';
            $checkbox_content = $before_checkbox . '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . (isset($field['checked']) && $field['checked'] ? 'checked="checked"' : '') . ' value="1" />' . $after_checkbox;
            if (!$field['input_inside_label']) {
                $content .= $checkbox_content;
            }
            $content .= '<label id="label-' . $id . '" class="label-checkbox" for="' . $id_html . '">';
            if ($field['input_inside_label']) {
                $content .= $checkbox_content . ' ';
            }
            $content .= $label_content . '</label>';
            break;
        case 'text':
        case 'tel':
        case 'url':
        case 'number':
        case 'email':
            $content .= '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'textarea':
            $content .= '<textarea cols="30" rows="10" ' . $field_id_name . '>' . $field['value'] . '</textarea>';
            break;
        }

        $content .= $field['html_after_input'];

        $box_class_name = esc_attr($this->options['contact__settings']['box_class']);
        $box_class = $box_class_name;
        $box_class .= ' ' . $box_class_name . '--' . $id;
        if ($field['box_class']) {
            $box_class .= ' ' . esc_attr($field['box_class']);
        }

        $conditions = '';
        if (isset($field['conditions'])) {
            $conditions = '';
            if (isset($field['conditions']['display'])) {
                $conditions .= ' style="display:none"';
            }
            $conditions .= ' data-wpucf-conditions="' . esc_attr(json_encode($field['conditions'])) . '" ';
        }

        $fieldgroup_class = $this->options['contact__settings']['fieldgroup_class'];
        if (isset($field['fieldgroup_class'])) {
            $fieldgroup_class = $field['fieldgroup_class'];
        }

        if (isset($field['fieldgroup_start']) && $field['fieldgroup_start']) {
            $field['html_before'] .= '<' . $this->options['contact__settings']['fieldgroup_tagname'] . ' class="' . esc_attr($fieldgroup_class) . '">';
        }
        if (isset($field['fieldgroup_html_before'])) {
            $field['html_before'] .= $field['fieldgroup_html_before'];
        }

        if (isset($field['fieldgroup_html_after'])) {
            $field['html_after'] .= $field['fieldgroup_html_after'];
        }
        if (isset($field['fieldgroup_end']) && $field['fieldgroup_end']) {
            $field['html_after'] .= '</' . $this->options['contact__settings']['fieldgroup_tagname'] . '>';
        }

        if (isset($this->options['contact__settings']['enable_custom_validation']) && $this->options['contact__settings']['enable_custom_validation'] && !$is_preview_mode) {

            $error_invalid = apply_filters('wpucontactforms__error_invalid_txt', __('This field is invalid', 'wpucontactforms'));
            if (isset($field['error_invalid'])) {
                $error_invalid = $field['error_invalid'];
            }
            $error_choose = apply_filters('wpucontactforms__error_choose_txt', __('A value should be selected', 'wpucontactforms'));
            if (isset($field['error_choose'])) {
                $error_choose = $field['error_choose'];
            }
            $error_empty = apply_filters('wpucontactforms__error_empty_txt', __('This field should not be empty', 'wpucontactforms'));
            if (isset($field['error_empty'])) {
                $error_empty = $field['error_empty'];
            }

            $content .= '<div' .
            ' data-error-invalid="' . esc_attr($error_invalid) . '"' .
            ' data-error-choose="' . esc_attr($error_choose) . '"' .
            ' data-error-empty="' . esc_attr($error_empty) . '"' .
                ' class="error" aria-live="polite"></div>';
        }

        if (isset($field['help']) && $field['help']) {
            $content .= '<small class="help">' . $field['help'] . '</small>';
        }

        return $field['html_before'] .
        '<' . $this->options['contact__settings']['box_tagname'] . $conditions . '' .
        ' data-boxtype="' . esc_attr($field['type']) . '"' .
        ' data-required="' . ($field['required'] ? 'true' : 'false') . '"' .
        ' data-boxid="' . esc_attr($field['id']) . '"' .
        ' class="' . trim($box_class) . '">' .
        $content .
        '</' . $this->options['contact__settings']['box_tagname'] . '>' .
            $field['html_after'];
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
            $_POST = stripslashes_deep($_POST);
        }

        // Checking bots
        if (!isset($_POST[$this->humantest_classname]) || !empty($_POST[$this->humantest_classname])) {
            return;
        }

        // Recaptcha
        if (($this->has_recaptcha_v2 || $this->has_recaptcha_v3) && !isset($_POST["g-recaptcha-response"])) {
            $this->msg_errors[] = __('The captcha is invalid', 'wpucontactforms');
        }
        if (($this->has_recaptcha_v2 || $this->has_recaptcha_v3) && isset($_POST["g-recaptcha-response"])) {
            $recaptcha_args = apply_filters('wpucontactforms__recaptcha_args', array(
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
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', $recaptcha_args);
            $body_response = wp_remote_retrieve_body($response);
            $body_response_json = json_decode($body_response);
            if (!is_object($body_response_json) || !isset($body_response_json->success) || !$body_response_json->success) {
                $this->msg_errors[] = __('The captcha is invalid', 'wpucontactforms');
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

        // Add custom error messages.
        $this->msg_errors = apply_filters('wpucontactforms_submit_contactform_msg_errors', $this->msg_errors, $this);

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
            $is_multiple = isset($field['multiple']) && $field['multiple'];
            if ($field['type'] == 'checkbox-list') {
                $is_multiple = true;
            }

            $tmp_value = '';
            if (isset($post[$id])) {
                if ($is_multiple) {
                    $tmp_value = array_map('strip_tags', $post[$id]);
                    $tmp_value = array_map('htmlentities', $tmp_value);
                    $tmp_value = array_map('trim', $tmp_value);
                } else {
                    $tmp_value = trim(htmlentities(strip_tags($post[$id])));
                }
            }

            if ($field['type'] == 'file' && isset($_FILES[$id])) {
                $tmp_value = $this->convert_files_global_multiple($_FILES[$id]);
            }

            if ($field['type'] == 'checkbox' && empty($tmp_value)) {
                $tmp_value = '0';
            }

            if ($tmp_value != '' && $tmp_value != array('') && !empty($tmp_value)) {
                if ($field['type'] == 'file') {
                    $field_ok = true;
                    foreach ($tmp_value as $tmp_file) {
                        $tmp_ok = $this->validate_field_file($tmp_file, $field);
                        if (!$tmp_ok) {
                            $field_ok = false;
                        }
                    }
                } else {
                    $field_ok = $this->validate_field($tmp_value, $field);
                }

                if (!$field_ok) {
                    $this->msg_errors[] = sprintf(__('The field "%s" is not correct', 'wpucontactforms'), $field['label']);
                } else {

                    if ($field['type'] == 'select' || $field['type'] == 'radio') {
                        $contact_fields[$id]['value_select'] = $tmp_value;
                        if (isset($field['datas'][$tmp_value])) {
                            $tmp_value = $field['datas'][$tmp_value];
                        }
                    }

                    if ($field['type'] == 'checkbox') {
                        $tmp_value = ($tmp_value == '1') ? __('Yes', 'wpucontactforms') : __('No', 'wpucontactforms');
                    }

                    if ($field['type'] == 'file') {
                        $tmp_files = $tmp_value;
                        $tmp_value = array();
                        foreach ($tmp_files as $tmp_file) {
                            $tmp_value[] = $this->upload_file_return_att_id($tmp_file, $field);
                        }
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

    public function setup_upload_protection($dirs = array()) {
        if (apply_filters('wpucontactforms__upload_protection_disabled', false)) {
            return $dirs;
        }

        $default_folder_name = 'wpucontactforms';
        $folder_name = apply_filters('wpucontactforms__uploads_dir', $default_folder_name);

        $plugin_dir = dirname(__FILE__) . '/tools';

        $root_dir = $dirs['basedir'] . '/' . $folder_name;

        $dirs['subdir'] = '/' . $folder_name . $dirs['subdir'];
        $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];

        /* Create dir if needed */
        if (!is_dir($dirs['path'])) {
            mkdir($dirs['path'], 0755, true);
        }

        /* Ensure htaccess is up-to-date */
        if (file_exists($root_dir . '/.htaccess')) {
            unlink($root_dir . '/.htaccess');
        }
        copy($plugin_dir . '/htaccess.txt', $root_dir . '/.htaccess');
        $file_contents = str_replace($default_folder_name, $folder_name, file_get_contents($root_dir . '/.htaccess'));
        file_put_contents($root_dir . '/.htaccess', $file_contents);

        /* Ensure index.php is up-to-date */
        if (file_exists($root_dir . '/index.php')) {
            unlink($root_dir . '/index.php');
        }
        copy($plugin_dir . '/file.php', $root_dir . '/index.php');

        return $dirs;
    }

    public function upload_file_return_att_id($file, $field) {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        add_filter('upload_dir', array(&$this, 'setup_upload_protection'));
        $attachment_id = media_handle_sideload($file, $this->options['contact__settings']['attach_to_post']);
        remove_filter('upload_dir', array(&$this, 'setup_upload_protection'));
        if (!is_numeric($attachment_id)) {
            return false;
        }

        //add_post_meta($attachment_id, '_wpucontactforms_att', '1');

        return $attachment_id;
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
        if (isset($field['custom_validation']) && !call_user_func_array($field['custom_validation'], array($tmp_value, $field))) {
            return false;
        }

        $zero_one = array('0', '1');
        switch ($field['validation']) {
        case 'radio':
        case 'select':
            return array_key_exists($tmp_value, $field['datas']);
            break;
        case 'checkbox':
            return in_array($tmp_value, $zero_one);
            break;
        case 'checkbox-list':
            if (!is_array($tmp_value)) {
                return false;
            }
            foreach ($tmp_value as $tmp_value_item) {
                if (!array_key_exists($tmp_value_item, $field['datas'])) {
                    return false;
                }
            }
            return true;
            break;
        case 'number':
            return is_numeric($tmp_value);
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
        $accepted_files = $this->extract_file_types($this->options['contact__settings']['file_types']);
        if (isset($field['file_types']) && is_array($field['file_types'])) {
            $accepted_files = $this->extract_file_types($field['file_types']);
        }
        return $accepted_files;
    }

    public function extract_file_types($types = array()) {
        $accepted_files = array();
        foreach ($types as $accepted_file) {
            if (preg_match('/^([a-z]+)$/', $accepted_file)) {
                $accepted_file = '.' . $accepted_file;
            }
            $accepted_files[] = $accepted_file;
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
                if (is_object($user_info)) {
                    $response[$id] = $user_info->user_email;
                }
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

    /** Ensure $_FILES global is always a correct array */
    public function convert_files_global_multiple($file = array()) {
        if (!isset($file['error'])) {
            return array();
        }
        if (!is_array($file['error'])) {
            return array($file);
        }
        $final_file = array();
        foreach ($file['error'] as $key => $var) {
            $final_file[] = array(
                'name' => $file['name'][$key],
                'type' => $file['type'][$key],
                'tmp_name' => $file['tmp_name'][$key],
                'error' => $file['error'][$key],
                'size' => $file['size'][$key]
            );
        }
        return $final_file;
    }

    public function register_meta_boxes() {
        add_meta_box('wpucontactforms-meta-boxes', __('Attachments', 'wpucontactforms'), array(&$this, 'callback_meta_box'), wpucontactforms_savepost__get_post_type());
    }

    /**
     * Meta box display callback.
     *
     * @param WP_Post $post Current post object.
     */
    public function callback_meta_box($post) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_parent' => $post->ID
        ));

        if ($attachments) {
            echo '<ul>';
            foreach ($attachments as $attachment) {
                echo '<li>- <a download href="' . wp_get_attachment_url($attachment->ID) . '">' . $attachment->post_title . ' (' . $attachment->post_mime_type . ')</a></li>';
            }
            echo '</ul>';

        } else {
            echo '<p>' . __('No attachments available', 'wpucontactforms') . '</p>';
        }
    }

    /* Hide attachments from library */
    public function ajax_query_attachments_args($query = array()) {
        if (apply_filters('wpucontactforms__hide_from_library', true)) {
            if (!isset($query['meta_query'])) {
                $query['meta_query'] = array();
            }
            $query['meta_query'][] = array(
                'key' => '_wpucontactforms_att',
                'compare' => 'NOT EXISTS'
            );
        }
        return $query;
    }

    /* Display a form without interfering with the admin */
    public function is_preview_form() {
        global $is_preview;
        $is_preview_mode = false;
        if (isset($is_preview) && $is_preview) {
            $is_preview_mode = true;
        }
        return $is_preview_mode;
    }

}

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

function wpucontactform__set_html_field_content($field, $wrap_html = true) {
    $field_content = $field['value'];

    /* Better presentation for text */
    if ($field['type'] == 'textarea') {
        $field_content = nl2br($field_content);
    }

    if ($field['type'] == 'checkbox-list' && is_array($field['value'])) {
        $field_parts = array();

        foreach ($field['value'] as $val_item) {
            if (isset($field['datas'][$val_item])) {
                $field_parts[] = $field['datas'][$val_item];
            }
        }
        $field_content = implode(', ', $field_parts);

    }

    $wpucontactforms__upload_protection_disabled = apply_filters('wpucontactforms__upload_protection_disabled', false);

    if ($field['type'] == 'file' && is_array($field['value'])) {
        $field_content_parts = array();
        foreach ($field['value'] as $field_value) {
            if (wp_attachment_is_image($field_value) && $wpucontactforms__upload_protection_disabled) {
                $field_content_parts[] = wp_get_attachment_image($field_value);
            } else {
                $field_content_parts[] = __('See attached file(s)', 'wpucontactforms');
                $field_content_parts[] = '<a href="' . wp_get_attachment_url($field_value) . '">' . __('Or click here', 'wpucontactforms') . '</a>';
            }
        }
        $field_content = implode('<br />', $field_content_parts);
    }

    // Return layout
    return $wrap_html ? '<p><strong>' . $field['label'] . '</strong>:<br />' . $field_content . '</p>' : $field_content;
}

/* ----------------------------------------------------------
  Init
---------------------------------------------------------- */

$wpucontactforms_forms = array();

add_action('init', 'launch_wpucontactforms_default');
function launch_wpucontactforms_default() {
    $options = apply_filters('wpucontactforms_default_options', array());
    if (!empty($options)) {
        new wpucontactforms($options);
    }
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

function wpucontactforms_submit_sendmail($mail_content = '', $more = array(), $form = false) {
    $form_options = array();
    $form_contact_fields = array();
    if (is_object($form)) {
        $form_options = $form->options;
        $form_contact_fields = $form->contact_fields;
    }

    /* Subject */
    $from_name = '';
    if (isset($form_contact_fields['contact_firstname'])) {
        $from_name = $form_contact_fields['contact_firstname']['value'];
    }
    if (isset($form_contact_fields['contact_name'])) {
        $from_name .= ' ' . $form_contact_fields['contact_name']['value'];
        $from_name = trim($from_name);
    }
    $sendmail_subject = __('New message', 'wpucontactforms');
    if ($from_name) {
        $sendmail_subject = sprintf(__('New message from %s', 'wpucontactforms'), $from_name);
    }

    if (isset($form->options['name'])) {
        $sendmail_subject = '[' . $form->options['name'] . '] ' . $sendmail_subject;
    }
    if (!function_exists('wputh_sendmail')) {
        $sendmail_subject = '[' . get_bloginfo('name') . ']' . $sendmail_subject;
    }

    $sendmail_subject = strip_tags(html_entity_decode($sendmail_subject));
    $sendmail_subject = apply_filters('wpucontactforms__sendmail_subject', $sendmail_subject, $form);

    /* Get email */
    $target_email = get_option('wpu_opt_email');
    if (!is_email($target_email)) {
        $target_email = get_option('admin_email');
    }
    $target_email = apply_filters('wpucontactforms_email', $target_email, $form_options, $form_contact_fields);

    /* More */
    if (!is_array($more)) {
        $more = array();
    }
    if (!isset($more['attachments'])) {
        $more['attachments'] = array();
    }

    /* Send content */
    if (function_exists('wputh_sendmail')) {
        wputh_sendmail($target_email, $sendmail_subject, $mail_content, $more);
    } else {
        wp_mail($target_email, $sendmail_subject, $mail_content, '', $more['attachments']);
    }
}

/* Send mail
-------------------------- */

add_action('wpucontactforms_submit_contactform', 'wpucontactforms_submit_contactform__sendmail', 10, 1);
function wpucontactforms_submit_contactform__sendmail($form) {
    if (apply_filters('wpucontactforms_submit_contactform__sendmail__disable', false, $form)) {
        return false;
    }

    // Send mail
    $mail_content = apply_filters('wpucontactforms__sendmail_intro', '<p>' . __('Message from your contact form', 'wpucontactforms') . '</p>', $form);
    $attachments_to_destroy = array();
    $more = array(
        'attachments' => array()
    );

    // Target Email
    foreach ($form->contact_fields as $id => $field) {
        if ($field['type'] == 'html') {
            continue;
        }

        if ($field['type'] == 'file' && is_array($field['value'])) {

            foreach ($field['value'] as $file_id) {

                // Store attachment id
                $attachments_to_destroy[] = $file_id;

                // Add to mail attachments
                $more['attachments'][] = get_attached_file($file_id);
            }
        }

        // Emptying values
        $mail_content .= '<hr />' . wpucontactform__set_html_field_content($field);
    }

    $mail_content = apply_filters('wpucontactforms_submit_contactform__sendmail__mail_content', $mail_content, $form);

    wpucontactforms_submit_sendmail($mail_content, $more, $form);

    // Delete temporary attachments
    if (apply_filters('wpucontactforms__sendmail_delete_attachments', true)) {
        foreach ($attachments_to_destroy as $att_id) {
            wp_delete_attachment($att_id);
        }
    }
}

/* Save post
-------------------------- */

function wpucontactforms_savepost__pll_get_post_types($post_types, $hide) {
    $post_types['contact_message'] = 'contact_message';
    return $post_types;
}

function wpucontactforms_savepost__get_post_type() {
    return apply_filters('wpucontactforms_savepost__get_post_type', 'contact_message');
}

function wpucontactforms_savepost__get_taxonomy() {
    return apply_filters('wpucontactforms_savepost__get_taxonomy', 'contact_form');
}

add_action('init', 'wpucontactforms_submit_contactform__savepost__objects');
function wpucontactforms_submit_contactform__savepost__objects() {
    add_action('wpucontactforms_submit_contactform', 'wpucontactforms_submit_contactform__savepost', 10, 1);
    add_filter('wpucontactforms__sendmail_delete_attachments', '__return_false');

    /* Resend a mail */
    add_action('post_submitbox_misc_actions', 'wpucontactforms_submit_contactform__resendmail');
    add_action('save_post', 'wpucontactforms_submit_contactform__resendmail__action');

    // Create a new taxonomy
    register_taxonomy(
        wpucontactforms_savepost__get_taxonomy(),
        array(wpucontactforms_savepost__get_post_type()),
        array(
            'label' => __('Forms', 'wpucontactforms'),
            'show_admin_column' => true,
            'public' => false,
            'rewrite' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'show_ui' => true
        )
    );

    // Create a new post type
    register_post_type(wpucontactforms_savepost__get_post_type(),
        array(
            'labels' => array(
                'name' => __('Messages', 'wpucontactforms'),
                'singular_name' => __('Message', 'wpucontactforms')
            ),
            'exclude_from_search' => true,
            'has_archive' => false,
            'menu_position' => 50,
            'menu_icon' => 'dashicons-email-alt',
            'public' => false,
            'rewrite' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'show_ui' => true,
            'taxonomies' => array(wpucontactforms_savepost__get_taxonomy())
        )
    );

    // Allow sorting by lang
    add_filter('pll_get_post_types', 'wpucontactforms_savepost__pll_get_post_types', 10, 2);

    add_filter('manage_taxonomies_for_activity_columns', 'activity_type_columns');

}

function wpucontactforms_submit_contactform__savepost($form) {

    if (apply_filters('wpucontactforms_submit_contactform__savepost__disable', false, $form)) {
        return false;
    }

    $taxonomy = apply_filters('wpucontactforms__createpost_taxonomy', wpucontactforms_savepost__get_taxonomy(), $form);
    $post_content = '';
    $post_metas = array();
    $attachments = array();

    foreach ($form->contact_fields as $id => $field) {
        if ($field['type'] == 'html') {
            continue;
        }

        if ($field['type'] == 'file' && is_array($field['value'])) {
            foreach ($field['value'] as $file_value) {
                $attachments[] = $file_value;
            }
        }

        $post_content .= wpucontactform__set_html_field_content($field) . '<hr />';
        $post_metas[$id] = $field['value'];
        if (apply_filters('wpucontactforms__save_post_meta_html', false) && !in_array($field['type'], array('file'))) {
            $post_metas[$id] = wpucontactform__set_html_field_content($field, false);
        }
    }

    $default_post_title = __('New message', 'wpucontactforms');
    $from_name = '';
    if (isset($form->contact_fields['contact_firstname'])) {
        $from_name = $form->contact_fields['contact_firstname']['value'];
    }
    if (isset($form->contact_fields['contact_name'])) {
        $from_name .= ' ' . $form->contact_fields['contact_name']['value'];
    }
    $from_name = trim($from_name);
    $from_name = strip_tags(html_entity_decode($from_name));
    if (!empty($from_name)) {
        $default_post_title = sprintf(__('New message from %s', 'wpucontactforms'), $from_name);
    }

    $post_content = apply_filters('wpucontactforms_submit_contactform__savepost__post_content', $post_content, $form);

    // Create post
    $post_id = wp_insert_post(array(
        'post_title' => apply_filters('wpucontactforms__createpost_post_title', $default_post_title, $form),
        'post_type' => apply_filters('wpucontactforms__createpost_post_type', wpucontactforms_savepost__get_post_type(), $form),
        'post_content' => apply_filters('wpucontactforms__createpost_post_content', $post_content, $form),
        'post_author' => apply_filters('wpucontactforms__createpost_postauthor', 1, $form),
        'post_status' => 'publish'
    ));

    global $wpucontactforms_last_post_id;
    $wpucontactforms_last_post_id = $post_id;

    // Set language
    if (function_exists('pll_current_language')) {
        pll_set_post_language($post_id, pll_current_language());
    }

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

/* Re-send mail
-------------------------- */

function wpucontactforms_submit_contactform__resendmail() {
    if (get_post_type() != wpucontactforms_savepost__get_post_type()) {
        return;
    }
    $html = '<div class="misc-pub-section">';
    $html .= '<input type="submit" value="' . __('Re-send this email', 'wpucontactforms') . '" class="button-secondary" id="custom" name="wpucontactforms__resendmail" />';
    $html .= '</div>';
    echo $html;
}

function wpucontactforms_submit_contactform__resendmail__action($post_id) {

    if (!isset($_POST['wpucontactforms__resendmail'])) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (get_post_type($post_id) != wpucontactforms_savepost__get_post_type()) {
        return;
    }

    $wpq_post = new WP_Query(array(
        'posts_per_page' => -1,
        'post_type' => wpucontactforms_savepost__get_post_type(),
        'p' => $post_id
    ));

    if ($wpq_post->have_posts()) {
        $wpq_post->the_post();
        ob_start();
        the_content();
        $out = ob_get_clean();
        wpucontactforms_submit_sendmail($out);

    }
    wp_reset_postdata();

}

/* Get data from a textarea option
-------------------------- */

function wpucontactforms_get_select_data_from_opt($opt_value = '') {
    $opt_value = explode("\n", $opt_value);
    $opt_value = array_map('trim', $opt_value);
    $data = array();
    foreach ($opt_value as $i => $opt_data) {
        if ($opt_data) {
            $data['k' . $i] = $opt_data;
        }
    }
    return $data;
}

/* WP-CLI action
-------------------------- */

require_once dirname(__FILE__) . '/inc/wp-cli-migrate.php';
