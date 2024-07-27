<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Admin page
Description: A class to handle pages in WordPress
Version: 1.8.0
Class URI: https://github.com/WordPressUtilities/wpubaseplugin
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') || die;

class WPUBaseAdminPage {

    public $page_hook = false;
    private $pages;
    private $options;
    private $prefix;

    private $default_options = array(
        'level' => 'manage_options',
        'network_page' => false
    );

    public function __construct() {}

    /* ----------------------------------------------------------
      Script
    ---------------------------------------------------------- */

    public function init($options, $pages) {
        if (!is_admin()) {
            return;
        }
        $this->options = $options;
        $this->pages = $pages;
        $this->prefix = $this->options['id'] . '-';
        $this->pages = $this->set_pages($this->pages);

        /* Set default options */
        if (!is_array($this->options)) {
            $this->options = array();
        }
        $this->options = array_merge($this->default_options, $this->options);

        add_action($this->options['network_page'] ? 'network_admin_menu' : 'admin_menu', array(&$this,
            'set_admin_menu'
        ));
        add_action('admin_bar_menu', array(&$this,
            'set_adminbar_menu'
        ), 100);
        add_filter("plugin_action_links_" . $this->options['basename'], array(&$this,
            'add_settings_link'
        ));
        // Only on a plugin admin page
        $current_page = $this->get_page();
        if (array_key_exists($current_page, $this->pages)) {
            add_action('admin_post_' . $this->options['id'], array(&$this,
                'set_admin_page_main_postAction'
            ));
        }
        foreach ($this->pages as $p_id => $p) {
            if ($p_id == $current_page) {
                foreach ($p['actions'] as $action) {
                    add_action($action[0], $action[1]);
                }
                foreach ($p['filters'] as $filter) {
                    add_filter($filter[0], $filter[1]);
                }
            }
            if (in_array($current_page, $p['aliases'])) {
                wp_redirect($this->get_page_url($p_id));
                die;
            }
        }
    }

    public function set_pages($pages) {
        foreach ($pages as $id => $page) {
            $page['id'] = $this->prefix . $id;
            $page['url'] = admin_url('admin.php?page=' . $page['id']);
            if (!isset($page['section'])) {
                $page['section'] = '';
            } else {
                $path = (strpos($page['section'], '?') !== false ? '&' : '?') . 'page=' . $page['id'];
                $page['url'] = admin_url($page['section'] . $path);
            }
            if (!isset($page['name'])) {
                $page['name'] = $id;
            }
            if (!isset($page['menu_name'])) {
                $page['menu_name'] = $page['name'];
            }
            if (!isset($page['settings_name'])) {
                $page['settings_name'] = $page['name'];
            }
            if (!isset($page['parent'])) {
                $page['parent'] = '';
            }
            if (!isset($page['actions'])) {
                $page['actions'] = array();
            }
            if (!isset($page['filters']) || !is_array($page['filters'])) {
                $page['filters'] = array();
            }
            if (!isset($page['aliases']) || !is_array($page['aliases'])) {
                $page['aliases'] = array();
            }
            if (!isset($page['display_banner_menu'])) {
                $page['display_banner_menu'] = false;
            }
            if (!isset($page['function_content'])) {
                $page['function_content'] = array(&$this,
                    'page_content__' . $id
                );
            }
            if (!isset($page['function_action'])) {
                $page['function_action'] = array(&$this,
                    'page_action__' . $id
                );
            }
            if (!isset($page['level'])) {
                $page['level'] = $this->options['level'];
            }
            if (!isset($page['icon_url'])) {
                $page['icon_url'] = '';
            }
            if (!isset($page['has_file'])) {
                $page['has_file'] = false;
            }
            if (!isset($page['settings_link'])) {
                $page['settings_link'] = false;
            }
            if (!isset($page['has_form'])) {
                $page['has_form'] = true;
            }
            if (!isset($page['page_help'])) {
                $page['page_help'] = false;
            }
            $pages[$id] = $page;
        }
        return $pages;
    }

    public function set_admin_menu() {
        $parent = false;
        foreach ($this->pages as $id => $page) {

            $page_id = $page['id'];
            $page_action = array(&$this,
                'set_admin_page_main'
            );
            $page_title = $page['name'];
            if (isset($page['page_title'])) {
                $page_title = $page['page_title'];
            }

            // A parent is defined
            if (array_key_exists($page['parent'], $this->pages)) {
                $this->page_hook = add_submenu_page($this->prefix . $page['parent'], $page_title, $page['menu_name'], $page['level'], $page_id, $page_action);
                // A section is defined
            } elseif (!empty($page['section'])) {
                $this->page_hook = add_submenu_page($page['section'], $page_title, $page['menu_name'], $page['level'], $page_id, $page_action);
            } else {
                // Create a parent menu page
                add_menu_page($page['name'], $page['menu_name'], $page['level'], $page_id, $page_action, $page['icon_url']);
                $this->page_hook = add_submenu_page($page_id, $page_title, $page['name'], $page['level'], $page_id, $page_action);
            }
            add_action('load-' . $this->page_hook, array(&$this, 'add_help'), 10, 1);
        }
    }

    public function add_help($arg) {

        $screen = get_current_screen();
        if (!isset($_GET['page']) || !is_object($screen) || !property_exists($screen, 'base')) {
            return;
        }
        $base_str = str_replace(array($this->prefix, 'toplevel_page_'), '', $screen->base);
        // Add help tabs
        if (isset($this->pages[$base_str]['page_help']) && !empty($this->pages[$base_str]['page_help'])) {
            $help_page = $this->pages[$base_str]['page_help'];
            // If multiple one pages are available
            if (is_array($help_page)) {
                foreach ($help_page as $id => $help_tab) {
                    $help_tab['title'] = isset($help_tab['title']) ? $help_tab['title'] : $id;
                    $help_tab['content'] = isset($help_tab['content']) ? $help_tab['content'] : $help_tab['title'];
                    $screen->add_help_tab(array(
                        'id' => 'help-' . $base_str . '-' . $id,
                        'title' => $help_tab['title'],
                        'content' => $help_tab['content']
                    ));
                }
            } else {
                // If only one tab available
                $screen->add_help_tab(array(
                    'id' => 'help-' . $base_str,
                    'title' => __('Help'),
                    'content' => $help_page
                ));
            }
        }

        // Add help sidebar
        if (isset($this->pages[$base_str]['page_help_sidebar']) && !empty($this->pages[$base_str]['page_help_sidebar'])) {
            $screen->set_help_sidebar($this->pages[$base_str]['page_help_sidebar']);
        }
    }

    public function set_adminbar_menu($admin_bar) {
        foreach ($this->pages as $id => $page) {
            if (!$page['display_banner_menu']) {
                continue;
            }
            $menu_details = array(
                'id' => $page['id'],
                'title' => $page['menu_name'],
                'href' => $page['url'],
                'meta' => array(
                    'title' => $page['menu_name']
                )
            );
            if (isset($page['parent']) && array_key_exists($page['parent'], $this->pages)) {
                $menu_details['parent'] = $this->prefix . $page['parent'];
            }
            $admin_bar->add_menu($menu_details);
        }
    }

    public function add_settings_link($links) {
        foreach ($this->pages as $id => $page) {
            if (!$page['settings_link']) {
                continue;
            }
            $settings_link = '<a href="' . $page['url'] . '">' . $page['settings_name'] . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    public function set_admin_page_main() {
        $page = $this->get_page();

        $form_classname = $this->prefix . $page . '-form';

        echo $this->get_wrapper_start();

        // Default Form
        if ($this->pages[$page]['has_form']):
            echo '<form class="' . esc_attr($form_classname) . '" action="' . admin_url('admin-post.php') . '" method="post" ' . ($this->pages[$page]['has_file'] ? ' enctype="multipart/form-data"' : '') . '><div>';
            echo '<input type="hidden" name="action" value="' . $this->options['id'] . '">';
            echo '<input type="hidden" name="page_name" value="' . $page . '" />';
            wp_nonce_field('action-main-form-' . $page, 'action-main-form-' . $this->options['id'] . '-' . $page);
        endif;
        call_user_func($this->pages[$page]['function_content']);
        if ($this->pages[$page]['has_form']):
            echo '</div></form>';
        endif;

        echo $this->get_wrapper_end();
    }

    public function set_admin_page_main_postAction() {
        $page = $this->get_page();
        $action_id = 'action-main-form-' . $this->options['id'] . '-' . $page;
        if (empty($_POST) || !isset($_POST[$action_id]) || !wp_verify_nonce($_POST[$action_id], 'action-main-form-' . $page)) {
            return;
        }
        call_user_func($this->pages[$page]['function_action']);
        wp_redirect($this->pages[$page]['url']);
    }

    private function get_wrapper_start() {
        return '<div class="wrap"><h2 class="title">' . get_admin_page_title() . '</h2><br />';
    }

    private function get_wrapper_end() {
        return '</div>';
    }

    private function get_page() {
        $page = '';
        if (isset($_GET['page'])) {
            $page = str_replace($this->options['id'] . '-', '', $_GET['page']);
        }
        if (isset($_POST['page_name'])) {
            $page = str_replace($this->options['id'] . '-', '', $_POST['page_name']);
        }
        return $page;
    }

    public function get_page_url($page_id) {
        if (!isset($this->pages[$page_id])) {
            return false;
        }
        return $this->pages[$page_id]['url'];
    }
}
