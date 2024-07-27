WPU Base Admin Page
---

Add an admin page in your plugin.

## Insert in the plugins_loaded hook

```php
$admin_pages = array(
    'main' => array(
        'menu_name' => 'Base plugin',
        'name' => 'Main page',
        'settings_link' => true,
        'settings_name' => 'Settings',
        'function_content' => array(&$this,
            'page_content__main'
        ),
        'function_action' => array(&$this,
            'page_action__main'
        )
    ),
    'subpage' => array(
        'parent' => 'main',
        'name' => 'Subpage page',
        'function_content' => array(&$this,
            'page_content__subpage'
        ),
        'function_action' => array(&$this,
            'page_action__subpage'
        )
    )
);

$pages_options = array(
    'id' => 'wpubaseplugin',
    'level' => 'manage_options',
    'basename' => plugin_basename(__FILE__)
);

// Init admin page
require_once __DIR__ . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
$this->adminpages = new \wpubaseplugin\WPUBaseAdminPage();
$this->adminpages->init($pages_options, $admin_pages);

```
