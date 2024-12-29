<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Update
Description: A class to handle plugin update from github
Version: 0.6.0
Class URI: https://github.com/WordPressUtilities/wpubaseplugin
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
Thanks: https://gist.github.com/danielbachhuber/7684646
*/

defined('ABSPATH') || die;

class WPUBaseUpdate {

    public $current_version;
    private $github_username;
    private $github_project;
    private $github_path;
    private $transient_name;
    private $transient_expiration;
    private $plugin_id;
    private $plugin_dir;
    private $details;
    private $is_tracked = false;

    public function __construct($github_username = false, $github_project = false, $current_version = false, $details = array()) {
        $this->init($github_username, $github_project, $current_version, $details);
    }

    public function init($github_username = false, $github_project = false, $current_version = false, $details = array()) {
        if (!$github_username || !$github_project || !$current_version) {
            return;
        }

        /* Settings */
        $this->github_username = $github_username;
        $this->github_project = $github_project;
        $this->current_version = $current_version;
        $this->github_path = $this->github_username . '/' . $this->github_project;
        $this->transient_name = strtolower($this->github_username . '_' . $this->github_project . '_info_plugin_update');
        $this->transient_expiration = HOUR_IN_SECONDS;
        $this->plugin_id = $this->github_project . '/' . $this->github_project . '.php';
        $this->plugin_dir = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins') . '/' . $this->plugin_id;

        $gitpath = dirname($this->plugin_dir) . '/.git';
        $this->is_tracked = (is_dir($gitpath) || file_exists($gitpath));

        if (!is_array($details)) {
            $details = array();
        }

        $this->details = $details;

        /* Hook on plugin update */
        add_filter('site_transient_update_plugins', array($this,
            'filter_update_plugins'
        ));
        add_filter('transient_update_plugins', array($this,
            'filter_update_plugins'
        ));
        add_filter('upgrader_post_install', array($this,
            'upgrader_post_install'
        ), 10, 3);
        add_filter('plugins_api', array(&$this,
            'plugins_api'
        ), 20, 3);

    }

    public function filter_update_plugins($update_plugins) {

        if (!is_object($update_plugins)) {
            return $update_plugins;
        }

        if (!isset($update_plugins->response) || !is_array($update_plugins->response)) {
            $update_plugins->response = array();
        }

        $plugin_info = $this->get_new_plugin_info();
        if ($plugin_info !== false && is_array($plugin_info)) {
            $update_plugins->response[$this->plugin_id] = (object) $plugin_info;
        }

        return $update_plugins;
    }

    public function get_new_plugin_info() {
        $plugin_info = false;
        $body_json = $this->get_plugin_update_info();
        if (!is_array($body_json)) {
            return false;
        }

        foreach ($body_json as $plugin_version) {
            /* Skip older versions */
            if (version_compare($plugin_version->name, $this->current_version) <= 0) {
                continue;
            }

            if (is_array($plugin_info)) {
                /* Update only changelog if other plugin info have been filled by a previous commit */
                $plugin_info['sections']['changelog'] .= $this->get_commit_info($plugin_version->commit->url, $plugin_version->commit->sha);
                continue;
            }

            /* Add plugin details */
            $plugin_info = array(
                'name' => $this->github_project,
                'slug' => 'github-' . $this->github_project,
                'version' => $plugin_version->name,
                'new_version' => $plugin_version->name,
                'plugin' => $this->plugin_id,
                'destination_name' => $this->github_project,
                'url' => 'https://github.com/' . $this->github_path,
                'trunk' => $plugin_version->zipball_url,
                'download_link' => $plugin_version->zipball_url,
                'package' => $plugin_version->zipball_url,
                'sections' => array()
            );

            /* Disable download link if plugin is tracked */
            if ($this->is_tracked) {
                $plugin_info['trunk'] = '';
                $plugin_info['download_link'] = '';
                $plugin_info['package'] = '';
            }

            /* Fetch plugin data */
            $plugin_data = array();
            if (file_exists($this->plugin_dir)) {
                if (!function_exists('get_plugin_data')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $plugin_data = get_plugin_data($this->plugin_dir);
            }

            if (isset($plugin_data['Author'])) {
                $plugin_info['author'] = $plugin_data['Author'];
            }
            if (isset($plugin_data['Name'])) {
                $plugin_info['name'] = $plugin_data['Name'];
            }
            if (isset($plugin_data['Description'])) {
                $plugin_info['sections']['description'] = $plugin_data['Description'];
            }

            $plugin_info['sections']['changelog'] = $this->get_commit_info($plugin_version->commit->url, $plugin_version->commit->sha);

            $_plugin_vars = array('tested', 'requires', 'homepage', 'donate_link', 'author_profile');
            foreach ($_plugin_vars as $_plugin_var) {
                if (isset($this->details[$_plugin_var]) && $this->details[$_plugin_var]) {
                    $plugin_info[$_plugin_var] = $this->details[$_plugin_var];
                }
            }

            /* Future info */
            // $plugin_info['banners'] = array(
            //     'low' => 'http://placehold.it/772x250',
            //     'high' => 'http://placehold.it/1544x500'
            // );

        }

        return $plugin_info;
    }

    public function plugins_api($res, $action, $args) {

        if ($action !== 'plugin_information') {
            return false;
        }

        if ('github-' . $this->github_project !== $args->slug && $this->github_project !== $args->slug) {
            return $res;
        }

        $plugin_info = $this->get_new_plugin_info();
        if ($plugin_info !== false && is_array($plugin_info)) {
            return (object) $plugin_info;
        }

        $plugin_details = array(
            'name' => $this->github_project,
            'slug' => $this->github_project
        );

        $parent_plugin = __DIR__ . '/../../' . $this->github_project . '.php';
        if (file_exists($parent_plugin)) {
            $plugin_name = get_plugin_data($parent_plugin);
            if (isset($plugin_name['Name'])) {
                $plugin_details['name'] = $plugin_name['Name'];
            }
        }

        return (object) $plugin_details;

    }

    /* Retrieve tag infos from github */
    public function get_plugin_update_info() {
        if (false === ($plugin_update_body = get_transient($this->transient_name))) {
            $plugin_update_body = wp_remote_retrieve_body(wp_remote_get('https://api.github.com/repos/' . $this->github_path . '/tags'));
            set_transient($this->transient_name, $plugin_update_body, $this->transient_expiration);
        }
        return json_decode($plugin_update_body);
    }

    /* Retrieve commit infos from github */
    public function get_plugin_commits_info() {
        $transient_id = $this->transient_name . '_commits';
        $url = 'https://api.github.com/repos/' . $this->github_path . '/commits';
        if (false === ($plugin_update_body = get_transient($transient_id))) {
            $plugin_update_body = wp_remote_retrieve_body(wp_remote_get($url));
            set_transient($transient_id, $plugin_update_body, $this->transient_expiration);
        }
        return json_decode($plugin_update_body);
    }

    private function get_commit_info($commit, $sha) {

        $commit_info = false;
        /* Try to obtain commit info from global commit infos */
        $commits = $this->get_plugin_commits_info();
        if (is_array($commits)) {
            foreach ($commits as $_commit) {
                if ($_commit->sha != $sha) {
                    continue;
                }
                return $this->get_nice_commit_diff($_commit);
            }
        }

        /* No luck : obtain infos from Commit API */
        $transient_id = $this->github_project . '_commit_info_' . $sha . $this->current_version;
        if (!$commit_info && false === ($commit_info = get_transient($transient_id))) {
            $commit_info = wp_remote_retrieve_body(wp_remote_get($commit));
            set_transient($transient_id, $commit_info, YEAR_IN_SECONDS);
        }
        return $this->get_nice_commit_diff(json_decode($commit_info));
    }

    private function get_nice_commit_diff($commit_info) {
        $info = '';
        if (is_object($commit_info) && isset($commit_info->commit->author->date)) {
            $commit_time = strtotime($commit_info->commit->author->date);
            $info .= '<p>';
            $info .= '<strong>' . date_i18n('Y-m-d H:i', $commit_time) . '</strong> / ';
            $info .= nl2br($commit_info->commit->message);
            $info .= '</p>';
        }
        return $info;
    }

    public function upgrader_post_install($true, $hook_extra, $result) {
        /* Only for this plugin */
        $base_plugin_name = $this->github_username . '-' . $this->github_project;
        if (substr($result['destination_name'], 0, strlen($base_plugin_name)) === $base_plugin_name) {
            $new_destination = $result['local_destination'] . '/' . $this->github_project . '/';

            /* Move files to the correct destination */
            rename($result['destination'], $new_destination);

            /* Set result vars */
            $result['destination'] = $new_destination;
            $result['remote_destination'] = $new_destination;
            $result['destination_name'] = $this->github_project;
        }
        return $result;
    }
}
