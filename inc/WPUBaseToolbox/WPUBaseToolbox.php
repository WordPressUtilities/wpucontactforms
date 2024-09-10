<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Toolbox
Description: Cool helpers for WordPress Plugins
Version: 0.16.2
Class URI: https://github.com/WordPressUtilities/wpubaseplugin
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') || die;

class WPUBaseToolbox {
    private $plugin_version = '0.16.2';
    private $args = array();
    private $missing_plugins = array();
    private $default_module_args = array(
        'need_form_js' => true,
        'plugin_name' => 'WPU Base Toolbox'
    );

    public function __construct($args = array()) {
        if (!is_array($args)) {
            $args = array();
        }
        $this->args = array_merge($this->default_module_args, $args);

        add_action('wp_enqueue_scripts', array(&$this,
            'form_scripts'
        ));
    }

    public function form_scripts() {
        if ($this->args['need_form_js']) {
            wp_enqueue_script(__NAMESPACE__ . '-wpubasetoolbox-form-validation', plugins_url('assets/form-validation.js', __FILE__), array(), $this->plugin_version);
        }
    }

    /* ----------------------------------------------------------
      Forms
    ---------------------------------------------------------- */

    /* Wrapper
    -------------------------- */

    public function get_form_html($form_id, $fields = array(), $args = array()) {
        $html = '';
        if (!is_array($fields) || !is_array($args)) {
            return '';
        }

        $args = $this->get_clean_form_args($form_id, $fields, $args);

        $extra_post_attributes = $args['form_attributes'];

        /* Clean & check fields */
        $has_file = false;
        foreach ($fields as $field_name => $field) {
            $fields[$field_name] = $this->get_clean_field($field_name, $field, $form_id, $args);
            if ($fields[$field_name]['type'] == 'file') {
                $has_file = true;
            }
        }

        /* Extra attributes */
        if ($has_file) {
            $extra_post_attributes .= ' enctype="multipart/form-data"';
        }

        $button_submit = '<button class="' . esc_attr($args['button_classname']) . '" type="submit"><span>' . $args['button_label'] . '</span></button>';

        /* Start form */
        if ($args['form_element']) {
            $html .= '<form class="' . esc_attr($args['form_classname']) . ' wpubasetoolbox-form" id="' . esc_attr($form_id) . '" ' . ($args['wizard_mode'] ? ' data-wizard="1"' : '') . ' action="" method="post" ' . $extra_post_attributes . '>';
        }
        $html .= $args['html_before_content'];

        $html_fieldset = '';
        $html_wizard = '';

        /* Insert fields */
        $nb_fieldsets = count($args['fieldsets']);
        $fieldset_num = 0;
        foreach ($args['fieldsets'] as $fieldset_id => $fieldset) {
            $fieldset_num++;

            if ($args['wizard_steps'] && (!isset($fieldset['label']) || !$fieldset['label'])) {
                $fieldset['label'] = $fieldset_id;
            }

            $attributes = array_merge($fieldset['attributes'], array(
                'data-fielset-id' => $fieldset_id
            ));

            $html_fieldset .= '<fieldset ' . $this->array_to_html_attributes($attributes) . '>';
            $html_fieldset .= $fieldset['content_before'];
            if (isset($fieldset['label']) && $fieldset['label']) {
                $html_fieldset .= '<legend>' . esc_html($fieldset['label']) . '</legend>';

                if ($args['wizard_steps']) {
                    $html_wizard .= '<button type="button" data-active="' . ($fieldset_num == 1 ? '1' : '0') . '" data-go="' . ($fieldset_num - 1) . '"><span>' . esc_html($fieldset['label']) . '</span></button>';
                }
            }
            foreach ($fields as $field_name => $field) {
                if ($field['fieldset'] != $fieldset_id) {
                    continue;
                }
                $html_fieldset .= $this->get_field_html($field_name, $field, $form_id, $args);
            }
            $html_fieldset .= $fieldset['content_after'];
            if ($args['wizard_mode']) {
                $btn_prev_class = isset($fieldset['wizard_prev_button_class']) && $fieldset['wizard_prev_button_class'] ? $fieldset['wizard_prev_button_class'] : $args['wizard_prev_button_class'];
                $btn_next_class = isset($fieldset['wizard_next_button_class']) && $fieldset['wizard_next_button_class'] ? $fieldset['wizard_next_button_class'] : $args['wizard_next_button_class'];
                $btn_prev_label = isset($fieldset['wizard_prev_button_label']) && $fieldset['wizard_prev_button_label'] ? $fieldset['wizard_prev_button_label'] : $args['wizard_prev_button_label'];
                $btn_next_label = isset($fieldset['wizard_next_button_label']) && $fieldset['wizard_next_button_label'] ? $fieldset['wizard_next_button_label'] : $args['wizard_next_button_label'];

                $html_fieldset .= '<div class="form-navigation">';
                if ($fieldset_num > 1) {
                    $html_fieldset .= '<button data-dir="prev" class="' . $btn_prev_class . '" type="button"><span>' . $btn_prev_label . '</span></button>';
                }
                if ($fieldset_num == $nb_fieldsets) {
                    $html_fieldset .= $button_submit;
                } else {
                    $html_fieldset .= '<button data-dir="next" class="' . $btn_next_class . '" type="button"><span>' . $btn_next_label . '</span></button>';
                }
                $html_fieldset .= '</div>';
            }
            $html_fieldset .= '</fieldset>';
        }

        if ($html_wizard) {
            $html .= '<div class="form-wizard-steps">';
            $html .= $html_wizard;
            $html .= '</div>';
        }
        $html .= $html_fieldset;

        /* Submit box */
        $html .= '<div class="' . esc_attr($args['submit_box_classname']) . '">';
        foreach ($args['hidden_fields'] as $field_id => $field_value) {
            $html .= '<input type="hidden" name="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '" />';
        }
        if ($args['has_nonce']) {
            $html .= wp_nonce_field($args['nonce_id'], $args['nonce_name'], 0, 0);
        }
        if (!$args['wizard_mode']) {
            $html .= $button_submit;
        }
        $html .= '</div>';

        $html .= $args['html_after_content'];

        /* End form */
        if ($args['form_element']) {
            $html .= '</form>';
        }
        return $html;
    }

    /* Clean form value
    -------------------------- */

    public function get_clean_form_args($form_id, $fields = array(), $args = array()) {
        $default_args = array(
            'button_label' => __('Submit', __NAMESPACE__),
            'button_classname' => 'cssc-button',
            'fieldsets' => array(
                'default' => array(
                    'label' => '',
                    'attributes' => array(),
                    'content_before' => '',
                    'content_after' => ''
                )
            ),
            'form_attributes' => '',
            'form_classname' => 'cssc-form',
            'form_element' => true,
            'field_group_classname' => 'twoboxes',
            'field_box_classname' => 'box',
            'submit_box_classname' => 'box--submit',
            'html_before_content' => '',
            'html_after_content' => '',
            'hidden_fields' => array(),
            'has_nonce' => true,
            'nonce_id' => $form_id,
            'nonce_name' => $form_id . '_nonce',
            'wizard_mode' => false,
            'wizard_steps' => false,
            'wizard_prev_button_class' => 'btn--prev',
            'wizard_next_button_class' => 'btn--next',
            'wizard_prev_button_label' => __('Previous', __NAMESPACE__),
            'wizard_next_button_label' => __('Next', __NAMESPACE__)
        );
        $args = array_merge($default_args, $args);

        $args = apply_filters('wpubasetoolbox_get_form_html_args_' . __NAMESPACE__, $args);
        if (!is_array($args['hidden_fields']) || !isset($args['hidden_fields'])) {
            $args['hidden_fields'] = array();
        }
        if (!is_array($args['fieldsets']) || !isset($args['fieldsets'])) {
            $args['fieldsets'] = array();
        }
        foreach ($args['fieldsets'] as $fieldset_id => $fieldset) {
            $args['fieldsets'][$fieldset_id] = array_merge($default_args['fieldsets']['default'], $fieldset);
            if (!is_array($args['fieldsets'][$fieldset_id]['attributes'])) {
                $args['fieldsets'][$fieldset_id]['attributes'] = array();
            }
        }
        return $args;
    }

    /* Clean field values
    -------------------------- */

    public function get_clean_field($field_name, $field, $form_id, $args) {
        if (!is_array($field)) {
            $field = array();
        }

        if (!isset($field['fieldset']) || !array_key_exists($field['fieldset'], $args['fieldsets'])) {
            $field['fieldset'] = array_key_first($args['fieldsets']);
        }

        $default_field = array(
            'label' => $field_name,
            'type' => 'text',
            'fieldgroup_start' => false,
            'fieldgroup_end' => false,
            'html_before_fieldgroup' => '',
            'html_before_fieldgroup_inner' => '',
            'html_after_fieldgroup' => '',
            'html_after_fieldgroup_inner' => '',
            'html_before_content' => '',
            'html_after_content' => '',
            'value' => '',
            'extra_attributes' => '',
            'data_html' => '',
            'sub_fields' => array(),
            'data' => array(
                '0' => __('No'),
                '1' => __('Yes')
            ),
            'required' => false
        );
        $field = array_merge($default_field, $field);

        /* Ensure format */
        if (!is_array($field['data'])) {
            $field['data'] = array();
        }

        if (!is_array($field['sub_fields'])) {
            $field['sub_fields'] = array();
        }

        foreach ($field['sub_fields'] as $subfield_name => $sub_field) {
            $field['sub_fields'][$subfield_name] = $this->get_clean_field($subfield_name, $sub_field, $form_id, $args);
        }

        return $field;
    }

    public function get_field_html($field_name, $field, $form_id, $args = array()) {

        if (!isset($field['extra_attributes'])) {
            echo wp_debug_backtrace_summary();
            die;
        }

        /* Values */
        $field_id = strtolower($form_id . '__' . $field_name);
        $field_id_name = ' name="' . esc_attr($field_name) . '" id="' . esc_attr($field_id) . '" ' . $field['extra_attributes'];
        if ($field['required']) {
            $field_id_name .= ' required';
        }

        /* Label */
        $default_label = '<label for="' . esc_attr($field_id) . '">';
        $default_label .= $field['label'];
        if ($field['required']) {
            $default_label .= ' <em>*</em>';
        }
        $default_label .= '</label>';

        /* Content */
        $html = '';
        switch ($field['type']) {
        case 'textarea':
            $html .= $default_label;
            $html .= '<textarea ' . $field_id_name . '>' . htmlentities($field['value'] ? $field['value'] : '') . '</textarea>';
            break;

        case 'select':
            $html .= $default_label;
            $html .= '<select ' . $field_id_name . '>';
            if ($field['data_html']) {
                $html .= $field['data_html'];
            } else {
                foreach ($field['data'] as $key => $var) {
                    $html .= '<option ' . selected($key, $field['value'], false) . ' value="' . esc_attr($key) . '">' . esc_html($var) . '</option>';
                }
            }
            $html .= '</select>';
            break;

        case 'radio':
            $html .= $default_label;
            foreach ($field['data'] as $key => $var) {
                $id_field = strtolower($field_id . '___' . $key);
                $html .= '<span>';
                $html .= '<input type="radio" id="' . esc_attr($id_field) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($key) . '" ' . ($key === $field['value'] ? 'checked' : '') . ' ' . ($field['required'] ? 'required' : '') . ' />';
                $html .= '<label for="' . esc_attr($id_field) . '">' . $var . '</label>';
                $html .= '</span>';
            }
            break;

        case 'checkbox':
            $checked = $field['value'] ? ' checked="checked"' : '';
            $html .= '<input ' . $field_id_name . ' type="' . esc_attr($field['type']) . '" value="1" ' . $checked . ' />';
            $html .= $default_label;
            break;

        case 'group':
            foreach ($field['sub_fields'] as $subfield_name => $sub_field) {
                $html .= $this->get_field_html($subfield_name, $sub_field, $form_id, $args);
            }
            break;

        default:
            $html .= $default_label;
            $html .= '<input ' . $field_id_name . ' type="' . esc_attr($field['type']) . '" value="' . esc_attr($field['value']) . '" />';
        }

        if ($html) {
            $field_html = $html;
            $field_tag = ($field['type'] == 'group') ? 'div' : 'p';
            $html = '';
            $html .= $field['html_before_fieldgroup'];
            if ($field['fieldgroup_start']) {
                $html .= '<div class="' . $args['field_group_classname'] . '">';
            }
            $html .= $field['html_before_fieldgroup_inner'];
            $html .= '<' . $field_tag . ' class="' . $args['field_box_classname'] . '" data-box-name="' . $field_name . '" data-box-type="' . esc_attr($field['type']) . '">';
            $html .= $field['html_before_content'];
            $html .= $field_html;
            $html .= '<span aria-hidden="true" class="wpubasetoolbox-form-validation-message"></span>';
            $html .= $field['html_after_content'];
            $html .= '</' . $field_tag . '>';
            $html .= $field['html_after_fieldgroup_inner'];
            if ($field['fieldgroup_end']) {
                $html .= '</div>';
            }
            $html .= $field['html_after_fieldgroup'];
        }

        return $html;
    }

    /* Validate form
    -------------------------- */

    public function validate_form($source, $form_id, $fields = array(), $args = array()) {
        if (!is_array($source) || empty($source) || empty($fields)) {
            return false;
        }

        $errors = array();

        $args = $this->get_clean_form_args($form_id, $fields, $args);

        /* Check nonce */
        if ($args['has_nonce']) {
            if (!isset($_POST[$args['nonce_name']]) || !wp_verify_nonce($_POST[$args['nonce_name']], $args['nonce_id'])) {
                wp_nonce_ays('');
            }
        }

        /* Check required fields */
        foreach ($fields as $field_name => $field) {
            $field = $this->get_clean_field($field_name, $field, $form_id, $args);
            $value = isset($source[$field_name]) ? $source[$field_name] : false;

            if ($field['required'] && !isset($source[$field_name])) {
                $errors[] = sprintf(__('The field “%s” is required', __NAMESPACE__), $field['label']);
                continue;
            }

            if ($field['type'] == 'email' && $field['type'] && !is_email($value)) {
                $errors[] = sprintf(__('The field “%s” should be an email', __NAMESPACE__), $field['label']);
            }
        }

        return $errors;
    }

    /* ----------------------------------------------------------
      HTML Helpers
    ---------------------------------------------------------- */

    public function array_to_html_attributes($attributes = array()) {
        if (!is_array($attributes)) {
            return '';
        }

        $html = '';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $key . '="' . esc_attr($value) . '"';
        }

        return trim($html);
    }

    public function array_to_html_table($array, $args = array()) {

        /* Ensure array is ok */
        if (empty($array) || !is_array($array)) {
            return '';
        }

        /* Fix args */
        $default_args = array(
            'table_classname' => 'widefat',
            'htmlspecialchars_td' => true,
            'htmlspecialchars_th' => true,
            'colnames' => array()
        );
        if (!is_array($args)) {
            $args = array();
        }
        $args = array_merge($default_args, $args);

        $html = '';

        /* HEAD */
        $html .= '<thead><tr>';
        foreach ($array[0] as $key => $value) {
            $label = $key;
            if (isset($args['colnames'][$key])) {
                $label = $args['colnames'][$key];
            }
            if ($args['htmlspecialchars_th']) {
                $label = htmlspecialchars($label);
            }
            $html .= '<th>' . $label . '</th>';
        }
        $html .= '</tr></thead>';

        /* CONTENT */
        $html .= '<tbody>';
        foreach ($array as $line) {
            $html .= '<tr>';
            foreach ($line as $value) {
                if ($args['htmlspecialchars_td']) {
                    $value = htmlspecialchars($value);
                }
                $html .= '<td>' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        /* Return content */
        $html = '<table class="' . esc_attr($args['table_classname']) . '">' . $html . '</table>';
        return $html;
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    /**
     * Insert a value or key/value pair after a specific key in an array.  If key doesn't exist, value is appended
     * to the end of the array.
     * Thanks to  https://gist.github.com/wpscholar/0deadce1bbfa4adb4e4c
     *
     * @param array $array
     * @param string $key
     * @param array $new
     *
     * @return array
     */
    public function array_insert_after($array, $key, $new) {
        $keys = array_keys($array);
        $index = array_search($key, $keys);
        $pos = false === $index ? count($array) : $index + 1;

        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    }

    /* ----------------------------------------------------------
      Export
    ---------------------------------------------------------- */

    /* Ensure all lines have the same keys
    -------------------------- */

    public function export_array_clean_for_csv($data) {

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

    /* Array to JSON
    -------------------------- */

    public function export_array_to_json($array, $name) {
        if (!isset($array[0])) {
            return;
        }
        /* Correct headers */
        header('Content-type: application/json');
        header('Content-Disposition: attachment; filename=' . $name . '.json');
        header('Pragma: no-cache');

        echo json_encode($array);
        die;
    }

    /* Array to CSV
    -------------------------- */

    public function export_array_to_csv($array, $name) {
        if (!isset($array[0])) {
            return;
        }

        $array = $this->export_array_clean_for_csv($array);

        /* Correct headers */
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . $name . '.csv');
        header('Pragma: no-cache');

        $all_keys = array_keys($array[0]);

        /* Build and send CSV */
        $output = fopen("php://output", 'w');
        fputcsv($output, $all_keys);
        foreach ($array as $item) {
            fputcsv($output, $item);
        }
        fclose($output);
        die;
    }

    /* ----------------------------------------------------------
      IPs
    ---------------------------------------------------------- */

    /* Thanks to https://stackoverflow.com/a/13646735/975337 */
    public function get_user_ip($anonymized = true) {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) && filter_var($_SERVER["HTTP_CF_CONNECTING_IP"], FILTER_VALIDATE_IP)) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : '';
        $forward = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
        $remote = (isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) ? $_SERVER['REMOTE_ADDR'] : '';

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
    public function anonymize_ip($ip) {
        if ($ip = @inet_pton($ip)) {
            return inet_ntop(substr($ip, 0, strlen($ip) / 2) . str_repeat(chr(0), strlen($ip) / 2));
        }
        return '0.0.0.0';
    }

    /* ----------------------------------------------------------
      Dependencies
    ---------------------------------------------------------- */

    public function check_plugins_dependencies($plugins = array()) {
        if (!is_array($plugins) || !is_admin() || !current_user_can('activate_plugins')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        foreach ($plugins as $plugin) {

            $plugin['path'] = is_array($plugin['path']) ? $plugin['path'] : array($plugin['path']);

            // Check if plugin is active
            $has_plugin = false;

            foreach ($plugin['path'] as $plugin_path) {
                if (is_plugin_active($plugin_path) || is_plugin_active_for_network($plugin_path)) {
                    $has_plugin = true;
                }

                /* Get active must-use plugins list */
                $mu_plugins_path = array(
                    WPMU_PLUGIN_DIR,
                    WPMU_PLUGIN_DIR . '/wpu'
                );
                foreach ($mu_plugins_path as $mu_plugins_dir) {
                    if (is_dir($mu_plugins_dir) && file_exists($mu_plugins_dir . '/' . $plugin_path)) {
                        $has_plugin = true;
                        break;
                    }
                }
            }

            if (!$has_plugin) {
                $this->missing_plugins[] = $plugin;
            }
        }

        if (!empty($this->missing_plugins)) {
            add_action('admin_notices', array(&$this,
                'set_error_missing_plugins'
            ));
        }
    }

    public function set_error_missing_plugins() {

        if (!$this->missing_plugins) {
            return;
        }

        echo '<div class="error">';
        if (count($this->missing_plugins) > 1) {
            echo '<p>' . sprintf(__('The plugin <b>%s</b> depends on the following plugins. Please install and activate them:', __NAMESPACE__), $this->args['plugin_name']) . '</p><ul>';
            foreach ($this->missing_plugins as $plugin) {
                echo '<li>- ' . $this->get_missing_plugin_display_name($plugin) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . sprintf(__('The plugin <b>%s</b> depends on the <b>%s</b> plugin. Please install and activate it.', __NAMESPACE__), $this->args['plugin_name'], $this->get_missing_plugin_display_name($this->missing_plugins[0])) . '</p>';
        }
        echo '</div>';
    }

    public function get_missing_plugin_display_name($plugin) {
        $name = $plugin['name'];
        if (isset($plugin['url'])) {
            $name = '<a target="_blank" rel="noopener" href="' . $plugin['url'] . '">' . $name . '</a>';
        }
        return $name;
    }
}
