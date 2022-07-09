<?php
 /**********************************************************************************************************************
 * Copyright 2021, Inesonic, LLC
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
 */

namespace Inesonic\Logger;
    require_once dirname(__FILE__) . '/helpers.php';
    require_once dirname(__FILE__) . '/options.php';
    require_once dirname(__FILE__) . '/log.php';
    require_once dirname(__FILE__) . '/file.php';

    /**
     * Class that manages the plug-in admin panel menus.
     */
    class Menus {
        /**
         * Static method that is triggered when the plug-in is activated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_activated(Options $options) {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_deactivated(Options $options) {}

        /**
         * Constructor
         *
         * \param[in] $short_plugin_name  A short version of the plug-in name to be used in the menus.
         *
         * \param[in] $plugin_name        The user visible name for this plug-in.
         *
         * \param[in] $plugin_slug        The slug used for the plug-in.  We use this slug as a prefix for slugs this
         *                                class may also require.
         *
         * \param[in] $apache_log_file    The Apache log file instance.
         *
         * \param[in] $apache_errors_file The Apache log file instance.
         */
        public function __construct(
                string $short_plugin_name,
                string $plugin_name,
                string $plugin_slug,
                File   $apache_log_file,
                File   $apache_errors_file
            ) {
            $this->short_plugin_name = $short_plugin_name;
            $this->plugin_name = $plugin_name;
            $this->plugin_slug = $plugin_slug;
            $this->apache_log_file = $apache_log_file;
            $this->apache_errors_file = $apache_errors_file;

            $this->plugin_prefix = str_replace('-', '_', $plugin_slug);

            add_action('init', array($this, 'on_initialization'));
        }

        /**
         * Method that is triggered during initialization to bolt the plug-in settings UI into WordPress.
         */
        public function on_initialization() {
            add_action('admin_menu', array($this, 'add_menu'));
            add_action('wp_ajax_inesonic_logger_read' , array($this, 'read_logs'));
            add_action('wp_ajax_inesonic_logger_purge' , array($this, 'purge_logs'));
        }

        /**
         * Method that adds the menu to the dashboard.
         */
        public function add_menu() {
            add_menu_page(
                $this->plugin_name,
                $this->short_plugin_name,
                'manage_options',
                $this->plugin_prefix,
                array($this, 'build_page'),
                plugin_dir_url(__FILE__) . 'assets/img/menu_icon.png',
                100
            );
        }

        /**
         * Method that adds scripts and styles to the admin page.
         */
        public function enqueue_scripts() {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'moment',
                \Inesonic\Logger\javascript_url('moment')
            );
            wp_enqueue_script(
                'inesonic-logger',
                \Inesonic\Logger\javascript_url('inesonic-logger'),
                array('jquery', 'moment'),
                null,
                true
            );
            wp_localize_script(
                'inesonic-logger',
                'ajax_object',
                array('ajax_url' => admin_url('admin-ajax.php'))
            );

            wp_enqueue_style(
                'inesonic-logger-styles',
                \Inesonic\Logger\css_url('inesonic-logger-styles'),
                array(),
                null
            );
        }

        /**
         * Method that renders the site monitoring page.
         */
        public function build_page() {
            if (current_user_can('manage_options')) {
                $this->enqueue_scripts();

                echo '<div class="inesonic-logger-area">
                        <div class="inesonic-logger-page-title"><h1 class="inesonic-logger-header">' .
                          esc_html(__("Logs", 'inesonic-logger' )) . '
                        </h1></div>
                        <div class="inesonic-logger-controls">
                          <label class="inesonic-logger-checkbox-label">
                            <input type="checkbox"
                                   class="inesonic-logger-checkbox"
                                   id="inesonic-logger-apache-logs-checkbox"
                            />
                            <span class="inesonic-logger-apache-log-color">' .
                              __("Apache Logs", 'inesonic-logger') .'
                            </span>
                          </label>
                          <label class="inesonic-logger-checkbox-label">
                            <input type="checkbox"
                                   class="inesonic-logger-checkbox"
                                   id="inesonic-logger-apache-errors-checkbox"
                            />
                            <span class="inesonic-logger-apache-error-color">' .
                              __("Apache Error Logs", 'inesonic-logger') .'
                            </span>
                          </label>
                          <label class="inesonic-logger-checkbox-label">
                            <input type="checkbox"
                                   class="inesonic-logger-checkbox"
                                   id="inesonic-logger-internal-logs-checkbox"
                            />
                            <span class="inesonic-logger-internal-color">' .
                              __("Internal Logs", 'inesonic-logger') .'
                            </span>
                          </label>
                          <label class="inesonic-logger-refresh-interval-label">
                            <select class="inesonic-logger-refresh-interval-select"
                                    id="inesonic-logger-refresh-interval-select"
                            >
                              <option value="1000">Update Every Second</option>
                              <option value="2000">Update Every 2 Seconds</option>
                              <option value="5000">Update Every 5 Seconds</option>
                              <option value="10000">Update Every 10 Seconds</option>
                              <option value="30000" selected="selected">Update Twice/Minute</option>
                              <option value="60000">Update Once Per Minute</option>
                              <option value="300000">Update Every 5 Minutes</option>
                              <option value="600000">Update Every 10 Minutes</option>
                            </select>
                          </label>
                          <a id="inesonic-logger-purge-internal-logs"
                             class="button action inesonic-logger-button-anchor"
                          >' .
                            __("Purge Internal Logs", 'inesonic-logger') . '
                          </a>
                        </div>
                        <div class="inesonic-logger-table-area">
                          <table class="inesonic-logger-table">
                            <thead class="inesonic-logger-table-header">
                              <tr class="inesonic-logger-table-header-row">
                                <td class="inesonic-logger-table-header-data-datetime">' .
                                  __("Date/Time", 'inesonic-logger') . '
                                </td>
                                <td class="inesonic-logger-table-header-data-ipaddress">' .
                                  __("IP Address", 'inesonic-logger') . '
                                </td>
                                <td class="inesonic-logger-table-header-data-user-id">' .
                                  __("User ID", 'inesonic-logger') . '
                                </td>
                                <td class="inesonic-logger-table-header-data-content">' .
                                  __("Content", 'inesonic-logger') . '
                                </td>
                              </tr>
                            <tbody id="inesonic-logger-table-body">
                              <tr class="inesonic-logger-table-row">
                                <td class="inesonic-logger-table-data-datetime">?</td>
                                <td class="inesonic-logger-table-data-ipaddress">?</td>
                                <td class="inesonic-logger-table-data-user-id">?</td>
                                <td class="inesonic-logger-table-data-content">?</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>';
            } else {
                echo '<div class="inesonic-logger-area">
                        <div class="inesonic-logger-page-title"><h1 class="inesonic-logger-header">' .
                          esc_html(__("Logs", 'inesonic-logger' )) . '
                        </h1></div>
                        <p>You do not have permissions to view logs.</p>
                      </div>';
            }
        }

        /**
         * Method that is triggered by AJAX to read one or more logs.
         */
        public function read_logs() {
            if (current_user_can('manage_options')                  &&
                array_key_exists('apache_log', $_POST)              &&
                array_key_exists('apache_error_log', $_POST)        &&
                array_key_exists('internal_log', $_POST)            &&
                array_key_exists('apache_log_offset', $_POST)       &&
                array_key_exists('apache_error_log_offset', $_POST) &&
                array_key_exists('internal_log_index', $_POST)      &&
                array_key_exists('internal_log_user', $_POST)          ) {
                $apache_log = sanitize_text_field($_POST['apache_log']);
                $apache_error_log = sanitize_text_field($_POST['apache_error_log']);
                $internal_log = sanitize_text_field($_POST['internal_log']);
                $apache_log_offset = sanitize_text_field($_POST['apache_log_offset']);
                $apache_error_log_offset = sanitize_text_field($_POST['apache_error_log_offset']);
                $internal_log_index = sanitize_text_field($_POST['internal_log_index']);
                $internal_log_user = sanitize_text_field($_POST['internal_log_user']);

                try {
                    $apache_log_offset = intval($apache_log_offset);
                    $apache_error_log_offset = intval($apache_error_log_offset);
                    $internal_log_index = intval($internal_log_index);
                    $internal_log_user = intval($internal_log_user);
                } catch (Exception $e) {
                    $apache_log_offset = -1;
                    $apache_error_log_offset = -1;
                    $internal_log_index = -1;
                    $internal_log_user = -1;
                }

                if ($apache_log_offset >= 0       &&
                    $apache_error_log_offset >= 0 &&
                    $internal_log_index >= 0      &&
                    $internal_log_user >= 0          ) {
                    $response = array();
                    if ($apache_log == 'true') {
                        $response['apache_log'] = $this->apache_log_file->get_entries($apache_log_offset);
                    } else {
                        $response['apache_log'] = array(
                            'starting_offset' => $apache_log_offset,
                            'ending_offset' => 0,
                            'content' => array()
                        );
                    }

                    if ($apache_error_log == 'true') {
                        $response['apache_error_log'] = $this->apache_errors_file->get_entries(
                            $apache_error_log_offset
                        );
                    } else {
                        $response['apache_error_log'] = array(
                            'starting_offset' => $apache_log_offset,
                            'ending_offset' => 0,
                            'content' => array()
                        );
                    }

                    if ($internal_log == 'true') {
                        $log_data = Log::get_entries($internal_log_index, $internal_log_user);
                        $internal_log = array();
                        foreach ($log_data as $log_entry) {
                            $internal_log[] = array(
                                $log_entry->event_timestamp,
                                $log_entry->event_ip_address,
                                $log_entry->event_user_id,
                                $log_entry->event_content,
                                $log_entry->event_id
                            );
                        }

                        $response['internal_log'] = $internal_log;
                    } else {
                        $response['internal_log'] = array();
                    }

                    $response['status'] = 'OK';
                } else {
                    $response = array('status' => 'failed');
                }
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }

        /**
         * Method that is triggered by AJAX to purge our internal logs.
         */
        public function purge_logs() {
            if (current_user_can('manage_options')) {
                Log::purge_old_entries();
                $response = array('status' => 'OK');
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }
    };
