<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Update
Description: A class to handle plugin update from github
Version: 0.2.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Thanks: https://gist.github.com/danielbachhuber/7684646
*/

class WPUBaseUpdate {

    private $github_username;
    private $github_project;
    private $current_version;
    private $transient_name;
    private $transient_expiration;
    private $plugin_id;
    private $plugin_dir;

    public function __construct($github_username = false, $github_project = false, $current_version = false) {
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
        if (is_array($body_json)) {
            foreach ($body_json as $plugin_version) {

                /* Skip older versions */
                if (version_compare($plugin_version->name, $this->current_version) <= 0) {
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

                /* Fetch plugin data */
                $plugin_data = get_plugin_data($this->plugin_dir);

                if (isset($plugin_data['Author'])) {
                    $plugin_info['author'] = $plugin_data['Author'];
                }
                if (isset($plugin_data['Name'])) {
                    $plugin_info['name'] = $plugin_data['Name'];
                }
                if (isset($plugin_data['Description'])) {
                    $plugin_info['sections']['description'] = $plugin_data['Description'];
                }

                /* Get latest commit info */
                $commit_info = $this->get_latest_commit_info($plugin_version->commit->url, $plugin_version->commit->sha);
                if (is_object($commit_info) && isset($commit_info->commit->author->date)) {
                    $plugin_info['last_updated'] = $commit_info->commit->author->date;
                    $plugin_info['sections']['changelog'] = wpautop($commit_info->commit->message);
                }

                /* Future info */
                // $plugin_info['tested'] = "4.9";
                // $plugin_info['requires'] = "4.9";
                // $plugin_info['author_profile'] = 'https://profiles.wordpress.org/wordpressurl';
                // $plugin_info['banners'] = array(
                //     'low' => 'http://placehold.it/772x250',
                //     'high' => 'http://placehold.it/1544x500'
                // );

                break;
            }
        }

        return $plugin_info;
    }

    public function plugins_api($res, $action, $args) {

        if ($action !== 'plugin_information') {
            return false;
        }

        if ('github-' . $this->github_project !== $args->slug) {
            return $res;
        }

        $plugin_info = $this->get_new_plugin_info();
        if ($plugin_info !== false && is_array($plugin_info)) {
            return (object) $plugin_info;
        }

        return false;

    }

    /* Retrieve infos from github */
    private function get_plugin_update_info() {
        if (false === ($plugin_update_body = get_transient($this->transient_name))) {
            $plugin_update_body = wp_remote_retrieve_body(wp_remote_get('https://api.github.com/repos/' . $this->github_path . '/tags'));
            set_transient($this->transient_name, $plugin_update_body, $this->transient_expiration);
        }

        return json_decode($plugin_update_body);
    }

    private function get_latest_commit_info($commit, $sha) {
        $transient_id = 'wpuimporttwitter_commit_' . $sha;
        if (false === ($commit_info = get_transient($transient_id))) {
            $commit_info = wp_remote_retrieve_body(wp_remote_get($commit));
            set_transient($transient_id, $commit_info, $this->transient_expiration);
        }
        return json_decode($commit_info);
    }

    public function upgrader_post_install($true, $hook_extra, $result) {
        /* Only for this plugin */
        $base_plugin_name = $this->github_username . '-' . $this->github_project;
        if (substr($result['destination_name'], 0, strlen($base_plugin_name)) === $base_plugin_name) {
            $new_destination = $result['local_destination'] . '/' . $this->github_project . '/';
            rename($result['destination'], $new_destination);
            $result['destination'] = $new_destination;
            $result['remote_destination'] = $new_destination;
            $result['destination_name'] = $this->github_project;
        }
        return $result;
    }
}
