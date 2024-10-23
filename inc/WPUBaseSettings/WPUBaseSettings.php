<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Settings
Description: A class to handle native settings in WordPress admin
Version: 0.24.0
Class URI: https://github.com/WordPressUtilities/wpubaseplugin
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') || die;

class WPUBaseSettings {

    private $hook_page = false;
    private $has_media_setting = false;
    private $admin_url = false;
    private $is_admin_page = false;
    private $has_create_page = false;
    public $settings = array();
    public $settings_details = array();

    public function __construct($settings_details = array(), $settings = array()) {
        $this->init($settings_details, $settings);
    }

    public function init($settings_details = array(), $settings = array()) {
        if (empty($settings_details) || empty($settings)) {
            return;
        }
        $this->set_datas($settings_details, $settings);
        $this->has_media_setting = false;
        foreach ($this->settings as $setting) {
            if ($setting['type'] == 'media') {
                $this->has_media_setting = true;
            }
        }

        $this->is_admin_page = isset($_GET['page']) && $_GET['page'] == $this->settings_details['plugin_id'];
        $this->has_create_page = isset($settings_details['create_page']) && $settings_details['create_page'];

        $opt = $this->get_settings();
        add_action('admin_init', array(&$this,
            'add_settings'
        ));
        add_filter('option_page_capability_' . $this->settings_details['option_id'], array(&$this,
            'set_min_capability'
        ));
        add_action('admin_notices', array(&$this,
            'admin_notices'
        ));
        if ($this->has_create_page) {
            add_action('admin_menu', array(&$this,
                'admin_menu'
            ));
            $this->admin_url = admin_url($this->settings_details['parent_page_url'] . '?page=' . $this->settings_details['plugin_id']);
            if (isset($settings_details['plugin_basename'])) {
                add_filter("plugin_action_links_" . $settings_details['plugin_basename'], array(&$this, 'plugin_add_settings_link'));
            }
        } else {
            add_action('init', array(&$this, 'load_assets'));
        }
    }

    public function admin_notices() {
        if (!$this->is_admin_page) {
            return;
        }
        if ($this->settings_details['parent_page'] == 'options-general.php') {
            return;
        }
        settings_errors();
    }

    public function get_settings() {
        return $this->get_setting_values();
    }

    public function get_setting($id, $lang = false) {
        $opt = $this->get_settings();
        if ($lang === true) {
            $id = $this->get_current_language() . '__' . $id;
        }
        if (isset($opt[$id])) {
            return $opt[$id];
        }
        return false;
    }

    public function update_setting($id, $value) {
        $opt = $this->get_settings();
        $opt[$id] = $value;
        update_option($this->settings_details['option_id'], $opt);
    }

    public function set_min_capability() {
        return $this->settings_details['user_cap'];
    }

    public function set_datas($settings_details, $settings) {
        if (!is_array($settings_details)) {
            $settings_details = array();
        }
        if (!isset($settings_details['plugin_id'])) {
            $settings_details['plugin_id'] = 'wpubasesettingsdefault';
        }
        if (!isset($settings_details['user_cap'])) {
            $settings_details['user_cap'] = 'manage_options';
        }
        if (!isset($settings_details['option_id'])) {
            $settings_details['option_id'] = $settings_details['plugin_id'] . '_options';
        }
        if (!isset($settings_details['parent_page'])) {
            $settings_details['parent_page'] = 'options-general.php';
        }
        if (!isset($settings_details['parent_page_url'])) {
            $settings_details['parent_page_url'] = $settings_details['parent_page'];
        }
        if (!isset($settings_details['plugin_name'])) {
            $settings_details['plugin_name'] = $settings_details['plugin_id'];
        }
        if (!isset($settings_details['show_in_rest'])) {
            $settings_details['show_in_rest'] = false;
        }
        if (!isset($settings_details['sections']) || empty($settings_details['sections'])) {
            $settings_details['sections'] = array(
                'default' => array(
                    'name' => __('Settings')
                )
            );
        }
        foreach ($settings_details['sections'] as $id => $section) {
            if (!isset($section['user_cap'])) {
                $settings_details['sections'][$id]['user_cap'] = $settings_details['user_cap'];
            }
        }
        $this->settings_details = $settings_details;
        if (!is_array($settings)) {
            $settings = array(
                'option_example' => array(
                    'label' => 'My label',
                    'help' => 'My help',
                    'type' => 'textarea'
                )
            );
        }

        $default_section = key($this->settings_details['sections']);
        foreach ($settings as $id => $input) {
            $settings[$id]['required'] = isset($input['required']) ? $input['required'] : false;
            $settings[$id]['default_value'] = isset($input['default_value']) ? $input['default_value'] : '';
            $settings[$id]['label'] = isset($input['label']) ? $input['label'] : '';
            $settings[$id]['label_check'] = isset($input['label_check']) ? $input['label_check'] : $settings[$id]['label'];
            $settings[$id]['help'] = isset($input['help']) ? $input['help'] : '';
            $settings[$id]['type'] = isset($input['type']) ? $input['type'] : 'text';
            $settings[$id]['post_type'] = isset($input['post_type']) ? $input['post_type'] : 'post';
            $settings[$id]['section'] = isset($input['section']) ? $input['section'] : $default_section;
            $settings[$id]['datas'] = isset($input['datas']) && is_array($input['datas']) ? $input['datas'] : array(__('No'), __('Yes'));
            $settings[$id]['editor_args'] = isset($input['editor_args']) && is_array($input['editor_args']) ? $input['editor_args'] : array();
            $settings[$id]['user_cap'] = $this->settings_details['sections'][$settings[$id]['section']]['user_cap'];
        }

        $languages = $this->get_languages();

        /* Set multilingual fields */
        $new_settings = array();
        foreach ($settings as $id => $input) {
            if (!isset($input['lang']) || empty($languages)) {
                $new_settings[$id] = $input;
                continue;
            }
            foreach ($languages as $lang => $lang_name) {
                $input_lang = $input;
                unset($input_lang['lang']);
                $input_lang['translated_from'] = $id;
                $input_lang['lang_id'] = $lang;
                $input_lang['label'] = '[' . $lang . ']&nbsp;' . $input_lang['label'];
                $new_settings[$lang . '__' . $id] = $input_lang;
            }

        }

        $this->settings = $new_settings;
    }

    public function add_settings() {
        register_setting($this->settings_details['option_id'], $this->settings_details['option_id'], array(
            'sanitize_callback' => array(&$this, 'options_validate'),
            'show_in_rest' => $this->settings_details,
            'default' => array()
        ));
        $has_check_all = false;
        foreach ($this->settings_details['sections'] as $id => $section) {
            if (!current_user_can($section['user_cap'])) {
                continue;
            }
            if (!isset($section['before_section'])) {
                $section['before_section'] = '';
            }
            if (!isset($section['after_section'])) {
                $section['after_section'] = '';
            }
            if (isset($section['wpubasesettings_checkall']) && $section['wpubasesettings_checkall']) {
                $check_label = __('Check all', __NAMESPACE__);
                $section['after_section'] .= '<button class="wpubasesettings-check-all" type="button" data-check-label="' . $check_label . '" data-uncheck-label="' . __('Uncheck all', __NAMESPACE__) . '">' . $check_label . '</button>';
                if (!$has_check_all) {
                    $has_check_all = true;
                    add_action('admin_footer', array(&$this, 'admin_footer_checkall'));
                }
            }
            $section['before_section'] = '<div class="wpubasesettings-form-table-section">' . $section['before_section'];
            $section['after_section'] =  $section['after_section'] . '</div>';
            add_settings_section(
                $id,
                $section['name'],
                isset($section['description']) ? $section['description'] : '',
                $this->settings_details['plugin_id'],
                $section
            );
        }

        foreach ($this->settings as $id => $input) {
            // Hide input if not in capacity
            if (!current_user_can($input['user_cap'])) {
                continue;
            }
            $lang_id = '';
            if (isset($input['lang_id'])) {
                $lang_id = $input['lang_id'];
            }
            add_settings_field($id, $this->settings[$id]['label'], array(&$this,
                'render__field'
            ), $this->settings_details['plugin_id'], $this->settings[$id]['section'], array(
                'name' => $this->settings_details['option_id'] . '[' . $id . ']',
                'id' => $id,
                'lang_id' => $lang_id,
                'label_for' => $id,
                'readonly' => isset($this->settings[$id]['readonly']) ? $this->settings[$id]['readonly'] : false,
                'placeholder' => isset($this->settings[$id]['placeholder']) ? $this->settings[$id]['placeholder'] : false,
                'attributes_html' => isset($this->settings[$id]['attributes_html']) ? $this->settings[$id]['attributes_html'] : false,
                'translated_from' => isset($this->settings[$id]['translated_from']) ? $this->settings[$id]['translated_from'] : false,
                'required' => $this->settings[$id]['required'],
                'post_type' => $this->settings[$id]['post_type'],
                'datas' => $this->settings[$id]['datas'],
                'type' => $this->settings[$id]['type'],
                'help' => $this->settings[$id]['help'],
                'default_value' => $this->settings[$id]['default_value'],
                'editor_args' => $this->settings[$id]['editor_args'],
                'label_check' => $this->settings[$id]['label_check']
            ));
        }
    }

    public function options_validate($input) {
        $options = get_option($this->settings_details['option_id']);
        foreach ($this->settings as $id => $setting) {

            // If regex : use it to validate the field
            if (isset($setting['regex'])) {
                if (isset($input[$id]) && preg_match($setting['regex'], $input[$id])) {
                    $options[$id] = $input[$id];
                } else {
                    if (isset($setting['default'])) {
                        $options[$id] = $setting['default'];
                    }
                }
                continue;
            }

            // Set a default value
            if ($setting['type'] != 'checkbox') {
                // - if not sent or if user is not allowed
                if (!isset($input[$id]) || !current_user_can($setting['user_cap'])) {
                    $input[$id] = isset($options[$id]) ? $options[$id] : '0';
                }
                $option_id = $input[$id];
            }
            switch ($setting['type']) {
            case 'checkbox':
                $option_id = isset($input[$id]) && !in_array($input[$id], array('0', '')) ? '1' : '0';
                break;
            case 'radio':
            case 'select':
                if (!array_key_exists($input[$id], $setting['datas'])) {
                    $option_id = key($setting['datas']);
                }
                break;
            case 'email':
                if (filter_var($input[$id], FILTER_VALIDATE_EMAIL) === false) {
                    $option_id = '';
                }
                break;
            case 'url':
                if (filter_var($input[$id], FILTER_VALIDATE_URL) === false) {
                    $option_id = '';
                }
                break;
            case 'post':
            case 'page':
            case 'media':
            case 'number':
                if (!is_numeric($input[$id])) {
                    $option_id = 0;
                }
                break;
            case 'editor':
                $option_id = trim($input[$id]);
                break;
            default:
                $option_id = esc_html(trim($input[$id]));
            }

            $options[$id] = $option_id;
        }

        add_settings_error(
            $this->settings_details['option_id'],
            $this->settings_details['option_id'] . esc_attr('settings_updated'),
            __('Settings saved.'),
            'updated'
        );

        return $options;
    }

    public function render__field($args = array()) {
        $option_id = $this->settings_details['option_id'];
        $options = get_option($option_id);
        $name_val = $option_id . '[' . $args['id'] . ']';
        $name = ' name="' . $name_val . '" ';
        $id = ' id="' . $args['id'] . '" ';
        $attr = '';
        if (isset($args['readonly']) && $args['readonly']) {
            $attr .= ' readonly ';
            $name = '';
        }
        if (isset($args['lang_id']) && $args['lang_id']) {
            $attr .= ' data-wpulang="' . esc_attr($args['lang_id']) . '" ';
        }
        if (isset($args['required']) && $args['required']) {
            $attr .= ' required="required" ';
        }

        if (isset($args['placeholder']) && $args['placeholder']) {
            $attr .= ' placeholder="' . esc_attr($args['placeholder']) . '" ';
        }
        if (isset($args['attributes_html']) && $args['attributes_html']) {
            $attr .= ' ' . $args['attributes_html'];
        }
        $id .= $attr;
        $value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default_value'];
        if (!isset($options[$args['id']]) && isset($args['translated_from']) && $args['translated_from'] && isset($options[$args['translated_from']]) && $options[$args['translated_from']]) {
            $value = $options[$args['translated_from']];
        }

        switch ($args['type']) {
        case 'checkbox':
            $checked_val = isset($options[$args['id']]) ? $options[$args['id']] : '0';
            echo '<label><input type="checkbox" ' . $name . ' ' . $id . ' ' . checked($checked_val, '1', 0) . ' value="1" /> ' . $args['label_check'] . '</label>';
            break;
        case 'textarea':
            echo '<textarea ' . $name . ' ' . $id . ' cols="50" rows="5">' . esc_attr($value) . '</textarea>';
            break;
        case 'media':
            $img_src = '';
            if (is_numeric($value)) {
                $tmp_src = wp_get_attachment_image_src($value, 'medium');
                if (is_array($tmp_src)) {
                    $img_src = ' src="' . esc_attr($tmp_src[0]) . '" ';
                }
            }
            echo '<div class="wpubasesettings-mediabox">';
            echo '<input ' . $name . ' ' . $id . ' type="hidden" value="' . esc_attr($value) . '" />';
            /* Preview */
            echo '<div class="img-preview" style="' . (empty($img_src) ? 'display:none;' : '') . '">';
            echo '<a href="#" class="x">&times;</a>';
            echo '<img ' . $img_src . ' alt="" />';
            echo '</div>';
            echo '<button type="button" class="button">' . __('Upload New Media') . '</button>';
            echo '</div>';
            break;
        case 'radio':
            foreach ($args['datas'] as $_id => $_data) {
                echo '<p>';
                echo '<input id="' . $args['id'] . $_id . '" type="radio" ' . $name . ' value="' . esc_attr($_id) . '" ' . ($value == $_id ? 'checked="checked"' : '') . ' />';
                echo '<label class="wpubasesettings-radio-label" for="' . $args['id'] . $_id . '">' . $_data . '</label>';
                echo '</p>';
            }
            break;
        case 'post':
        case 'page':
            $page_dropdown_args = array(
                'echo' => false,
                'name' => $name_val,
                'id' => $args['id'],
                'selected' => $value,
                'post_type' => isset($args['post_type']) ? $args['post_type'] : $args['type']
            );

            if (isset($args['lang_id']) && $args['lang_id'] && function_exists('pll_get_post')) {
                $page_dropdown_args['lang'] = $args['lang_id'];
            }
            $code_dropdown = wp_dropdown_pages($page_dropdown_args);
            echo str_replace('<select ', '<select ' . $attr, $code_dropdown);
            break;
        case 'select':
            echo '<select ' . $name . ' ' . $id . '>';
            foreach ($args['datas'] as $_id => $_data) {
                echo '<option value="' . esc_attr($_id) . '" ' . ($value == $_id ? 'selected="selected"' : '') . '>' . $_data . '</option>';
            }
            echo '</select>';
            break;
        case 'editor':
            $editor_args = array(
                'textarea_rows' => isset($args['textarea_rows']) && is_numeric($args['textarea_rows']) ? $args['textarea_rows'] : 3
            );
            if (isset($args['editor_args']) && is_array($args['editor_args'])) {
                $editor_args = $args['editor_args'];
            }
            $editor_args['textarea_name'] = $name_val;
            wp_editor($value, $option_id . '_' . $args['id'], $editor_args);
            echo '<span ' . $attr . '></span>';
            break;
        case 'url':
        case 'number':
        case 'email':
        case 'text':
            echo '<input ' . $name . ' ' . $id . ' type="' . $args['type'] . '" value="' . esc_attr($value) . '" />';
        }
        if (!empty($args['help'])) {
            echo '<div><small>' . $args['help'] . '</small></div>';
        }
    }

    public static function isRegex($str0) {
        /* Thx https://stackoverflow.com/a/16098097 */
        $regex = "/^\/[\s\S]+\/$/";
        return preg_match($regex, $str0);
    }

    /* Media */
    public function load_assets() {
        add_action('admin_footer', array(&$this, 'admin_footer'));
        add_action('admin_head', array(&$this, 'admin_head'));

        if (!$this->has_media_setting) {
            return;
        }

        add_action('admin_print_scripts', array(&$this, 'admin_scripts'));
        add_action('admin_print_styles', array(&$this, 'admin_styles'));
        add_action('admin_footer', array(&$this, 'admin_footer_medias'));
    }

    public function admin_scripts() {
        wp_enqueue_script('media-upload');
        wp_enqueue_media();
    }

    public function admin_styles() {
        wp_enqueue_style('thickbox');
    }

    public function admin_head() {
        echo <<<EOT
<style>
.wpubasesettings-mediabox .img-preview {
    z-index: 1;
    position: relative;
}

.wpubasesettings-mediabox .img-preview img {
    max-width: 100px;
}

.wpubasesettings-mediabox .img-preview .x {
    z-index: 1;
    position: absolute;
    top: 0;
    left: 0;
    padding: 0.2em;
    text-decoration: none;
    font-weight: bold;
    line-height: 1;
    color: #000;
    background-color: #fff;
}

.wpubasesettings-form-table-section h2 {
    cursor: pointer;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
}

.wpubasesettings-form-table-section h2:after {
    content: '▼';
    display: inline-block;
    padding-left: 0.3em;
    text-align: center;
    cursor: pointer;
}

.wpubasesettings-form-table-section:not(.is-open) h2:after {
    content: '▶';
}

.wpubasesettings-form-table-section:not(.is-open) >*:not(h2) {
    visibility: hidden;
    z-index: 1;
    position: absolute;
    top: -99vh;
    left: -99vw;
    pointer-events: none;
}
</style>
EOT;
    }

    public function admin_footer_medias() {
        echo <<<EOT
<script>
(function(){
{$this->admin_footer_js_check_correct_page()}

/* Delete image */
jQuery('.wpubasesettings-mediabox .x').click(function(e) {
    var \$this = jQuery(this),
        \$parent = \$this.closest('.wpubasesettings-mediabox'),
        \$imgPreview = \$parent.find('.img-preview');
        \$imgField = \$parent.find('input[type="hidden"]');
    e.preventDefault();
    \$imgPreview.css({'display':'none'});
    \$imgField.val('');
});

/* Add image */
jQuery('.wpubasesettings-mediabox .button').click(function(e) {
    var \$this = jQuery(this),
        \$parent = \$this.closest('.wpubasesettings-mediabox'),
        \$imgPreview = \$parent.find('.img-preview');
        \$imgField = \$parent.find('input[type="hidden"]');

    var frame = wp.media({multiple: false });

    // When an image is selected in the media frame...
    frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        \$imgPreview.css({'display':'block'});
        \$imgPreview.find('img').attr('src',attachment.url);
        // Send the attachment id to our hidden input
        \$imgField.val(attachment.id);
    });

    // Finally, open the modal on click
    frame.open();

    e.preventDefault();
});
}());
</script>
EOT;
    }

    public function admin_footer_js_check_correct_page() {
        return <<<EOT
if(!jQuery('[name="option_page"][value="{$this->settings_details['option_id']}"]').length){return;}
EOT;
    }

    public function admin_footer() {
        $option_id = $this->settings_details['option_id'];
        $languages = json_encode($this->get_languages());
        $label_txt = __('Language');
        echo <<<EOT
<script>
(function(){
{$this->admin_footer_js_check_correct_page()}
/* Check langs */
var _langs = {$languages};
if(!_langs){
    return;
}

/* Get items */
var jQinput = jQuery('input[type="hidden"][name="option_page"][value="{$option_id}"]');
if(!jQinput.length){
    return;
}
var jQform = jQinput.closest('form');

/* Add toggles on titles */
jQform.find('h2').each(function(i,el){
    var jQel = jQuery(el),
        jQWrap = jQel.closest('.wpubasesettings-form-table-section');
    jQWrap.addClass('is-open');
    jQel.on('click',function(){
        jQWrap.toggleClass('is-open');
    });
});

/* Add lang on TR */
jQform.find('[data-wpulang]').each(function(i,el){
    var jQel = jQuery(el);
    jQel.closest('tr').attr('data-wpulangtr', jQel.attr('data-wpulang'));
});
var jQTr = jQform.find('[data-wpulangtr]'),
    _firstLang = Object.keys(_langs)[0];
if(!jQTr.length){
    return;
}

/* Build switch */
var select_html='';
for(var _l in _langs){
    select_html+='<option value="'+_l+'">'+_langs[_l]+'</option>';
}
var jQSelect = jQuery('<label><strong>{$label_txt}</strong> : <select>'+select_html+'</select></label>');
jQSelect.prependTo(jQform);

/* Switch */
function show_lang(_lang_id){
    jQTr.hide();
    jQTr.filter('[data-wpulangtr="'+_lang_id+'"]').show();
}
show_lang(_firstLang);
jQSelect.on('change', 'select',function(){
    show_lang(jQuery(this).val());
});

}());
</script>
EOT;
    }

    function admin_footer_checkall() {
        echo <<<EOT
<script>
jQuery(document).ready(function() {
    {$this->admin_footer_js_check_correct_page()}
    jQuery(".wpubasesettings-check-all").each(function() {
        var _btn = jQuery(this),
            _table = _btn.prev(".form-table"),
            _checkboxes = _table.find(":checkbox"),
            _check_all = true;

        _btn.on("click", function(e) {
            e.preventDefault();
            _checkboxes.prop("checked", _check_all);
            _checkboxes.trigger("change");
        });

        function check_checkboxes_mode() {
            var _checked = _checkboxes.filter(":checked").length;
            _check_all = _checked < _checkboxes.length;
            _btn.text(_check_all ? _btn.data("check-label") : _btn.data("uncheck-label"));
        }
        _checkboxes.on("change", check_checkboxes_mode);
        check_checkboxes_mode();

    });
});
</script>
EOT;
    }

    /* Base settings */

    public function admin_menu() {
        $this->hook_page = add_submenu_page($this->settings_details['parent_page'], $this->settings_details['plugin_name'] . ' - ' . __('Settings'), $this->settings_details['plugin_name'], $this->settings_details['user_cap'], $this->settings_details['plugin_id'], array(&$this,
            'admin_settings'
        ), 110);
        add_action('load-' . $this->hook_page, array(&$this, 'load_assets'));
    }

    public function plugin_add_settings_link($links) {
        $settings_link = '<a href="' . $this->admin_url . '">' . __('Settings') . '</a>';
        array_push($links, $settings_link);
        return $links;
    }

    public function admin_settings() {
        echo '<div class="wrap">';
        do_action('wpubasesettings_after_wrap_start' . $this->hook_page);
        echo apply_filters('wpubasesettings_page_title_' . $this->hook_page, '<h1>' . get_admin_page_title() . '</h1>');
        do_action('wpubasesettings_before_content_' . $this->hook_page);
        if (current_user_can($this->settings_details['user_cap'])) {
            echo apply_filters('wpubasesettings_before_form_' . $this->hook_page, '<hr />');
            echo '<form action="' . admin_url('options.php') . '" method="post">';
            settings_fields($this->settings_details['option_id']);
            do_settings_sections($this->settings_details['plugin_id']);
            echo submit_button(__('Save'));
            echo '</form>';
        }
        do_action('wpubasesettings_after_content_' . $this->hook_page);
        do_action('wpubasesettings_before_wrap_end' . $this->hook_page);
        echo '</div>';
    }

    /* Get settings */

    public function get_setting_values($lang = false) {
        if (!isset($this->settings) || !is_array($this->settings)) {
            return array();
        }
        if (!$lang) {
            $lang = $this->get_current_language();
        }
        $settings = get_option($this->settings_details['option_id']);
        if (!is_array($settings)) {
            $settings = array();
        }
        foreach ($this->settings as $key => $setting) {
            /* Default fields */
            if (!isset($settings[$key]) && !isset($setting['translated_from'])) {
                $default_value = false;
                if (isset($this->settings[$key], $this->settings[$key]['default'])) {
                    $default_value = $this->settings[$key]['default'];
                }
                $settings[$key] = $default_value;
            }
            if (isset($setting['translated_from'], $setting['lang_id'], $settings[$key]) && $lang == $setting['lang_id'] && $settings[$key] !== false) {
                $settings[$setting['translated_from']] = $settings[$key];
            }
        }
        return $settings;
    }

    public function get_languages() {
        // Obtaining from Qtranslate
        if (function_exists('qtrans_getSortedLanguages')) {
            return qtrans_getSortedLanguages();
        }

        // Obtaining from Qtranslate X
        if (function_exists('qtranxf_getSortedLanguages')) {
            return qtranxf_getSortedLanguages();
        }

        // Obtaining from Polylang
        global $polylang;
        if (function_exists('pll_the_languages') && is_object($polylang)) {
            $poly_langs = $polylang->model->get_languages_list();
            $languages = array();
            foreach ($poly_langs as $lang) {
                $languages[$lang->slug] = $lang->slug;
            }
            return $languages;
        }

        // Obtaining from WPML
        if (function_exists('icl_get_languages')) {
            $wpml_lang = icl_get_languages();
            foreach ($wpml_lang as $lang) {
                $languages[$lang['code']] = $lang['native_name'];
            }
            return $languages;
        }
        return array();

    }

    public function get_current_language() {

        // Obtaining from Qtranslate
        if (function_exists('qtrans_getLanguage')) {
            return qtrans_getLanguage();
        }

        // Obtaining from Qtranslate X
        if (function_exists('qtranxf_getLanguage')) {
            return qtranxf_getLanguage();
        }

        // Obtaining from Polylang
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }

        // Obtaining from WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        return '';
    }

}

/*
    ## INIT ##
    $this->settings_details = array(
        # Create admin page
        'create_page' => true,
        'parent_page' => 'tools.php',
        'plugin_name' => 'Maps Autocomplete',
        # Default
        'plugin_id' => 'wpuimporttwitter',
        'user_cap' => 'manage_options',
        'option_id' => 'wpuimporttwitter_options',
        'sections' => array(
            'import' => array(
                'name' => __('Import Settings', 'wpuimporttwitter')
            )
        )
    );
    $this->settings = array(
        'sources' => array(
            'label' => __('Sources', 'wpuimporttwitter'),
            'help' => __('One #hashtag or one @user per line.', 'wpuimporttwitter'),
            'type' => 'textarea'
        )
    );
    if (is_admin()) {
        include 'inc/WPUBaseSettings.php';
        $settings_obj = new \wpuimporttwitter\WPUBaseSettings($this->settings_details, $this->settings);

        ## if no auto create_page and medias ##
        if(isset($_GET['page']) && $_GET['page'] == 'wpuimporttwitter'){
            add_action('admin_init', array(&$settings_obj, 'load_assets'));
        }
    }

    ## IN ADMIN PAGE if no auto create_page ##
    echo '<form action="' . admin_url('options.php') . '" method="post">';
    settings_fields($this->settings_details['option_id']);
    do_settings_sections($this->options['plugin_id']);
    echo submit_button(__('Save Changes', 'wpuimporttwitter'));
    echo '</form>';
*/
