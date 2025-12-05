<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Contact forms
Plugin URI: https://github.com/WordPressUtilities/wpucontactforms
Update URI: https://github.com/WordPressUtilities/wpucontactforms
Version: 3.28.0
Description: Contact forms
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpucontactforms
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class wpucontactforms {
    public $msg_errors;
    public $form_submitted_page_url;
    public $form_submitted_page_title;
    public $form_submitted_ip;
    public $form_submitted_hashed_ip;
    public $wpubasemessages;
    public $basetoolbox;

    private $plugin_version = '3.28.0';
    private $humantest_classname = 'hu-man-te-st';
    private $first_init = true;
    public $has_recaptcha_v2 = false;
    public $has_recaptcha_v3 = false;
    public $has_recaptcha_hcaptcha = false;
    public $has_recaptcha_turnstile = false;
    public $user_options = false;
    public $settings_details;
    public $settings;
    public $basecron;
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
    public $allowed_url_params = array(
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid'
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
        if (array_key_exists($options['id'], $wpucontactforms_forms)) {
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

        $wpucontactforms_forms[$options['id']] = array();

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
        add_action('wpucontactforms_submit_contactform', array(&$this,
            'wpucontactforms_submit_contactform__webhook'
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
                'gdpr' => array(
                    'name' => __('GDPR', 'wpucontactforms')
                ),
                'webhook' => array(
                    'name' => __('Webhook', 'wpucontactforms')
                ),
                'content' => array(
                    'name' => __('Content Settings', 'wpucontactforms')
                ),
                'recaptcha' => array(
                    'name' => __('Captcha', 'wpucontactforms')
                ),
                'settings' => array(
                    'name' => __('Settings')
                )
            )
        );
        $this->settings = array(
            'autodelete_enabled' => array(
                'label' => __('Enable', 'wpucontactforms'),
                'label_check' => __('Auto-delete messages after n month', 'wpucontactforms'),
                'type' => 'checkbox',
                'section' => 'gdpr'
            ),
            'autodelete_duration' => array(
                'label' => __('Messages expiration', 'wpucontactforms'),
                'help' => __('Messages will be automatically deleted after this period in months', 'wpucontactforms'),
                'type' => 'number',
                'default_value' => 36,
                'section' => 'gdpr'
            ),
            'webhook_url' => array(
                'label' => __('Webhook URL', 'wpucontactforms'),
                'type' => 'url',
                'section' => 'webhook'
            ),
            'excluded_words' => array(
                'label' => __('Excluded Words', 'wpucontactforms'),
                'help' => __('One word or expression per line.', 'wpucontactforms'),
                'type' => 'textarea',
                'section' => 'content'
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
            ),
            'log_spams' => array(
                'label' => __('Log spams', 'wpucontactforms'),
                'label_check' => __('Spams will be logged to the debug log file', 'wpucontactforms'),
                'type' => 'checkbox',
                'section' => 'settings'
            ),
            'disable_emails' => array(
                'label' => __('Disable emails', 'wpucontactforms'),
                'label_check' => __('No email will be sent on form submission', 'wpucontactforms'),
                'type' => 'checkbox',
                'section' => 'settings'
            ),
            'disable_saving_messages' => array(
                'label' => __('Disable saving messages', 'wpucontactforms'),
                'label_check' => __('Messages will not be saved in the database', 'wpucontactforms'),
                'type' => 'checkbox',
                'section' => 'settings'
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
            add_action('admin_menu', array(&$this, 'create_admin_form_submenus_forms'));

            /* Add meta boxes */
            add_action('add_meta_boxes', array(&$this, 'register_meta_boxes'));

            /* Update */
            require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
            $this->settings_update = new \wpucontactforms\WPUBaseUpdate(
                'WordPressUtilities',
                'wpucontactforms',
                $this->plugin_version);

            /* Settings */
            if (is_admin()) {
                require_once __DIR__ . '/inc/WPUBaseSettings/WPUBaseSettings.php';
                new \wpucontactforms\WPUBaseSettings($this->settings_details, $this->settings);
            }

            /* Messages */
            if (is_admin()) {
                require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
                $this->wpubasemessages = new \wpucontactforms\WPUBaseMessages(__NAMESPACE__);
            }

            // Init admin page
            require_once __DIR__ . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
            $this->adminpages = new \wpucontactforms\WPUBaseAdminPage();
            $this->adminpages->init($pages_options, $admin_pages);

            /* Cron for deletion */
            require_once __DIR__ . '/inc/WPUBaseCron/WPUBaseCron.php';
            $this->basecron = new \wpucontactforms\WPUBaseCron(array(
                'pluginname' => 'WPU Contact forms',
                'cronhook' => 'wpucontactforms__cron_hook',
                'croninterval' => 3600
            ));
            add_action('wpucontactforms__cron_hook', array(&$this,
                'wpucontactforms__callback_cron'
            ), 10);
        }

        # TOOLBOX
        require_once __DIR__ . '/inc/WPUBaseToolbox/WPUBaseToolbox.php';
        $this->basetoolbox = new \wpucontactforms\WPUBaseToolbox(array(
            'need_form_js' => false
        ));

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

        $wpucontactforms_forms[$options['id']]['fields'] = $this->contact_fields;

        add_filter('wpucontactforms_submit_contactform__savepost__disable', function ($disable, $form) {
            if (isset($this->user_options['disable_emails']) && $this->user_options['disable_emails'] == '1') {
                return true;
            }
            return $disable;
        }, 50, 2);

        add_filter('wpucontactforms_submit_contactform__sendmail__disable', function ($disable, $form) {
            if (isset($this->user_options['disable_saving_messages']) && $this->user_options['disable_saving_messages'] == '1') {
                return true;
            }
            return $disable;
        }, 50, 2);

    }

    public function set_user_options() {
        $this->user_options = get_option('wpucontactforms_options');
        if (!is_array($this->user_options)) {
            $this->user_options = array();
        }

        /* Excluded Words */
        if (!isset($this->user_options['excluded_words']) || empty($this->user_options['excluded_words'])) {
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
                    continue;
                }
            }
        }

        wp_enqueue_style('wpucontactforms-frontcss', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version, 'all');
        if (is_admin()) {
            wp_enqueue_style('wpucontactforms-admincss', plugins_url('assets/admin.css', __FILE__), array(), $this->plugin_version, 'all');
            if (isset($_GET['page']) && $_GET['page'] == 'wpucontactforms-export') {
                wp_enqueue_script('wpucontactforms-adminjs', plugins_url('assets/admin.js', __FILE__), array(
                    'jquery'
                ), $this->plugin_version, true);
                wp_localize_script('wpucontactforms-adminjs', 'wpucontactforms_adminobj', $this->get_admin_data());
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('wpucontactforms-admin-jqueryui', plugins_url('assets/jquery-ui/jquery-ui-1.9.2.custom.min.css', __FILE__), array(), $this->plugin_version, 'all');
            }
        } else {
            wp_enqueue_script('wpucontactforms-front', plugins_url('assets/front.js', __FILE__), array(
                'jquery'
            ), $this->plugin_version, true);
            wp_localize_script('wpucontactforms-front', 'wpucontactforms_obj', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'disposable_domains' => base64_encode(json_encode($this->disposable_domains)),
                'allowed_url_params' => base64_encode(json_encode($this->allowed_url_params)),
                'enable_custom_validation' => $this->options['contact__settings']['enable_custom_validation']
            ));
        }
    }

    public function get_admin_data() {
        $data = array(
            'oldest_message' => '2000-01-01',
            'today' => date('Y-m-d')
        );
        $oldest_message = get_posts(array(
            'orderby' => 'date',
            'order' => 'ASC',
            'limit' => 1,
            'post_type' => wpucontactforms_savepost__get_post_type()
        ));
        if (is_array($oldest_message) && isset($oldest_message[0])) {
            $data['oldest_message'] = date('Y-m-d', strtotime($oldest_message[0]->post_date));
        }

        return $data;
    }

    public function create_admin_form_submenus_forms() {
        global $submenu;

        if (!apply_filters('wpucontactforms_create_admin_form_submenus_forms', true)) {
            return;
        }

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
        $this->allowed_url_params = apply_filters('wpucontactforms_allowed_url_params', $this->allowed_url_params);
        $this->is_successful = false;
        $this->has_upload = false;
        $this->content_contact = '';
        $this->default_field = array(
            'box_class' => '',
            'classname' => '',
            'classname_extra' => '',
            'html_box_start' => '',
            'html_box_end' => '',
            'html_after' => '',
            'html_after_input' => '',
            'html_before_label' => '',
            'html_after_label' => '',
            'html_before' => '',
            'html_before_input' => '',
            'input_inside_label' => true,
            'label_classname' => '',
            'label_checkbox_classname' => '',
            'placeholder' => '',
            'meta_value_saved' => 'displayed',
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
            'fieldset_show_count' => false,
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
            'resubmit_delay_days' => 0,
            'resubmit_delay_field' => 'contact_email',
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
        if (!$form_id || !is_string($form_id) || $form_id !== $this->options['id']) {
            if (!is_string($form_id)) {
                error_log('WPU Contact forms : form_id is not a string');
            }
            return '';
        }
        if (!is_array($args)) {
            $args = array();
        }

        /* There is no previous posted data, so we try to retrieve it from the global page info */
        if (!isset($args['custom__form_data'])) {
            $args['custom__form_data'] = wpucontactforms_get_safe_form_data($this->page_content_get_form_data());
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
        /* Init contact steps */
        foreach ($this->contact_steps as $step_id => $step) {
            if (!isset($step['title'])) {
                $this->contact_steps[$step_id]['title'] = '';
            }
            if (!isset($step['submit_intermediate_prev_label'])) {
                $this->contact_steps[$step_id]['submit_intermediate_prev_label'] = $this->options['contact__settings']['submit_intermediate_prev_label'];
            }
            if (!isset($step['submit_intermediate_label'])) {
                $this->contact_steps[$step_id]['submit_intermediate_label'] = $this->options['contact__settings']['submit_intermediate_label'];
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
        $nb_fieldsets = count($content_fields);
        foreach ($content_fields as $fieldset_id => $fieldset_values) {
            $args['current_fieldset_step'] = $this->contact_steps[$fieldset_id];
            $content_form .= '<' . $fieldset_tagname . ' class="' . $fieldset_classname . '" data-wpucontactforms-group="1" data-wpucontactforms-group-name="' . esc_attr($fieldset_id) . '" data-wpucontactforms-group-id="' . $fieldset_i . '" data-visible="' . ($fieldset_id === $first_group ? '1' : '0') . '">';
            if (isset($this->contact_steps[$fieldset_id]['html_before']) && $this->contact_steps[$fieldset_id]['html_before']) {
                $content_form .= $this->contact_steps[$fieldset_id]['html_before'];
            }
            if (isset($this->contact_steps[$fieldset_id]['title']) && $this->contact_steps[$fieldset_id]['title']) {
                $legend_count = ($this->options['contact__settings']['fieldset_show_count'] ? ' <span class="legend-count">' . ($fieldset_i + 1) . '/' . $nb_fieldsets . '</span>' : '');
                $content_form .= '<div class="legend wpucontactforms-fieldset__title">' . $this->contact_steps[$fieldset_id]['title'] . $legend_count . '</div>';
            }
            $content_form .= $fieldset_values;

            /* Final form control */
            if ($fieldset_id === $last_group) {
                $content_form .= $this->page_content__extra_fields($form_id, $is_preview_mode);
                $content_form .= $this->page_content__get_submit_box($form_id, $is_preview_mode, $fieldset_id === $first_group, $args);
            }
            /* Intermediate form control */
            else {
                $content_form .= $this->page_content__get_submit_box_intermediate($form_id, $is_preview_mode, $fieldset_id === $first_group, $fieldset_id, $args);
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

    public function page_content__get_submit_box_intermediate($form_id, $is_preview_mode, $is_first_group, $fieldset_id, $args = array()) {

        $content_form = '';

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_intermediate_class'] . '">';
        $content_form .= apply_filters('wpucontactforms_fields_submit_intermediate_inner_before', '', $form_id, $fieldset_id);
        if (!$is_first_group) {
            $content_form .= '<button class="' . $this->options['contact__settings']['submit_intermediate_prev_class'] . '" data-type="previous" type="button"><span>' . $args['current_fieldset_step']['submit_intermediate_prev_label'] . '</span></button>';
        }
        $content_form .= '<button class="' . $this->options['contact__settings']['submit_intermediate_class'] . '" data-type="next" type="button"><span>' . $args['current_fieldset_step']['submit_intermediate_label'] . '</span></button>';
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

        $form_data = json_encode($this->page_content_get_form_data());

        /* Box success && hidden fields */
        $content_form .= '<' . $this->options['contact__settings']['box_tagname'] . ' class="' . $this->options['contact__settings']['group_submit_class'] . '">';
        $content_form .= apply_filters('wpucontactforms_fields_submit_inner_before', '', $form_id, $this->options, $args);
        $hidden_fields = apply_filters('wpucontactforms_hidden_fields', array(
            'form_id' => $form_id,
            'wpucontactforms_form_data' => $form_data,
            'wpucontactforms_form_data_hash' => md5($form_data . DB_PASSWORD),
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

    public function page_content_get_form_data() {
        $raw_form_data = array(
            'post_id' => get_the_ID()
        );
        if (function_exists('get_row_index')) {
            $raw_form_data['acf_row_id'] = get_row_index();
        }
        return $raw_form_data;
    }

    public function field_content($field) {
        $is_preview_mode = $this->is_preview_form();

        if (!$is_preview_mode && isset($field['type'])) {
            if ($field['type'] == 'html' && isset($field['html'])) {
                return $field['html'];
            }
            if ($field['type'] == 'hook_filter' && isset($field['hook_filter'])) {
                return apply_filters($field['hook_filter'], '', $field, $this->options);
            }
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

        $label_after_input = isset($field['label_after_input']) && $field['label_after_input'];
        $input_multiple = ($field['type'] == 'file' && $is_multiple);

        $field_id_name = '';
        if ($field['type'] != 'radio' && $field['type'] != 'checkbox-list') {
            $field_id_name .= ' id="' . $id_html . '"';
            if ($field['type'] != 'checkbox') {
                $field_id_name .= ' aria-labelledby="label-' . $id_html . '"';
            }
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
        if ($field['classname_extra']) {
            $classname .= ' ' . $field['classname_extra'];
        }
        if ($classname) {
            $field_id_name .= ' class="' . esc_attr(trim($classname)) . '"';
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
            if ($field['type'] != 'file') {
                $field_id_name .= ' placeholder="' . esc_attr($field['placeholder']) . '"';
            }
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
            $field_id_name .= ' ' . trim($field['attributes_extra']);
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
        $label_content = trim($label_content);
        $label_content_html = '';
        if (!empty($label_content)) {
            $label_content_html .= $field['html_before_label'];
            $label_content_html .= '<label ' . ($field['required'] ? 'data-for-required="1"' : '') . ' class="wpucontactform-itemlabel label-' . $id . ($field['label_classname'] ? ' ' . esc_attr($field['label_classname']) : '') . '" id="label-' . $id_html . '" for="' . $id_html . '">' . $label_content . '</label>';
            $label_content_html .= $field['html_after_label'];
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
                $input_radio_label_before = '<label for="' . $label_for . '" id="label-' . $id . $key . '" class="label-checkbox ' . ($field['label_checkbox_classname'] ? esc_attr($field['label_checkbox_classname']) : '') . '">';
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
                $has_preview = isset($field['fake_upload__preview']) && $field['fake_upload__preview'];
                $html_input_file = '<div class="fake-upload-wrapper" ' . ($has_preview ? 'data-has-preview="1"' : '') . '>';
                if ($has_preview) {
                    $html_input_file .= '<div class="fake-upload-preview"></div>';
                }
                $html_input_file .= $input_file;
                $html_input_file .= '<div class="fake-upload-cover" data-placeholder="' . esc_attr($placeholder) . '">' . $placeholder . '</div>';
                $html_input_file .= '</div>';
                $input_file = $html_input_file;
            }
            $content .= $input_file;
            break;
        case 'checkbox':
            $content = '';
            $checkbox_content = $before_checkbox . '<input type="' . $field['type'] . '" ' . $field_id_name . ' ' . (isset($field['checked']) && $field['checked'] ? 'checked="checked"' : '') . ' value="1" />' . $after_checkbox;
            if (!$field['input_inside_label']) {
                $content .= $checkbox_content;
            }
            $content .= '<label id="label-' . $id . '" class="label-checkbox ' . ($field['label_checkbox_classname'] ? esc_attr($field['label_checkbox_classname']) : '') . '" for="' . $id_html . '">';
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
            $datepicker_args = isset($field['datepicker_args']) ? $field['datepicker_args'] : array();
            if (is_array($datepicker_args)) {
                $datepicker_args = json_encode($datepicker_args);
            }
            $content .= '<input data-wpucontactforms-datepicker="' . esc_attr($datepicker_args) . '" type="text" ' . $field_id_name . ' ' . $field_val . ' />';
            break;
        case 'textarea':
            $nb_cols = isset($field['textarea_nb_cols']) ? $field['textarea_nb_cols'] : 30;
            $nb_rows = isset($field['textarea_nb_rows']) ? $field['textarea_nb_rows'] : 10;

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

            $error_empty = __('This field should not be empty', 'wpucontactforms');
            if ($field['type'] == 'checkbox') {
                $error_empty = __('This box should be checked', 'wpucontactforms');
            }
            $error_empty = apply_filters('wpucontactforms__error_empty_txt', $error_empty);
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
            if (isset($this->user_options['log_spams']) && $this->user_options['log_spams'] == '1') {
                error_log('WPUContactForms: humantest failed from IP ' . $this->basetoolbox->get_user_ip() . ' - ' . print_r($_POST, true));
            }
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
                if (isset($this->user_options['log_spams']) && $this->user_options['log_spams'] == '1') {
                    error_log('WPUContactForms: recaptcha empty from IP ' . $this->basetoolbox->get_user_ip() . ' - ' . print_r($_POST, true));
                }
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
                    if (isset($this->user_options['log_spams']) && $this->user_options['log_spams'] == '1') {
                        error_log('WPUContactForms: recaptcha failed from IP ' . $this->basetoolbox->get_user_ip() . ' - ' . print_r($_POST, true));
                    }
                    return;
                }
            }
        }

        $this->post_contact_send_action($_POST);
    }

    public function post_contact_send_action($post_array) {

        if (!isset($post_array['form_id']) || $post_array['form_id'] != $this->options['id']) {
            return;
        }

        if (!isset($this->msg_errors) || !is_array($this->msg_errors)) {
            $this->msg_errors = array();
        }

        do_action('wpucontactforms_beforesubmit_contactform_manual', $this);

        $this->options = apply_filters('wpucontactforms_options_before_submit', $this->options, $post_array);
        $post_array = apply_filters('wpucontactforms_post_array_before_submit', $post_array, $this);

        $this->contact_fields = $this->extract_value_from_post($post_array, $this->contact_fields);

        // Validate message field
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
        $this->form_submitted_ip = $this->basetoolbox->get_user_ip();
        $this->form_submitted_hashed_ip = md5(site_url() . $this->basetoolbox->get_user_ip());

        // Check if resubmit delay is enabled
        $resubmit_delay_days = $this->options['contact__settings']['resubmit_delay_days'];
        $resubmit_delay_field = $this->options['contact__settings']['resubmit_delay_field'];
        if ($resubmit_delay_days > 0 && isset($post_array[$resubmit_delay_field])) {
            $submit_messages = get_posts(array(
                'post_type' => wpucontactforms_savepost__get_post_type(),
                'tax_query' => array(
                    array(
                        'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
                        'field' => 'slug',
                        'terms' => array($this->options['id'])
                    )
                ),
                'date_query' => array(
                    array(
                        'after' => date('Y-m-d H:i:s', strtotime('-' . intval($resubmit_delay_days) . ' days'))
                    )
                ),
                'meta_query' => array(
                    'key' => $resubmit_delay_field,
                    'compare' => '=',
                    'value' => $post_array[$resubmit_delay_field]
                ),
                'posts_per_page' => 1
            ));

            if ($submit_messages && count($submit_messages) > 0) {
                $error_txt = __('You already submitted this form recently. Please wait before submitting it again.', 'wpucontactforms');
                $this->msg_errors[] = apply_filters('wpucontactforms__resubmit_delay_error_txt', $error_txt, $submit_messages[0]);
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
                    $tmp_value = trim(htmlentities(wp_strip_all_tags($post[$id])));
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

        $plugin_dir = __DIR__ . '/tools';

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

        if (!$file['tmp_name']) {
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
        /* Load form data from the posted values */
        $args['custom__form_data'] = wpucontactforms_get_form_data($_POST);
        $this->options['custom__form_data'] = $args['custom__form_data'];
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
    public function page_content__export() {
        $form_id = 'wpucontactforms_export';
        $form_args = $this->basetoolbox->get_clean_form_args($form_id);
        $terms = get_terms(array(
            'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
            'hide_empty' => true
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            $terms_array = array(
                '0' => __('All forms', 'wpucontactforms')
            );
            foreach ($terms as $term) {
                $t_value = $term->name;
                if ($term->count) {
                    $t_value .= ' (' . $term->count . ')';
                }
                $terms_array[$term->slug] = $t_value;
            }
            $field_name = 'term';
            $field = $this->basetoolbox->get_clean_field($field_name, array(
                'label' => __('Choose a form:', 'wpucontactforms') . '<br />',
                'type' => 'select',
                'data' => $terms_array
            ), $form_id, $form_args);
            echo $this->basetoolbox->get_field_html($field_name, $field, $form_id, $form_args);

        } else {
            echo __('No messages to export yet.', 'wpucontactforms');
            return;
        }

        $locales = $this->page_action__export__get_locales();
        if ($locales && count($locales) > 1) {
            $locales_array = array(
                '0' => __('All languages', 'wpucontactforms')
            );
            foreach ($locales as $locale) {
                $t_value = $locale->lang_name;
                if ($locale->lang_count) {
                    $t_value .= ' (' . $locale->lang_count . ')';
                }
                $locales_array[$locale->lang_name] = $t_value;
            }
            $field_name = 'lang';
            $field = $this->basetoolbox->get_clean_field($field_name, array(
                'label' => __('Choose a language:', 'wpucontactforms') . '<br />',
                'type' => 'select',
                'data' => $locales_array
            ), $form_id, $form_args);
            echo $this->basetoolbox->get_field_html($field_name, $field, $form_id, $form_args);
        }

        $field_name = 'wpucontactforms_export_from';
        $field = $this->basetoolbox->get_clean_field($field_name, array(
            'label' => __('From:', 'wpucontactforms') . '<br />'
        ), $form_id, $form_args);
        echo $this->basetoolbox->get_field_html($field_name, $field, $form_id, $form_args);

        $field_name = 'wpucontactforms_export_to';
        $field = $this->basetoolbox->get_clean_field($field_name, array(
            'label' => __('To:', 'wpucontactforms') . '<br />'
        ), $form_id, $form_args);
        echo $this->basetoolbox->get_field_html($field_name, $field, $form_id, $form_args);

        $field_name = 'format';
        $field = $this->basetoolbox->get_clean_field($field_name, array(
            'label' => __('Format:', 'wpucontactforms') . '<br />',
            'type' => 'select',
            'data' => array(
                'csv' => 'CSV (Excel)',
                'json' => 'JSON'
            )
        ), $form_id, $form_args);
        echo $this->basetoolbox->get_field_html($field_name, $field, $form_id, $form_args);

        submit_button(__('Export', 'wpucontactforms'));
    }

    public function page_action__export__get_locales() {
        global $wpdb;
        $q = $wpdb->prepare("SELECT pm.meta_value as lang_name, COUNT(pm.meta_value) AS lang_count FROM $wpdb->posts AS p RIGHT JOIN $wpdb->postmeta as pm ON p.ID = pm.post_id WHERE p.post_type=%s AND pm.meta_key = 'contact_locale' GROUP BY pm.meta_value", wpucontactforms_savepost__get_post_type());
        $locales = $wpdb->get_results($q);
        if (!is_array($locales)) {
            $locales = array();
        }
        return $locales;
    }

    public function page_action__export() {
        $this->trigger_export($_POST);
    }

    public function trigger_export($posted_values) {

        $file_name = 'all-forms';

        $term = '';
        if (isset($posted_values['term']) && term_exists($posted_values['term'], wpucontactforms_savepost__get_taxonomy())) {
            $term = $posted_values['term'];
            $file_name = $term;
        }

        $lang = '';
        if (isset($posted_values['lang']) && $posted_values['lang']) {
            $locales = $this->page_action__export__get_locales();
            $has_locale = false;
            foreach ($locales as $locale) {
                if ($locale->lang_name == $posted_values['lang']) {
                    $lang = $posted_values['lang'];
                    $file_name .= '-' . $lang;
                    break;
                }
            }

        }

        global $wpucontactforms_forms;

        $data = array();

        $args = array(
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_type' => wpucontactforms_savepost__get_post_type()
        );

        /* TAX */
        if ($term) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => wpucontactforms_savepost__get_taxonomy(),
                    'field' => 'slug',
                    'terms' => $term
                )
            );
        }

        /* Lang */
        if ($lang) {
            $args['meta_query'] = array(array(
                'key' => 'contact_locale',
                'value' => $lang,
                'compare' => '='
            ));
        }

        /* DATE */
        $date_fields = array(
            'wpucontactforms_export_from' => 'after',
            'wpucontactforms_export_to' => 'before'
        );
        foreach ($date_fields as $post_id => $date_type) {
            if (!isset($posted_values[$post_id]) || !$this->is_date_valid($posted_values[$post_id])) {
                continue;
            }
            if (!isset($args['date_query'])) {
                $args['date_query'] = array(
                    'inclusive' => true
                );
            }
            $date_parts = explode('-', $posted_values[$post_id]);
            $file_name .= '-' . $date_type . str_replace('-', '', $posted_values[$post_id]);
            $args['date_query'][$date_type] = array(
                'year' => intval($date_parts[0], 10),
                'month' => intval($date_parts[1], 10),
                'day' => intval($date_parts[2], 10)
            );
        }

        /* EXPORT */
        global $wpdb;
        @ini_set('max_execution_time', 0);
        $posts = get_posts($args);
        foreach ($posts as $p_id) {
            $p = $wpdb->get_row($wpdb->prepare("SELECT post_title,post_date FROM $wpdb->posts WHERE ID=%d", $p_id));
            $item = array(
                'name' => $p->post_title,
                'date' => $p->post_date
            );
            $q = $wpdb->prepare("SELECT meta_key,meta_value FROM $wpdb->postmeta WHERE post_id = %d", $p_id) . " AND meta_key NOT LIKE '\_%'";
            $meta = $wpdb->get_results($q, ARRAY_A);
            foreach ($meta as $meta_k => $meta_item) {
                $meta_k = $meta_item['meta_key'];
                /* Remove private metas */
                if ($meta_k[0] == '_') {
                    continue;
                }
                $meta_value = $meta_item['meta_value'];
                $meta_item = array($meta_item['meta_value']);
                if (is_array($wpucontactforms_forms)) {
                    foreach ($wpucontactforms_forms as $form_id => $form_fields) {
                        if (isset($form_fields['fields'][$meta_k])) {
                            $field_item = $form_fields['fields'][$meta_k];
                            $field_item['value'] = $meta_item;
                            $meta_value = html_entity_decode(wpucontactform__set_html_field_content($field_item, false));
                        }
                    }
                }
                $item[$meta_k] = $meta_value;
            }
            $data[] = $item;
        }
        $file_name = 'export-' . $file_name . '-' . date_i18n('Ymd-His');

        $format = 'csv';
        if (isset($posted_values['wpucontactforms_export_format'])) {
            $format = $posted_values['wpucontactforms_export_format'];
        }
        switch ($format) {
        case 'json':
            $this->basetoolbox->export_array_to_json($data, $file_name);
            break;
        default:
            $this->basetoolbox->export_array_to_csv($data, $file_name);
        }

        $this->wpubasemessages->set_message('wpucontactforms_export_no_msg', __('No messages match this request.', 'wpucontactforms'), 'error');

    }

    /* ----------------------------------------------------------
      Cron autodelete
    ---------------------------------------------------------- */

    public function wpucontactforms__callback_cron() {
        if (!is_array($this->user_options)) {
            $this->user_options = array();
        }
        if (isset($this->user_options['autodelete_enabled'], $this->user_options['autodelete_duration']) && $this->user_options['autodelete_enabled'] == '1' && ctype_digit($this->user_options['autodelete_duration'])) {
            $nb_months = $this->user_options['autodelete_duration'];
            $args = array(
                'posts_per_page' => apply_filters('wpucontactforms__autodelete__posts_per_batch', 30),
                'post_type' => wpucontactforms_savepost__get_post_type(),
                'orderby' => 'date',
                'order' => 'ASC',
                'fields' => 'ids',
                'date_query' => array(
                    'before' => date('Y-m-d', strtotime('-' . $nb_months . ' months'))
                )
            );
            $posts = get_posts($args);
            foreach ($posts as $p) {
                wp_trash_post($p);
            }
        }
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

    public function is_date_valid($date, $format = 'Y-m-d') {
        $dt = DateTime::createFromFormat($format, $date);
        return $dt && $dt->format($format) === $date;
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

    public function wpucontactforms_submit_contactform__webhook() {
        if (!isset($this->user_options['webhook_url']) || !filter_var($this->user_options['webhook_url'], FILTER_VALIDATE_URL)) {
            return;
        }
        if (!isset($_POST['form_id']) || $_POST['form_id'] != $this->options['id']) {
            return;
        }
        $message = '';
        foreach ($this->contact_fields as $id => $field) {
            if ($field['type'] == 'html' || $field['type'] == 'file') {
                continue;
            }
            $message .= wpucontactform__set_html_field_content($field) . "\n";
        }

        /* Get initial message */
        $message .= wpucontactform__set_html_extra_content($this);

        /* Basic readability */
        $message = html_entity_decode($message);
        $message = str_replace('<br />', "\n", $message);
        $message = str_replace(array('<strong>', '</strong>'), '*', $message);
        $message = str_replace(array('<em>', '</em>'), '_', $message);
        $message = wp_strip_all_tags($message);

        $payload = apply_filters('wpucontactforms_submit_contactform__webhook__payload', array(
            'body' => json_encode(array(
                'text' => $message
            ))
        ), $this);

        /* Send message */
        wp_remote_post($this->user_options['webhook_url'], $payload);
    }
}

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

function wpucontactform__set_html_extra_content($form) {
    $html = "\n";

    if ($form->form_submitted_page_url && apply_filters('wpucontactform__set_html_extra_content__form_submitted_page_url', true)) {
        $html .= '<strong>' . __('URL:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_page_url) . '<br />';
    }
    if ($form->form_submitted_page_title && apply_filters('wpucontactform__set_html_extra_content__form_submitted_page_title', true)) {
        $html .= '<strong>' . __('Page title:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_page_title) . '<br />';
    }
    if ($form->form_submitted_ip && apply_filters('wpucontactform__set_html_extra_content__form_submitted_ip', true)) {
        $html .= '<strong>' . __('IP:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_ip) . '<br />';
    }
    if ($form->form_submitted_hashed_ip && apply_filters('wpucontactform__set_html_extra_content__form_submitted_hashed_ip', true)) {
        $html .= '<strong>' . __('Hashed IP:', 'wpucontactforms') . '</strong> ' . esc_html($form->form_submitted_hashed_ip) . '<br />';
    }
    return $html;
}

function wpucontactform__set_html_field_content($field, $wrap_html = true) {
    $field_content = $field['value'];

    $implode_sep = ($wrap_html ? '<br />' : ', ');

    if (is_array($field_content)) {
        $field_content = implode($field_content);
    }

    /* Better presentation for text */
    if ($wrap_html && $field['type'] == 'textarea') {
        $field_content = nl2br($field_content);
    }

    $is_select_multiple = ($field['type'] == 'select' && isset($field['multiple']) && $field['multiple']);
    if (($field['type'] == 'checkbox-list' || $is_select_multiple) && is_array($field['value'])) {
        $field_parts = array();

        foreach ($field['value'] as $val_item) {
            if (isset($field['datas'][$val_item])) {
                $field_parts[] = $field['datas'][$val_item];
            } else {
                $val_item_uns = unserialize($val_item);
                if ($val_item_uns) {
                    $val_item = implode(', ', $val_item_uns);
                }
                $field_parts[] = wp_strip_all_tags($val_item);
            }
        }
        $field_content = implode(', ', $field_parts);
    }

    $wpucontactforms__upload_protection_disabled = apply_filters('wpucontactforms__upload_protection_disabled', false);

    if ($field['type'] == 'file' && is_array($field['value'])) {
        $field_content_parts = array();
        foreach ($field['value'] as $field_value) {
            if (!is_numeric($field_value)) {
                $field_value_uns = unserialize($field_value);
                if ($field_value_uns && is_array($field_value_uns) && count($field_value_uns) == 1) {
                    $field_value = implode($field_value_uns);
                }
            }
            if ($wrap_html) {
                if (wp_attachment_is_image($field_value) && $wpucontactforms__upload_protection_disabled) {
                    $field_content_parts[] = wp_get_attachment_image($field_value);
                } else {
                    $field_content_parts[] = __('See attached file(s)', 'wpucontactforms');
                    $field_content_parts[] = '<a href="' . wp_get_attachment_url($field_value) . '">' . __('Or click here', 'wpucontactforms') . '</a>';
                }
            } else {
                $field_content_parts[] = wp_get_attachment_url($field_value);
            }
        }
        $field_content = implode($implode_sep, $field_content_parts);
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
  Helper name
---------------------------------------------------------- */

function wpucontactforms_get_from_name($form_contact_fields) {
    $from_name = '';
    if (isset($form_contact_fields['contact_firstname'])) {
        $from_name = $form_contact_fields['contact_firstname']['value'];
    }
    if (isset($form_contact_fields['contact_name'])) {
        $from_name .= ' ' . $form_contact_fields['contact_name']['value'];
    }
    return trim(wp_strip_all_tags(html_entity_decode($from_name)));
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
    $from_name = wpucontactforms_get_from_name($form_contact_fields);
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

    $sendmail_subject = wp_strip_all_tags(html_entity_decode($sendmail_subject));
    $sendmail_subject = apply_filters('wpucontactforms__sendmail_subject', $sendmail_subject, $form, $from_name);

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
    if (function_exists('wputh_sendmail') && apply_filters('wpucontactforms_submit_contactform__sendmail__use_wputh_sendmail', true)) {
        wputh_sendmail($target_email, $sendmail_subject, $mail_content, $more);
    } else {
        $headers[] = 'Content-Type: text/html';

        ob_start();
        require_once __DIR__ . '/tools/mail-header.php';
        $mail_content_header = ob_get_clean();
        ob_start();
        require_once __DIR__ . '/tools/mail-footer.php';
        $mail_content_footer = ob_get_clean();

        wp_mail($target_email, $sendmail_subject, $mail_content_header . $mail_content . $mail_content_footer, $headers, $more['attachments']);
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
    $pt = wpucontactforms_savepost__get_post_type();
    $post_types[$pt] = $pt;
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

    /* Remove taxonomy link in sidebar */
    if (apply_filters('wpucontactforms_savepost__remove_taxonomy_submenu', false)) {
        add_action('admin_menu', function () {
            remove_submenu_page('edit.php?post_type=' . wpucontactforms_savepost__get_post_type(), 'edit-tags.php?taxonomy=' . wpucontactforms_savepost__get_taxonomy() . '&amp;post_type=' . wpucontactforms_savepost__get_post_type());
        });
    }

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

        if (isset($field['posted_value']) && $field['meta_value_saved'] == 'posted') {
            $post_metas[$id] = $field['posted_value'];
        }
    }

    $default_post_title = __('New message', 'wpucontactforms');
    $from_name = wpucontactforms_get_from_name($form->contact_fields);
    if (!empty($from_name)) {
        $default_post_title = sprintf(__('New message from %s', 'wpucontactforms'), $from_name);
    }

    $post_content .= wpucontactform__set_html_extra_content($form);

    $post_content = apply_filters('wpucontactforms_submit_contactform__savepost__post_content', $post_content, $form);

    // Create post
    $post_id = wp_insert_post(array(
        'post_title' => apply_filters('wpucontactforms__createpost_post_title', $default_post_title, $form, $from_name),
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
    update_post_meta($post_id, 'contact_locale', get_locale());

    foreach ($form->allowed_url_params as $param) {
        if (isset($_POST[$param])) {
            update_post_meta($post_id, 'form_url_param_' . $param, $_POST[$param]);
        }
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

    do_action('wpucontactforms_submit_contactform__savepost__after_all', $wpucontactforms_last_post_id, $form);
}

add_action('before_delete_post', 'wpucontactforms_submit_contactform__delete_attachments');
function wpucontactforms_submit_contactform__delete_attachments($post_id) {
    if (get_post_type($post_id) != wpucontactforms_savepost__get_post_type()) {
        return;
    }
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $post_id
    ));

    foreach ($attachments as $attachment) {
        wp_delete_attachment($attachment->ID, true);
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
    $opt_value = array_filter($opt_value);
    $data = array();
    foreach ($opt_value as $i => $opt_data) {
        if ($opt_data) {
            $data['k' . $i] = $opt_data;
        }
    }
    return $data;
}

/* Override params via ACF
-------------------------- */

function wpucontactforms_filter_field($field, $field_id, $data) {
    if (!isset($field['wpucontactforms_params'])) {
        return $field;
    }
    foreach ($field['wpucontactforms_params'] as $param) {
        if ($param == 'hide' && isset($data['field_hide_' . $field_id]) && $data['field_hide_' . $field_id] == 1) {
            return false;
        }
        if ($param == 'required' && isset($data['field_required_' . $field_id])) {
            $field['required'] = $data['field_required_' . $field_id];
        }
        if ($param == 'label' && isset($data['field_label_' . $field_id]) && $data['field_label_' . $field_id]) {
            $field['label'] = wp_strip_all_tags($data['field_label_' . $field_id]);
        }
        if ($param == 'help' && isset($data['field_help_' . $field_id])) {
            $field['help'] = nl2br(wp_strip_all_tags($data['field_help_' . $field_id]));
        }
        if ($param == 'layout' && isset($data['field_layout_' . $field_id]) && $data['field_layout_' . $field_id] != 'default') {
            $field['box_class'] = 'layout--' . $data['field_layout_' . $field_id];
        }
        if ($param == 'data' && isset($data['field_data_' . $field_id])) {
            $field_datas = wpucontactforms_get_select_data_from_opt($data['field_data_' . $field_id]);
            if ($field_datas) {
                $field['datas'] = $field_datas;
            }
        }
    }
    return $field;
}

function wpucontactforms_filter_fields($fields, $data) {
    foreach ($fields as $key => $field) {
        $fields[$key] = wpucontactforms_filter_field($field, $key, $data);
        if ($fields[$key] === false) {
            unset($fields[$key]);
            continue;
        }
    }
    return $fields;
}

/* Get form data
-------------------------- */

function wpucontactforms_get_form_data($post) {
    if (!isset($post['wpucontactforms_form_data'], $post['wpucontactforms_form_data_hash'])) {
        return false;
    }
    /* Test if json fails because of a misplaced stripslashes */
    if (!json_decode($post['wpucontactforms_form_data'])) {
        $post['wpucontactforms_form_data'] = json_encode(json_decode(stripslashes($post['wpucontactforms_form_data'])));
    }
    if ($post['wpucontactforms_form_data_hash'] != md5($post['wpucontactforms_form_data'] . DB_PASSWORD)) {
        return false;
    }
    $data = json_decode($post['wpucontactforms_form_data'], true);
    return wpucontactforms_get_safe_form_data($data);
}

function wpucontactforms_get_safe_form_data($post_array_checked) {
    if (!is_array($post_array_checked) || !isset($post_array_checked['post_id'], $post_array_checked['acf_row_id'])) {
        return false;
    }
    $data_flexible_id = apply_filters('wpucontactforms_get_form_data__flexible_id', 'content-blocks');
    $data_raw = get_fields($post_array_checked['post_id']);
    $row_id = intval($post_array_checked['acf_row_id'], 10) - 1;
    if (!$data_raw || !is_array($data_raw) || !isset($data_raw[$data_flexible_id], $data_raw[$data_flexible_id][$row_id])) {
        return false;
    }
    return $data_raw[$data_flexible_id][$row_id];
}

/* WP-CLI action
-------------------------- */

require_once __DIR__ . '/inc/wp-cli-migrate.php';
require_once __DIR__ . '/inc/wp-cli-export.php';
