<?php
namespace wpucontactforms;

/*
Class Name: WPU Base Cron
Description: A class to handle crons
Version: 0.2.10
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') || die;

class WPUBaseCron {
    public $ns = '';
    public $pluginname = '';
    public $cronhook = '';
    public $croninterval = false;
    public $cronlastexec = false;
    public $cronoption = false;
    public $cronschedule = false;

    public function __construct($settings = array()) {
        $this->ns = preg_replace('/\W+/', '', __NAMESPACE__);

        /* Settings */
        $this->pluginname = isset($settings['pluginname']) ? $settings['pluginname'] : ucfirst($this->ns);
        $this->cronhook = isset($settings['cronhook']) ? $settings['cronhook'] : $this->ns . '__cron_hook';
        $this->croninterval = isset($settings['croninterval']) ? $settings['croninterval'] : 3600;

        /* Internal values */
        $this->cronoption = $this->cronhook . '_croninterval';
        $this->cronschedule = $this->cronhook . '_schedule';
        $this->cronlastexec = $this->cronhook . '_lastexec';

        /* Hooks */
        add_filter('cron_schedules', array(&$this,
            'add_schedule'
        ));
        add_action($this->cronhook, array(&$this,
            'store_execution_time'
        ), 99);

        /* Check cron */
        add_action('wp', array(&$this,
            'check_cron'
        ));
        add_action('admin_init', array(&$this,
            'check_cron'
        ));
    }

    /* Create schedule */
    public function add_schedule($schedules) {
        $schedules[$this->cronschedule] = array(
            'interval' => $this->croninterval,
            'display' => $this->pluginname . ' - Custom'
        );
        return $schedules;
    }

    /* Schedule cron if possible */
    public function check_cron() {
        $croninterval = intval(get_option($this->cronoption), 10);
        $schedule = wp_next_scheduled($this->cronhook);

        // If no schedule cron or new interval or incorrect interval
        if (!$schedule || $croninterval != $this->croninterval) {
            $this->install();
            return;
        }

        // Schedule is too in the future
        if ($schedule - time() - $croninterval > 0) {
            do_action($this->cronhook);
            $this->install();
            return;
        }

        // Schedule is too in the past
        if ($schedule - time() < $croninterval * 12 * -1) {
            do_action($this->cronhook);
            $this->install();
        }
    }

    /* ----------------------------------------------------------
      Time
    ---------------------------------------------------------- */

    /* Get time */
    public function get_time_details($schedule, $delta) {
        if (!is_numeric($schedule) || !is_numeric($delta)) {
            return false;
        }
        $minutes = 0;
        $seconds = abs($delta);
        if ($seconds >= 60) {
            $minutes = (int) ($seconds / 60);
            $seconds = $seconds % 60;
        }
        return array(
            'timestamp' => $schedule,
            'delta' => $delta,
            'min' => $minutes,
            'sec' => $seconds
        );
    }

    /* Get next scheduled */
    public function get_next_scheduled() {
        $schedule = wp_next_scheduled($this->cronhook);
        return $this->get_time_details($schedule, $schedule - time());
    }

    /* Get previous execution */
    public function get_previous_exec() {
        $schedule = get_option($this->cronlastexec);
        return $this->get_time_details($schedule, time() - $schedule);
    }

    public function store_execution_time() {
        update_option($this->cronlastexec, time());
    }

    /* ----------------------------------------------------------
      Activation
    ---------------------------------------------------------- */

    /* Create cron */
    public function install() {
        wp_clear_scheduled_hook($this->cronhook);
        update_option($this->cronoption, $this->croninterval);
        wp_schedule_event(time() + $this->croninterval, $this->cronschedule, $this->cronhook);
    }

    /* Destroy cron */
    public function uninstall() {
        wp_clear_scheduled_hook($this->cronhook);
        delete_option($this->cronlastexec);
        delete_option($this->cronoption);
        flush_rewrite_rules();
    }
}

/*
 ## plugins_loaded ##
 include 'inc/WPUBaseCron.php';
 $WPUBaseCron = new WPUBaseCron(array(
     'pluginname' => 'Base Plugin',
     'cronhook' => 'wpubaseplugin__cron_hook',
     'croninterval' => 900
 ));

 ## uninstall hook ##
 $WPUBaseCron->uninstall();
 *
 */
