WPU Base Messages
---

Add notices in your plugin.

## Insert in the INIT hook

```php
if (is_admin()) {
    include dirname( __FILE__ ) . '/inc/WPUBaseMessages/WPUBaseMessages.php';
    $this->messages = new \wpubaseplugin\WPUBaseMessages($this->options['plugin_id']);
}
```
