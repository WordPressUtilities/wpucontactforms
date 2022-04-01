WPU Base Update
---

Update your Github WordPress plugin from the plugins page admin.

## Insert in the INIT hook

```php
include dirname( __FILE__ ) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
$this->settings_update = new \PLUGINID\WPUBaseUpdate(
    'WordPressUtilities',
    'PLUGINID',
    $this->plugin_version);
```

Please ensure that this code is available before when !is_admin() ( for API purposes )
