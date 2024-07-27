WPU Base Toolbox
---

Cool helpers for WordPress Plugins.

## Insert in the INIT hook

```php
require_once __DIR__ . '/inc/WPUBaseToolbox/WPUBaseToolbox.php';
$this->basetoolbox = new \myplugin\WPUBaseToolbox(array(
    'need_form_js' => false
));
```

## Use functions


### Get form HTML

```php
echo $this->basetoolbox->get_form_html('form_edit_note', array(
    'note_name' => array(
        'label' => __('Note Name', 'myplugin'),
        'value' => 'base value'
    ),
    'note_content' => array(
        'label' => __('Note Content', 'myplugin'),
        'type' => 'textarea'
    )
), array(
    'button_label' => __('Send modifications', 'myplugin'),
    'hidden_fields' => array(
        'method' => 'edit_note',
        'action' => 'myplugin_callback_crud'
    )
));
```

### Get user IP

```php
$anonymized = false;
echo $this->basetoolbox->get_user_ip($anonymized);
```

### Handle plugin dependencies

```php
$this->basetoolbox->check_plugins_dependencies(array(
    'wpuoptions' => array(
        'path' => 'wpuoptions/wpuoptions.php',
        'url' => 'https://github.com/WordPressUtilities/wpuoptions',
        'name' => 'WPU Options'
    ),
    'wputaxometas' => array(
        'path' => 'wputaxometas/wputaxometas.php',
        'url' => 'https://github.com/WordPressUtilities/wputaxometas',
        'name' => 'WPU Taxo Metas'
    )
));
```

### Array helpers

#### Get a value from an array

```php

$data = array(
    'key1' => 'value1',
    'key2' => 'value2'
);

# Get HTML attributes from an array
echo $this->basetoolbox->array_to_html_attributes($data);

# Create an HTML table from an array
echo $this->basetoolbox->array_to_html_table($data);

# Export an array as a JSON file
$this->basetoolbox->export_array_to_json($data, 'filename');

# Export an array as a CSV file
$this->basetoolbox->export_array_to_csv($data, 'filename');
```
