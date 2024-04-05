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
