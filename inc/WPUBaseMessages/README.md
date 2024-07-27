WPU Base Messages
---

Add notices in your plugin.

## Insert in the INIT hook

```php
if (is_admin()) {
    require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
    $this->messages = new \wpubaseplugin\WPUBaseMessages($this->options['plugin_id']);
}
```
