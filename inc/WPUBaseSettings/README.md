WPU Base Settings
---

Add settings in your plugin.

## Insert in the INIT hook

```php
$this->settings_details = array(
    # Admin page
    'create_page' => true,
    'plugin_basename' => plugin_basename(__FILE__),
    # Default
    'plugin_name' => 'Import Twitter',
    'plugin_id' => 'wpuimporttwitter',
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
    require_once __DIR__ . '/inc/WPUBaseSettings/WPUBaseSettings.php';
    new \wpuimporttwitter\WPUBaseSettings($this->settings_details,$this->settings);
}
```

## Insert in your admin page content ( if needed )

```php
settings_errors();
echo '<form action="' . admin_url('options.php') . '" method="post">';
settings_fields($this->settings_details['option_id']);
do_settings_sections($this->options['plugin_id']);
echo submit_button(__('Save Changes', 'wpuimporttwitter'));
echo '</form>';
``
