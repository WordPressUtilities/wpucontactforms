WPU Base Cron
---

Add Cron to your plugin.

## Insert in the plugins_loaded hook

```php
require_once __DIR__ . '/inc/WPUBaseCron/WPUBaseCron.php';
$this->basecron = new \wpubaseplugin\WPUBaseCron(array(
    'pluginname' => 'Base Plugin', // Default : [Namespace]
    'cronhook' => 'wpubaseplugin__cron_hook', // Default : [namespace__cron_hook]
    'croninterval' => 900 // Default : [3600]
));
/* Callback when hook is triggered by the cron */
add_action('wpubaseplugin__cron_hook', array(&$this,
    'wpubaseplugin__callback_function'
), 10);
```

## uninstall hook ##

```php
$this->basecron->uninstall();
```
