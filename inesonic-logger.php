<?php
/**
 * Plugin Name:       Inesonic Logger
 * Description:       A small logger tool for WordPress.
 * Version:           1.6
 * Author:            Inesonic,  LLC
 * Author URI:        https://inesonic.com
 * License:           GPLv3
 * License URI:
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Text Domain:       inesonic-logger
 * Domain Path:       /locale
 ***********************************************************************************************************************
 * Inesonic Logger - A small log viewer for WordPress.
 *
 * Copyright 2021-2022, Inesonic, LLC
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with this program.  If not, see
 * <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 * \file inesonic-logger.php
 *
 * Main plug-in file.
 */

require_once __DIR__ . "/include/options.php";
require_once __DIR__ . "/include/plugin-page.php";
require_once __DIR__ . "/include/menus.php";
require_once __DIR__ . "/include/log.php";
require_once __DIR__ . "/include/file.php";

/**
 * Main class for the Logger plugin.  This class does the work needed to instantiate the plug-in within the larger
 * WordPress application.  Note that this file contains the only content not containing within the Inesonic\Logger
 * namespace.
 */
class InesonicLogger {
    /**
     * Plug-in version.
     */
    const VERSION = '1.0';

    /**
     * Plug-in slug.
     */
    const SLUG = 'inesonic-logger';

    /**
     * Plug-in descriptive name.
     */
    const NAME = 'Inesonic Logger';

    /**
     * Shorter plug-in descriptive name.
     */
    const SHORT_NAME = 'Log Viewer';

    /**
     * Plug-in author.
     */
    const AUTHOR = 'Inesonic, LLC';

    /**
     * Plug-in prefix.
     */
    const PREFIX = 'InesonicLogger';

    /**
     * Options prefix.
     */
    const OPTIONS_PREFIX = 'inesonic_logger';

    /**
     * The minimum supported PHP version.
     */
    const MINIMUM_PHP_VERSION = '7.4';

    /**
     * The maximum supported PHP version.
     */
    const MAXIMUM_PHP_VERSION = '8.0.14';

    /**
     * The minimum supported WordPress version.
     */
    const MINIMUM_WORDPRESS_VERSION = '5.7';

    /**
     * The maximum supported WordPress version.
     */
    const MAXIMUM_WORDPRESS_VERSION = '6.0';

    /**
     * The plugin singleton instance.
     */
    private static $instance;

    /**
     * Static method we use to create a single private instance of this plug-in.
     *
     * \return Returns our static plug-in instance.
     */
    public static function instance() {
        if (!isset(self::$instance) || !(self::$instance instanceof InesonicLogger)) {
            self::$instance = new InesonicLogger();
            spl_autoload_register(array(self::$instance, 'autoloader'));
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->options = new Inesonic\Logger\Options(self::OPTIONS_PREFIX);

        $this->apache_log_file = new Inesonic\Logger\File($this->options->apache_log_path());
        $this->apache_errors_file = new Inesonic\Logger\File($this->options->apache_errors_path());

        $this->admin_menus = new Inesonic\Logger\Menus(
            self::SHORT_NAME,
            self::NAME,
            self::SLUG,
            $this->apache_log_file,
            $this->apache_errors_file
        );

        $this->plugin_page = new Inesonic\Logger\PlugInsPage(
            plugin_basename(__FILE__),
            self::NAME,
            $this->options,
            $this->apache_log_file,
            $this->apache_errors_file
        );

        add_action('pre_get_posts', array($this, 'track_activity'), 10, 1);

        /**
         * Action: inesonic-logger-1
         *
         * You can use this action to add a new log entry.
         *
         * Parameters:
         *    $content - The content to be inserted into the log.
         */
        add_action('inesonic-logger-1', array($this, 'log_event'), 10, 1);
        
        /**
         * Action: inesonic-logger-2
         *
         * You can use this action to add a new log entry.
         *
         * Parameters:
         *    $content - The content to be inserted into the log.
         *    $user_id - The ID of the user to associate with the entry.
         */
        add_action('inesonic-logger-2', array($this, 'log_event'), 10, 2);
        
        /**
         * Action: inesonic-logger-3
         *
         * You can use this action to add a new log entry.
         *
         * Parameters:
         *    $content -    The content to be inserted into the log.
         *    $user_id -    The ID of the user to associate with the entry.
         *    $ip_address - The IP address to associate with the entry.
         */
        add_action('inesonic-logger-3', array($this, 'log_event'), 10, 3);
    }

    /**
     * Autoloader callback.
     *
     * \param[in] class_name The name of this class.
     */
    public function autoloader($class_name) {
        if (!class_exists($class_name) and (FALSE !== strpos($class_name, self::PREFIX))) {
            $class_name = str_replace(self::PREFIX, '', $class_name);
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';

            if (file_exists($classes_dir . $class_file)) {
                require_once $classes_dir . $class_file;
            }
        }
    }

    /**
     * Method that is triggered before WordPress gets pages or posts.
     *
     * \param[in] $query The WordPress query (ignored).
     */
    public function track_activity($query) {
        if ($this->options->track_user_activity()) {
            if (array_key_exists('REQUEST_URI', $_SERVER)) {
                $content = $_SERVER['REQUEST_URI'];
            } else {
                $content = '';
            }

            if (!str_starts_with($content, '/wp-admin/admin-ajax.php')                 &&
                !str_starts_with($content, '/wp-admin/admin.php?page=inesonic_logger') &&
                !str_starts_with($content, '/wp-cron.php')                                ) {
                $user = wp_get_current_user();
                if ($user === null || $user === false || $user->ID == 0) {
                    $user_id = 0;
                } else {
                    $user_id = $user->ID;
                }

                if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                } else {
                    $ip_address = '';
                }

                \Inesonic\Logger\Log::add_entry(
                    $content,
                    $user_id,
                    $ip_address
                );
            }
        }
    }

    /**
     * Method that is triggered to log an event.
     *
     * \param[in] $content    The content to be logged.
     *
     * \param[in] $user_id    The ID of the user that triggered this event.
     *
     * \param[in] $ip_address The IP address to log with the event.
     */
    public function log_event($content, $user_id = 0, $ip_address = '') {
        if ($user_id === null) {
            $user_id = 0;
        }

        if ($ip_address === null) {
            $ip_address = '';
        }

        \Inesonic\Logger\Log::add_entry($content, $user_id, $ip_address);
    }

    /**
     * Static method that is triggered when the plug-in is activated.
     */
    public static function plugin_activated() {
        \Inesonic\Logger\Log::plugin_activated();
    }

    /**
     * Static method that is triggered when the plug-in is deactivated.
     */
    public static function plugin_deactivated() {
        \Inesonic\Logger\Log::plugin_deactivated();
    }

    /**
     * Static method that is triggered when the plug-in is uninstalled.
     */
    public static function plugin_uninstalled() {
        \Inesonic\Logger\Log::plugin_uninstalled();
    }
}

/* Instantiate our plug-in. */
InesonicLogger::instance();

/* Define critical global hooks. */
register_activation_hook(__FILE__, array('InesonicLogger', 'plugin_activated'));
register_deactivation_hook(__FILE__, array('InesonicLogger', 'plugin_deactivated'));
