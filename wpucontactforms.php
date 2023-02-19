<?php

/*
Plugin Name: WPU Contact forms
Plugin URI: https://github.com/WordPressUtilities/wpucontactforms
Update URI: https://github.com/WordPressUtilities/wpucontactforms
Version: 3.2.5
Description: Contact forms
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class wpucontactforms {

    private $plugin_version = '3.2.5';
    private $humantest_classname = 'hu-man-te-st';
    private $first_init = true;
    public $has_recaptcha_v2 = false;
    public $has_recaptcha_v3 = false;
    public $has_recaptcha_hcaptcha = false;
    public $has_recaptcha_turnstile = false;
    public $user_options = false;
    public $settings_details;
    public $settings;
    public $default_field;
    public $options;
    public $content_contact;
    public $has_upload;
    public $is_successful;
    public $adminpages;
    public $plugin_description;
    public $settings_update;
    public $contact_fields = array();
    public $contact_steps = array();
    public $phone_pattern = '^(?:0|\(?\+33\)?\s?|0033\s?)[1-79](?:[\.\-\s]?\d\d){4}$';
    public $disposable_domains = array(
        'cool.fr.nf',
        'courriel.fr.nf',
        'example.com',
        'getnada.com',
        'hide.biz.st',
        'jetable.fr.nf',
        'moncourrier.fr.nf',
        'monemail.fr.nf',
        'monmail.fr.nf',
        'mymail.infos.st',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net'
    );

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

            $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
            if (!load_plugin_textdomain('wpucontactforms', false, $lang_dir)) {
                load_muplugin_textdomain('wpucontactforms', $lang_dir);
            }
            $this->plugin_description = __('Contact forms', 'wpucontactforms');
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
        ), 10, 3);
        add_action('wp_ajax_wpucontactforms_autofill', array(&$this,
            'ajax_action_autofill'
        ));
        add_action('wp_ajax_nopriv_wpucontactforms_autofill', array(&$this,
            'ajax_action_autofill'
        ));

        add_filter('ajax_query_attachments_args', array(&$this,
            'ajax_query_attachments_args'
        ), 10, 1);

        $this->settings_details = array(
            # Admin page
            'create_page' => true,
            'plugin_basename' => plugin_basename(__FILE__),
            # Default
            'plugin_name' => 'Contact Forms',
            'plugin_id' => 'wpucontactforms',
            'option_id' => 'wpucontactforms_options',
            'sections' => array(
                'content' => array(
                    'name' => __('Content Settings', 'wpucontactforms')
                ),
                'recaptcha' => array(
                    'name' => __('Captcha', 'wpucontactforms')
                )
            )
        );
        $this->settings = array(
            'excluded_words' => array(
                'label' => __('Excluded Words', 'wpucontactforms'),
                'help' => __('One word or expression per line.', 'wpucontactforms'),
                'type' => 'textarea'
            ),
            'recaptcha_enabled' => array(
                'label' => __('Enable', 'wpucontactforms'),
                'label_check' => __('Recaptcha will be enabled on all forms', 'wpucontactforms'),
                'type' => 'checkbox',
                'section' => 'recaptcha'
            ),
            'recaptcha_type' => array(
                'label' => __('Type', 'wpucontactforms'),
                'type' => 'select',
                'section' => 'recaptcha',
                'datas' => array(
                    'v2' => 'Google Recaptcha - v2',
                    'v3' => 'Google Recaptcha - v3',
                    'turnstile' => 'Cloudflare - Turnstile',
                    'hcaptcha' => 'hCaptcha'
                )
            ),
            'recaptcha_sitekey' => array(
                'label' => __('Site key', 'wpucontactforms'),
                'section' => 'recaptcha'
            ),
            'recaptcha_privatekey' => array(
                'label' => __('Private key', 'wpucontactforms'),
                'section' => 'recaptcha'
            )
        );

        $admin_pages = array(
            'export' => array(
                'section' => 'edit.php?post_type=' . wpucontactforms_savepost__get_post_type(),
                'menu_name' => __('Export', 'wpucontactforms'),
                'name' => __('Export', 'wpucontactforms'),
                'settings_link' => false,
                'function_content' => array(&$this,
                    'page_content__export'
                ),
                'function_action' => array(&$this,
                    'page_action__export'
                )
            )
        );

        $pages_options = array(
            'id' => 'wpucontactforms',
            'level' => 'edit_pages',
            'basename' => plugin_basename(__FILE__)
        );

        if ($this->first_init) {
            /* Add submenus */
            add_action('admin_menu', array(&$this, 'create_admin_form_submenus'));

            /* Add meta boxes */
            add_action('add_meta_boxes', array(&$this, 'register_meta_boxes'));

            /* Update */
            include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
            $this->settings_update = new \wpucontactforms\WPUBaseUpdate(
                'WordPressUtilities',
                'wpucontactforms',
                $this->plugin_version);

            if (is_admin()) {
                include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
                new \wpucontactforms\WPUBaseSettings($this->settings_details, $this->settings);
            }

            // Init admin page
            include dirname(__FILE__) . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
            $this->adminpages = new \wpucontactforms\WPUBaseAdminPage();
            $this->adminpages->init($pages_options, $admin_pages);
        }

        $this->set_user_options();

        $has_recaptcha = $this->options['contact__settings']['recaptcha_enabled'] && $this->options['contact__settings']['recaptcha_sitekey'] && $this->options['contact__settings']['recaptcha_privatekey'];
        $this->has_recaptcha_v2 = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'v2';
        $this->has_recaptcha_v3 = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'v3';
        $this->has_recaptcha_turnstile = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'turnstile';
        $this->has_recaptcha_hcaptcha = $has_recaptcha && $this->options['contact__settings']['recaptcha_type'] == 'hcaptcha';
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

    public function set_user_options() {
        $this->user_options = get_option('wpucontactforms_options');
        if (!is_array($this->user_options)) {
            $this->user_options = array();
        }

        /* Excluded Words */
        if (!isset($this->user_options['excluded_words'])) {
            $this->user_options['excluded_words'] = array();
        } else {
            $this->user_options['excluded_words'] = explode("\n", $this->user_options['excluded_words']);
            $this->user_options['excluded_words'] = array_map('trim', $this->user_options['excluded_words']);
        }

        /* Recaptcha */
        if (isset($this->user_options['recaptcha_sitekey']) && $this->user_options['recaptcha_sitekey']) {
            $this->options['contact__settings']['recaptcha_sitekey'] = $this->user_options['recaptcha_sitekey'];
        }
        if (isset($this->user_options['recaptcha_privatekey']) && $this->user_options['recaptcha_privatekey']) {
            $this->options['contact__settings']['recaptcha_privatekey'] = $this->user_options['recaptcha_privatekey'];
        }
        if (isset($this->user_options['recaptcha_type']) && $this->user_options['recaptcha_type']) {
            $this->options['contact__settings']['recaptcha_type'] = $this->user_options['recaptcha_type'];
        }
        if (isset($this->user_options['recaptcha_enabled']) && $this->user_options['recaptcha_enabled']) {
            $this->options['contact__settings']['recaptcha_enabled'] = true;
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

        if (is_array($this->contact_fields)) {
            foreach ($this->contact_fields as $field) {
                if (isset($field['type']) && $field['type'] == 'datepicker') {
                    wp_enqueue_script('jquery-ui-datepicker');
                }
            }
        }

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
            'disposable_domains' => base64_encode(json_encode($this->disposable_domains)),
            'enable_custom_validation' => $this->options['contact__settings']['enable_custom_validation']
        ));
    }

    public function create_admin_form_submenus() {
        global $submenu;
        $forms = get_terms(array(
            'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
            'hide_empty' => true
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

        $this->disposable_domains = apply_filters('wpucontactforms_disposable_domains', $this->disposable_domains);
        $this->is_successful = false;
        $this->has_upload = false;
        $this->content_contact = '';
        $this->default_field = array(
            'box_class' => '',
            'classname' => '',
            'html_box_start' => '',
            'html_box_end' => '',
            'html_after' => '',
            'html_after_input' => '',
            'html_before' => '',
            'html_before_input' => '',
            'input_inside_label' => true,
            'placeholder' => '',
            'preload_value' => 0,
            'min' => -99999,
            'max' => 99999,
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
            'fieldset_tagname' => 'fieldset',
            'fieldset_classname' => 'wpucontactforms-fieldset',
            'form_classname' => 'wpucontactforms__form',
            'content_before_wrapper_open' => '',
            'content_after_wrapper_open' => '',
            'content_before_content_form' => '',
            'content_after_content_form' => '',
            'content_before_wrapper_close' => '',
            'content_after_wrapper_close' => '',
            'content_before_recaptcha' => '',
            'content_after_recaptcha' => '',
            'disallow_temp_email' => true,
            'display_form_after_submit' => true,
            'extra_disposable_domains' => array(),
            'enable_custom_validation' => false,
            'group_class' => 'cssc-form cssc-form--default float-form',
            'group_submit_class' => 'box--submit',
            'group_submit_intermediate_class' => 'box--submit box--submit-intermediate',
            'group_tagname' => 'div',
            'input_class' => 'input-text',
            'label_checkbox_inner__classname' => 'label_checkbox',
            'label_radio_inner__classname' => 'label_radio',
            'label_text_required' => '<em>*</em>',
            'max_file_size' => wp_max_upload_size(),
            'recaptcha_type' => 'v2',
            'recaptcha_enabled' => false,
            'recaptcha_sitekey' => false,
            'recaptcha_privatekey' => false,
            'submit_class' => 'cssc-button cssc-button--default',
            'submit_label' => __('Submit', 'wpucontactforms'),
            'submit_intermediate_prev_class' => '',
            'submit_intermediate_class' => '',
            'submit_intermediate_prev_label' => __('Previous', 'wpucontactforms'),
            'submit_intermediate_label' => __('Continue', 'wpucontactforms'),
            'submit_type' => 'button',
            'contact_steps' => array(),
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

        // Testing missing settings
        foreach ($this->options['contact__settings'] as $id => $value) {
            if (($id == 'submit_intermediate_prev_class' || $id == 'submit_intermediate_class') && !$value) {
                $this->options['contact__settings'][$id] = $this->options['contact__settings']['submit_class'];
            }
        }

        $this->contact_steps = apply_filters('wpucontactforms_steps', $this->options['contact__settings']['contact_steps'], $this->options);
        $this->contact_fields = apply_filters('wpucontactforms_fields', $this->options['contact__settings']['contact_fields'], $this->options);

        // Testing missing field settings
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
                if (strpos($field['datas'], "\n") === false && strpos($field['datas'], " ") === false) {
                    $field['datas'] = get_option($field['datas']);
                }
                $values = explode("\n", $field['datas']);
                $values = array_map('trim', $values);
                $this->contact_fields[$id]['datas'] = array();
                foreach ($values as $val) {
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

    public function page_content($hide_wrapper = false, $form_id = false, $args = array()) {

        if (!$form_id || $form_id != $this->options['id']) {
            return '';
        }

        if (!is_array($args)) {
            $args = array();
        }

        $form_autofill = false;
        $content_form = '';
        $content_fields = array();
        $this->options = apply_filters('wpucontactforms_options_before_display', $this->options, $args);
        $filtered_contact_fields = apply_filters('wpucontactforms_contact_fields_before_display', $this->contact_fields, $this->options, $args);
        foreach ($filtered_contact_fields as $field) {
            if (!isset($field['fieldset'])) {
                $field['fieldset'] = 'default';
            }
            if (!isset($content_fields[$field['fieldset']])) {
                $content_fields[$field['fieldset']] = '';
            }
            if (!isset($this->contact_steps[$field['fieldset']])) {
                $this->contact_steps[$field['fieldset']] = array();
            }
            $content_fields[$field['fieldset']] .= $this->field_content($field);
            if (isset($field['autofill'])) {
                $form_autofill = true;
            }
        }
        foreach ($this->contact_steps as $step_id => $step) {
            if (!isset($step['title'])) {
                $this->contact_steps[$step_id]['title'] = '';
            }
        }

        $fieldset_tagname = $this->options['contact__settings']['fieldset_tagname'];
        $fieldset_classname = $this->options['contact__settings']['fieldset_classname'];
        $form_classname = $this->options['contact__settings']['form_classname'];

        $is_preview_mode = $this->is_preview_form();

        $form_tag = $is_preview_mode ? 'div' : 'form';

        /* Open form */
        $content_form .= '<' . $form_tag . ' class="' . $form_classname . '" action="" aria-atomic="true" aria-live="assertive" method="post" ';
        $content_form .= ' ' . ($this->has_upload ? 'enctype="multipart/form-data" data-max-file-size="' . $this->options['contact__settings']['max_file_size'] . '"' : '');
        $content_form .= ' ' . ($this->options['contact__settings']['autocomplete'] ? 'autocomplete="' . esc_attr($this->options['contact__settings']['autocomplete']) . '"' : '');
        $content_form .= ' data-disallow-temp-email="' . ($this->options['contact__settings']['disallow_temp_email'] ? '1' : '0') . '"';
        $content_form .= ' data-disposable-domains="' . base64_encode(json_encode($this->options['contact__settings']['extra_disposable_domains'])) . '"';
        $content_form .= ' data-autofill="' . ($form_autofill ? '1' : '0') . '">';

        /* Group start */
        $content_form .= '<' . $this->options['contact__settings']['group_tagname'] . ' class="' . $this->options['contact__settings']['group_class'] . '">';

        $first_group = array_key_first($content_fields);
        $last_group = array_key_last($content_fields);
        $fieldset_i = 0;
        foreach ($content_fields as $fieldset_id => $fieldset_values) {
            $content_form .= '<' . $fieldset_tagname . ' class="' . $fieldset_classname . '" data-wpucontactforms-group="1" data-wpucontactforms-group-id="' . $fieldset_i . '" data-visible="' . ($fieldset_id === $first_group ? '1' : '0') . '">';
            if (isset($this->contact_steps[$fieldset_id]['html_before']) && $this->contact_steps[$fieldset_id]['html_before']) {
                $content_form .= $this->contact_steps[$fieldset_id]['html_before'];
            }
            if (isset($this->contact_steps[$fieldset_id]['title']) && $this->contact_steps[$fieldset_id]['title']) {
                $content_form .= '<div class="legend wpucontactforms-fieldset__title">' . $this->contact_steps[$fieldset_id]['title'] . '</div>';
            }
            $content_form .= $fieldset_values;

            /* Final form control */
            if ($fieldset_id === $last_group) {
                $content_form .= $this->page_content__extra_fields($form_id, $is_preview_mode);
                $content_form .= $this->page_content__get_submit_box($form_id, $is_preview_mode, $fieldset_id === $first_group, $args);
            }
            /* Intermediate form control */
            else {
                $content_form .= $this->page_content__get_submit_box_intermediate($form_id, $is_preview_mode, $fieldset_id === $first_group, $fieldset_id);
            }

            if (isset($this->contact_steps[$fieldset_id]['html_after']) && $this->contact_steps[$fieldset_id]['html_after']) {
                $content_form .= $this->contact_steps[$fieldset_id]['html_after'];
            }
            $content_form .= '</' . $fieldset_tagname . '>';
            $fieldset_i++;
        }

        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        /* Close form */
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

    public function page_content__extra_fields($form_id, $is_preview_mode) {

        $content_form = '';

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
        if (!$is_preview_mode && $this->has_recaptcha_turnstile) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' box-recaptcha-turnstile" data-sitekey="' . $this->options['contact__settings']['recaptcha_sitekey'] . '">';
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }
        if (!$is_preview_mode && $this->has_recaptcha_hcaptcha) {
            $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['box_class'] . ' box-recaptcha-hcaptcha" data-sitekey="' . $this->options['contact__settings']['recaptcha_sitekey'] . '">';
            $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';
        }

        return $content_form;
    }

    public function page_content__get_submit_box_intermediate($form_id, $is_preview_mode, $is_first_group, $fieldset_id) {

        $content_form = '';

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_intermediate_class'] . '">';
        $content_form .= apply_filters('wpucontactforms_fields_submit_intermediate_inner_before', '', $form_id, $fieldset_id);
        if (!$is_first_group) {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_intermediate_prev_class'] . '" data-type="previous" type="button"><span>' . $this->options['contact__settings']['submit_intermediate_prev_label'] . '</span></button>';
        }
        $content_form .= '<button class="' . $this->options['contact__settings']['submit_intermediate_class'] . '" data-type="next" type="button"><span>' . $this->options['contact__settings']['submit_intermediate_label'] . '</span></button>';
        $content_form .= apply_filters('wpucontactforms_fields_submit_intermediate_inner_after', '', $form_id, $fieldset_id);
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        return $content_form;
    }

    public function page_content__get_submit_box($form_id, $is_preview_mode, $is_first_group, $args = array()) {
        if (!is_array($args)) {
            $args = array();
        }
        if (!isset($args['extra_hidden_fields']) || !is_array($args['extra_hidden_fields'])) {
            $args['extra_hidden_fields'] = array();
        }

        $content_form = '';

        /* Extract page URL */
        $page_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if (isset($args['tmp__page_url'])) {
            $tmp_page_url = base64_decode($args['tmp__page_url']);
            if (filter_var($tmp_page_url, FILTER_VALIDATE_URL) !== false) {
                $page_url = $tmp_page_url;
            }
        }
        $page_url = htmlspecialchars($page_url, ENT_QUOTES, 'UTF-8');

        /* Extract page title */
        $page_title = wp_title(' | ', false);
        if (isset($args['tmp__page_title'])) {
            $tmp_page_title = base64_decode($args['tmp__page_title']);
            if ($tmp_page_title) {
                $page_title = $tmp_page_title;
            }
        }
        $page_title = htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8');

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_class'] . '">';
        $content_form .= apply_filters('wpucontactforms_fields_submit_inner_before', '', $form_id);
        $hidden_fields = apply_filters('wpucontactforms_hidden_fields', array(
            'form_id' => $form_id,
            'page_url' => base64_encode($page_url),
            'page_title' => base64_encode($page_title),
            'control_stripslashes' => '&quot;',
            'wpucontactforms_send' => '1',
            'action' => 'wpucontactforms',
            'extra_hidden_fields' => implode(',', array_keys($args['extra_hidden_fields']))
        ), $this->options);
        $hidden_fields = array_merge($hidden_fields, $args['extra_hidden_fields']);
        if (!$is_preview_mode) {
            foreach ($hidden_fields as $name => $value) {
                $content_form .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
            }
        }
        if (!$is_first_group) {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_intermediate_prev_class'] . '" data-type="previous" type="button"><span>' . $this->options['contact__settings']['submit_intermediate_prev_label'] . '</span></button>';
        }
        if ($this->options['contact__settings']['submit_type'] == 'button') {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_class'] . '" type="submit"><span>' . $this->options['contact__settings']['submit_label'] . '</span></button>';
        } else {
            $content_form .= '<input class="' . $this->options['contact__settings']['submit_class'] . '" type="submit" value="' . esc_attr($this->options['contact__settings']['submit_label']) . '">';
        }
        $content_form .= apply_filters('wpucontactforms_fields_submit_inner_after', '', $form_id);
        $content_form .= '</' . $this->options['contact__settings']['box_tagname'] . '>';

        return $content_form;
    }

    public function field_content($field) {
        $is_preview_mode = $this->is_preview_form();

        if (!$is_preview_mode && isset($field['type'], $field['html']) && $field['type'] == 'html') {
            return $field['html'];
        }

        $content = '';

        $content .= $field['html_before_input'];

        $id = $field['id'];
        if ($is_preview_mode) {
            $id = 'preview_wpucontactforms__' . $id;
        }
        $id_html = $this->options['id'] . '_' . $id;
        $is_multiple = isset($field['multiple']) && $field['multiple'];
        if ($field['type'] == 'checkbox-list') {
            $is_multiple = true;
        }

        $label_after_input = isset($field['label_after_input']) && $field['label_after_input'];
        $input_multiple = ($field['type'] == 'file' && $is_multiple);

        $field_id_name = '';
        if ($field['type'] != 'radio' && $field['type'] != 'checkbox-list') {
            $field_id_name .= ' id="' . $id_html . '" aria-labelledby="label-' . $id_html . '"';
        }

        if ($input_multiple || $is_multiple) {
            $field_id_name .= ' name="' . $id . '[]"';
        } else {
            $field_id_name .= ' name="' . $id . '"';
        }
        if ($is_multiple && $field['type'] == 'select') {
            $field_id_name .= ' multiple';
        }

        if (!$is_preview_mode) {
            $field_id_name .= '  aria-required="' . ($field['required'] ? 'true' : 'false') . '" ';
        }

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
        if (empty($field['placeholder']) && $label_after_input) {
            $field['placeholder'] = $field['label'];
        }
        if (!empty($field['placeholder'])) {
            $field_id_name .= ' placeholder="' . esc_attr($field['placeholder']) . '"';
            $placeholder = $field['placeholder'];
        }

        // Default pattern for tel
        if ($field['type'] == 'tel' && (!isset($field['validation_pattern']) || empty($field['validation_pattern']))) {
            $field['validation_pattern'] = $this->phone_pattern;
        }

        // Validation
        if (isset($field['validation_pattern']) && !empty($field['validation_pattern'])) {
            $field_id_name .= ' pattern="' . $field['validation_pattern'] . '"';
        }

        // Extra attributes
        if (isset($field['attributes_extra']) && !empty($field['attributes_extra'])) {
            $field_id_name .= $field['attributes_extra'];
        }

        // Number
        if ($field['type'] == 'number') {
            $field_id_name .= ' min="' . $field['min'] . '"';
            $field_id_name .= ' max="' . $field['max'] . '"';
        }

        // Value
        $field_val = 'value="' . (is_string($field['value']) ? $field['value'] : '') . '"';

        // Additional HTML
        $after_checkbox = isset($field['html_after_checkbox']) ? $field['html_after_checkbox'] : '';
        $before_checkbox = isset($field['html_before_checkbox']) ? $field['html_before_checkbox'] : '';

        $label_content = '';
        $label_extra = ' ' . ($field['required'] ? $this->options['contact__settings']['label_text_required'] : '');
        if (isset($field['label'])) {
            $label_content = $field['label'] . $label_extra;
        }
        if (isset($field['label_display'])) {
            $label_content = $field['label_display'] . $label_extra;
        }
        $label_content_html = '';
        if (!empty($label_content)) {
            $label_content_html .= '<label ' . ($field['required'] ? 'data-for-required="1"' : '') . ' class="wpucontactform-itemlabel label-' . $id . '" id="label-' . $id_html . '" for="' . $id_html . '">' . $label_content . '</label>';
        }
        if ($label_content_html && !$label_after_input) {
            $content .= $label_content_html;
        }

        $content .= $field['html_before_input'];

        if (isset($field['datalist']) && is_array($field['datalist'])) {
            $datalist_id = 'datalist_' . $id_html;
            $field_id_name .= ' list="' . $datalist_id . '"';
            $content .= '<datalist id="' . $datalist_id . '">';
            foreach ($field['datalist'] as $val) {
                if ($val) {
                    $content .= '<option value="' . esc_attr($val) . '">';
                }
            }
            $content .= '</datalist>';
        }

        switch ($field['type']) {
        case 'select':
            $content .= '<select  ' . $field_id_name . '>';
            $content .= '<option value="" disabled selected style="display:none;">' . esc_html($placeholder) . '</option>';
            $has_optgroup = false;
            foreach ($field['datas'] as $key => $val) {
                /* Optgroup : load value and ignore opt line */
                if (is_array($val) && isset($val['optgroup'])) {
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
                $content .= '<span class="' . $this->options['contact__settings']['label_radio_inner__classname'] . '">' . $val . '</span></label>';
            }
            break;
        case 'file':
            $input_file = '<input ' . ($input_multiple ? 'multiple' : '') . ' type="file" accept="' . implode(',', $this->get_accepted_file_types($field)) . '" ' . $field_id_name . ' />';
            if (isset($field['fake_upload'])) {
                $input_file = '<div class="fake-upload-wrapper">' . $input_file . '<div class="fake-upload-cover" data-placeholder="' . esc_attr($placeholder) . '">' . $placeholder . '</div></div>';
            }
            $content .= $input_file;
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
            $content .= '<span class="' . $this->options['contact__settings']['label_checkbox_inner__classname'] . '">' . $label_content . '</span></label>';
            break;
        case 'text':
        case 'tel':
        case 'url':
        case 'number':
        case 'email':
            $content .= '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'datepicker':
            $datepicker_args = isset($field['datepicker_args']) ? $field['datepicker_args'] : $datepicker_args;
            if (is_array($datepicker_args)) {
                $datepicker_args = json_encode($datepicker_args);
            }
            $content .= '<input data-wpucontactforms-datepicker="' . esc_attr($datepicker_args) . '" type="text" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'textarea':
            $nb_cols = isset($field['textarea_nb_cols']) ? $field['textarea_nb_cols'] : 30;
            $nb_rows = isset($field['textarea_nb_rows']) ? $field['textarea_nb_rows'] : 10;

            $textarea_cols_rows_html = '';
            if ($nb_cols) {
                $field_id_name .= ' cols="' . $nb_cols . '"';
            }
            if ($nb_rows) {
                $field_id_name .= ' rows="' . $nb_rows . '"';
            }

            $content .= '<textarea ' . $field_id_name . '>' . $field['value'] . '</textarea>';
            break;
        }

        $content .= $field['html_after_input'];

        if ($label_content_html && $label_after_input) {
            $content .= $label_content_html;
        }

        $box_class_name = esc_attr($this->options['contact__settings']['box_class']);
        $box_class = $box_class_name;
        $box_class .= ' ' . $box_class_name . '--' . $id;
        if ($field['box_class']) {
            $box_class .= ' ' . esc_attr($field['box_class']);
        }
        if ($label_after_input) {
            $box_class .= ' box--labelafterinput';
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

            $error_invalid = __('This field is invalid', 'wpucontactforms');
            if ($field['type'] == 'email') {
                $error_invalid = __('This is not a valid e-mail address', 'wpucontactforms');
            }
            if ($field['type'] == 'tel') {
                $error_invalid = __('This is not a valid phone number', 'wpucontactforms');
            }

            $error_invalid = apply_filters('wpucontactforms__error_invalid_txt', $error_invalid);
            if (isset($field['error_invalid'])) {
                $error_invalid = $field['error_invalid'];
            }
            $error_suggest = apply_filters('wpucontactforms__error_suggest_txt', __('Did you mean: %s ?', 'wpucontactforms'));
            if (isset($field['error_suggest'])) {
                $error_suggest = $field['error_suggest'];
            }
            $error_choose = apply_filters('wpucontactforms__error_choose_txt', __('A value should be selected', 'wpucontactforms'));
            if (isset($field['error_choose'])) {
                $error_choose = $field['error_choose'];
            }
            $error_empty = apply_filters('wpucontactforms__error_empty_txt', __('This field should not be empty', 'wpucontactforms'));
            if (isset($field['error_empty'])) {
                $error_empty = $field['error_empty'];
            }
            $error_file_heavy = apply_filters('wpucontactforms__error_file_heavy_txt', __('This file is too heavy (%sMb)', 'wpucontactforms'));
            if (isset($field['error_file_heavy'])) {
                $error_file_heavy = $field['error_file_heavy'];
            }

            $content .= '<div';
            $content .= ' data-error-invalid="' . esc_attr($error_invalid) . '"';
            if ($field['type'] == 'email') {
                $content .= ' data-error-suggest="' . esc_attr($error_suggest) . '"';
            }
            if ($field['type'] == 'file') {
                $content .= ' data-error-file-heavy="' . esc_attr($error_file_heavy) . '"';
            }
            $content .= ' data-error-choose="' . esc_attr($error_choose) . '"';
            $content .= ' data-error-empty="' . esc_attr($error_empty) . '"';
            $content .= ' class="error" aria-live="polite"></div>';
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
        $field['html_box_start'] .
        $content .
        $field['html_box_end'] .
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
        if ($this->has_recaptcha_v2 || $this->has_recaptcha_v3 || $this->has_recaptcha_turnstile || $this->has_recaptcha_hcaptcha) {

            $response_field = 'g-recaptcha-response';
            $callback_url = 'https://www.google.com/recaptcha/api/siteverify';

            if ($this->has_recaptcha_turnstile) {
                $response_field = 'cf-turnstile-response';
                $callback_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
            }

            if ($this->has_recaptcha_hcaptcha) {
                $response_field = 'h-captcha-response';
                $callback_url = 'https://hcaptcha.com/siteverify';
            }

            if (!isset($_POST[$response_field])) {
                $this->msg_errors[] = __('The captcha is invalid', 'wpucontactforms');
            } else {
                $recaptcha_args = apply_filters('wpucontactforms__recaptcha_args', array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body' => array(
                        'secret' => $this->options['contact__settings']['recaptcha_privatekey'],
                        'response' => $_POST[$response_field]
                    ),
                    'cookies' => array()
                ));
                $response = wp_remote_post($callback_url, $recaptcha_args);
                $body_response = wp_remote_retrieve_body($response);
                $body_response_json = json_decode($body_response);
                if (!is_object($body_response_json) || !isset($body_response_json->success) || !$body_response_json->success) {
                    $this->msg_errors[] = __('The captcha is invalid', 'wpucontactforms');
                    return;
                }
            }
        }

        $this->post_contact_send_action($_POST);
    }

    function post_contact_send_action($post_array) {

        if (!isset($post_array['form_id']) || $post_array['form_id'] != $this->options['id']) {
            return;
        }

        if (!isset($this->msg_errors) || !is_array($this->msg_errors)) {
            $this->msg_errors = array();
        }

        do_action('wpucontactforms_beforesubmit_contactform_manual', $this);

        $this->options = apply_filters('wpucontactforms_options_before_submit', $this->options, $post_array);

        $this->contact_fields = $this->extract_value_from_post($post_array, $this->contact_fields);

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

        // Extract source page
        if (!isset($post_array['page_url'], $post_array['page_title'])) {
            $this->msg_errors[] = __('Invalid source', 'wpucontactforms');
            $post_array['page_url'] = '';
            $post_array['page_title'] = '';
        }
        $page_url = base64_decode($post_array['page_url']);
        $page_title = base64_decode($post_array['page_title']);
        if (empty($page_url) || filter_var($page_url, FILTER_VALIDATE_URL) === false) {
            $this->msg_errors[] = __('Invalid source', 'wpucontactforms');
        }
        $this->form_submitted_page_url = $page_url;
        $this->form_submitted_page_title = $page_title;

        // Extract anonymized user IP
        $this->form_submitted_ip = $this->get_user_ip();
        $this->form_submitted_hashed_ip = md5(site_url() . $this->get_user_ip());

        // Add custom error messages.
        $this->msg_errors = apply_filters('wpucontactforms_submit_contactform_msg_errors', $this->msg_errors, $this);

        if (!empty($this->msg_errors)) {
            $this->content_contact .= '<p class="contact-error"><strong>' . __('Error:', 'wpucontactforms') . '</strong><br />' . implode('<br />', $this->msg_errors) . '</p>';
            return;
        }

        $this->content_contact = '';

        // Setting success message
        $this->content_contact .= $this->options['contact__success'];
        $this->content_contact = apply_filters('wpucontactforms_submit_contactform_content_contact', $this->content_contact, $this, $post_array);

        // Trigger success action
        do_action('wpucontactforms_submit_contactform', $this);

        $this->is_successful = true;

    }

    public function extract_value_from_post($post, $contact_fields) {
        $contact_fields = apply_filters('wpucontactforms_fields_values_before_post_comparison', $contact_fields, $post);
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
                $contact_fields[$id]['posted_value'] = $tmp_value;
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

                    if (($field['type'] == 'select' && !$is_multiple) || $field['type'] == 'radio') {
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

        add_post_meta($attachment_id, '_wpucontactforms_att', '1');

        return $attachment_id;
    }

    public function validate_field_file($file, $field) {

        // Max size
        if ($file['size'] >= $this->options['contact__settings']['max_file_size']) {
            return false;
        }

        // Type
        $mime_type = mime_content_type($file['tmp_name']);
        if (!in_array($mime_type, $this->get_accepted_file_types($field))) {
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

        /* Check if value contains excluded words */
        if (isset($field['check_excluded_words']) && $field['check_excluded_words']) {
            foreach ($this->user_options['excluded_words'] as $word) {
                if (strpos($tmp_value, $word) !== false) {
                    return false;
                }
            }
        }

        $zero_one = array('0', '1');
        switch ($field['validation']) {
        case 'radio':
        case 'select':
            if (!is_array($tmp_value) || !isset($field['multiple']) || !$field['multiple']) {
                return array_key_exists($tmp_value, $field['datas']);
            }
            foreach ($tmp_value as $tmp_value_item) {
                if (!array_key_exists($tmp_value_item, $field['datas'])) {
                    return false;
                }
            }
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
            return (is_numeric($tmp_value) && $tmp_value >= $field['min'] && $tmp_value <= $field['max']);
            break;
        case 'email':
            if ($this->options['contact__settings']['disallow_temp_email']) {
                foreach ($this->disposable_domains as $domain) {
                    if (strpos($tmp_value, '@' . $domain) !== false) {
                        return false;
                    }
                }
            }
            return filter_var($tmp_value, FILTER_VALIDATE_EMAIL) !== false;
            break;
        case 'tel':
            return preg_match('/' . $this->phone_pattern . '/', $tmp_value);
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
        $args = array(
            'extra_hidden_fields' => array()
        );
        if (isset($_POST['page_title'])) {
            $args['tmp__page_title'] = $_POST['page_title'];
        }
        if (isset($_POST['page_url'])) {
            $args['tmp__page_url'] = $_POST['page_url'];
        }
        if (isset($_POST['extra_hidden_fields'])) {
            $hidden_fields = explode(',', $_POST['extra_hidden_fields']);
            foreach ($hidden_fields as $field_id) {
                $field_id_clear = esc_html($field_id);
                /* If an hidden field ID is invalid, refuse POST request */
                if ($field_id_clear != $field_id) {
                    return false;
                }
                if (isset($_POST[$field_id_clear])) {
                    $args['extra_hidden_fields'][$field_id_clear] = esc_html($_POST[$field_id_clear]);
                }
            }
        }
        $this->post_contact();
        $this->page_content(true, $this->options['id'], $args);
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

    /* Export */
    function page_content__export() {
        $terms = get_terms(array(
            'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
            'hide_empty' => true
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            echo '<p>';
            echo '<label for="wpucontactforms_export_term">' . __('Choose a form:', 'wpucontactforms') . '</label><br />';
            echo '<select id="wpucontactforms_export_term" name="term">';
            echo '<option>' . __('All forms', 'wpucontactforms') . '</option>';
            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name);
                if ($term->count) {
                    echo ' (' . $term->count . ')';
                }
                echo '</option>';
            }
            echo '</select>';
            echo '</p>';
        } else {
            echo __('No messages to export yet.', 'wpucontactforms');
            return;
        }
        submit_button(__('Export', 'wpucontactforms'));
    }

    function page_action__export() {
        $term = '';
        if (isset($_POST['term']) && term_exists($_POST['term'], wpucontactforms_savepost__get_taxonomy())) {
            $term = $_POST['term'];
        }

        $data = array();

        $args = array(
            'posts_per_page' => -1,
            'post_type' => wpucontactforms_savepost__get_post_type()
        );
        if ($term) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
                    'field' => 'slug',
                    'terms' => $term
                )
            );
        }

        $posts = get_posts($args);
        foreach ($posts as $p) {
            $item = array(
                'name' => $p->post_title,
                'date' => $p->post_date
            );
            $meta = get_post_meta($p->ID, '', false);
            foreach ($meta as $meta_k => $meta_item) {
                /* Remove private metas */
                if ($meta_k[0] == '_') {
                    continue;
                }
                $item[$meta_k] = implode($meta_item);
            }
            $data[] = $item;
        }

        $this->export_array_to_csv($data, $term ? $term : 'all-forms');
    }

    /* ----------------------------------------------------------
      Utilities : Export
    ---------------------------------------------------------- */

    function export_array_clean($data) {

        /* Extract all available keys */
        $all_keys = array();
        foreach ($data as $item) {
            $all_keys = array_merge($all_keys, array_keys($item));
        }
        $all_keys = array_unique($all_keys);

        foreach ($data as $item_key => $item) {
            /* Ensure all rows have the same keys */
            foreach ($all_keys as $k) {
                if (!isset($item[$k])) {
                    $data[$item_key][$k] = '';
                }
            }
            /* Ensure same sorting of all keys */
            ksort($data[$item_key]);
        }

        return $data;
    }

    /* Array to CSV
    -------------------------- */

    public function export_array_to_csv($data, $name) {
        if (!isset($data[0])) {
            return;
        }

        $data = $this->export_array_clean($data);

        /* Correct headers */
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=export-' . $name . '-' . date_i18n('Ymd-His') . '.csv');
        header('Pragma: no-cache');

        $all_keys = array_keys($data[0]);

        /* Build and send CSV */
        $output = fopen("php://output", 'w');
        fputcsv($output, $all_keys);
        foreach ($data as $item) {
            fputcsv($output, $item);
        }
        fclose($output);
        die;
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

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
                $url = wp_get_attachment_url($attachment->ID);
                echo '<li>- <a download="' . basename($url) . '" href="' . $url . '">' . $attachment->post_title . ' (' . $attachment->post_mime_type . ')</a></li>';
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

    /* Thanks to https://stackoverflow.com/a/13646735/975337 */
    function get_user_ip($anonymized = true) {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : '';
        $forward = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
        $remote = $_SERVER['REMOTE_ADDR'];

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }
        if (!$anonymized) {
            return $ip;
        }
        return $this->anonymize_ip($ip);
    }

    /* Thanks to https://gist.github.com/svrnm/3a124d2af18a6726f66e */
    function anonymize_ip($ip) {
        if ($ip = @inet_pton($ip)) {
            return inet_ntop(substr($ip, 0, strlen($ip) / 2) . str_repeat(chr(0), strlen($ip) / 2));
        }
        return '0.0.0.0';
    }
}

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

function wpucontactform__set_html_extra_content($form) {
    $html = '';

    if ($form->form_submitted_page_url) {
        $html .= '<strong>' . __('URL:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_page_url) . '<br />';
    }
    if ($form->form_submitted_page_title) {
        $html .= '<strong>' . __('Page title:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_page_title) . '<br />';
    }
    if ($form->form_submitted_ip) {
        $html .= '<strong>' . __('IP:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_ip) . '<br />';
    }
    if ($form->form_submitted_hashed_ip) {
        $html .= '<strong>' . __('Hashed IP:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_hashed_ip) . '<br />';
    }
    return $html;
}

function wpucontactform__set_html_field_content($field, $wrap_html = true) {
    $field_content = $field['value'];

    /* Better presentation for text */
    if ($field['type'] == 'textarea') {
        $field_content = nl2br($field_content);
    }

    $is_select_multiple = ($field['type'] == 'select' && isset($field['multiple']) && $field['multiple']);
    if (($field['type'] == 'checkbox-list' || $is_select_multiple) && is_array($field['value'])) {
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

    $headers = array();

    /* Subject */
    $from_name = '';
    if (isset($form_contact_fields['contact_firstname'])) {
        $from_name = $form_contact_fields['contact_firstname']['value'];
    }
    if (isset($form_contact_fields['contact_name'])) {
        $from_name .= ' ' . $form_contact_fields['contact_name']['value'];
        $from_name = trim($from_name);
    }
    if (isset($form_contact_fields['contact_email'])) {
        $headers[] = 'Reply-To: ' . $from_name . ' <' . $form_contact_fields['contact_email']['value'] . '>';
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
    $admin_email = get_option('admin_email');
    if (!is_email($target_email)) {
        $target_email = $admin_email;
    }
    $target_email = apply_filters('wpucontactforms_email', $target_email, $form_options, $form_contact_fields);

    /* Clean target email */
    if (is_array($target_email)) {
        $target_email = implode(';', $target_email);
    }
    $target_email = str_replace(array(';', ' ', ','), ';', $target_email);
    $target_email = array_filter(explode(';', $target_email));
    if (!$target_email) {
        $target_email = $admin_email;
    }

    /* More */
    if (!is_array($more)) {
        $more = array();
    }
    if (!isset($more['attachments'])) {
        $more['attachments'] = array();
    }
    if (!isset($more['headers'])) {
        $more['headers'] = $headers;
    }

    /* Send content */
    if (function_exists('wputh_sendmail')) {
        wputh_sendmail($target_email, $sendmail_subject, $mail_content, $more);
    } else {
        wp_mail($target_email, $sendmail_subject, $mail_content, $headers, $more['attachments']);
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

    $mail_content .= '<hr />';

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
        $mail_content .= wpucontactform__set_html_field_content($field) . '<hr />';
    }

    $mail_content .= wpucontactform__set_html_extra_content($form);

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
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => false
            ),
            'map_meta_cap' => true,
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

    $post_content .= wpucontactform__set_html_extra_content($form);

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

    if ($form->form_submitted_page_url) {
        update_post_meta($post_id, 'form_url_referrer', $form->form_submitted_page_url);
    }
    if ($form->form_submitted_page_title) {
        update_post_meta($post_id, 'form_title_referrer', $form->form_submitted_page_title);
    }
    if ($form->form_submitted_ip) {
        update_post_meta($post_id, 'form_submitted_ip', $form->form_submitted_ip);
    }
    if ($form->form_submitted_hashed_ip) {
        update_post_meta($post_id, 'form_submitted_hashed_ip', $form->form_submitted_hashed_ip);
    }

    // Add term
    $term = wp_insert_term(
        apply_filters('wpucontactforms_submit_contactform__savepost__term_name', $form->options['name']),
        $taxonomy,
        array(
            'slug' => apply_filters('wpucontactforms_submit_contactform__savepost__term_slug', $form->options['id'])
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
