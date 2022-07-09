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
    require_once dirname(__FILE__) . '/file.php';

    /**
     * Class that manages options displayed within the WordPress Plugins page.
     */
    class PlugInsPage {
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
         * \param[in] $plugin_basename    The base name for the plug-in.
         *
         * \param[in] $plugin_name        The user visible name for this plug-in.
         *
         * \param[in] $options            The plug-in options API.
         *
         * \param[in] $apache_log_file    The Apache log file instance.
         *
         * \param[in] $apache_errors_file The Apache log file instance.
         */
        public function __construct(
                string  $plugin_basename,
                string  $plugin_name,
                Options $options,
                File    $apache_log_file,
                File    $apache_errors_file
            ) {
            $this->plugin_basename = $plugin_basename;
            $this->plugin_name = $plugin_name;
            $this->options = $options;
            $this->apache_log_file = $apache_log_file;
            $this->apache_errors_file = $apache_errors_file;

            add_action('init', array($this, 'on_initialization'));
        }

        /**
         * Method that is triggered during initialization to bolt the plug-in settings UI into WordPress.
         */
        public function on_initialization() {
            add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_plugin_page_links'));
            add_action(
                'after_plugin_row_' . $this->plugin_basename,
                array($this, 'add_plugin_configuration_fields'),
                10,
                3
            );
            add_action('wp_ajax_inesonic_logger_get_configuration' , array($this, 'get_configuration'));
            add_action('wp_ajax_inesonic_logger_update_configuration' , array($this, 'update_configuration'));
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_page_links(array $links) {
            $configuration = "<a href=\"###\" id=\"inesonic-logger-configure-link\">" .
                               __("Configure", 'inesonic-logger') .
                             "</a>";
            array_unshift($links, $configuration);

            return $links;
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_configuration_fields(string $plugin_file, array $plugin_data, string $status) {
            echo '<tr id="inesonic-logger-configuration-area-row"
                      class="inesonic-logger-configuration-area-row inesonic-row-hidden">
                    <th></th> .
                    <td class="inesonic-logger-configuration-area-column" colspan="2">
                      <table><tbody>
                        <tr>
                          <td>' . __("Full path to the Apache log file:", 'inesonic-logger') . '</td>
                          <td>
                            <input type="text"
                                   class="inesonic-logger-input"
                                   id="inesonic-logger-apache-log-input"/>
                          </td>
                        </tr>
                        <tr>
                          <td>' . __("Full path to the Apache errors file:", 'inesonic-logger') . '</td>
                          <td>
                            <input type="text"
                                   class="inesonic-logger-input"
                                   id="inesonic-logger-apache-errors-input"/>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <label>
                              <input type="checkbox"
                                     class="inesonic-logger-input"
                                     id="inesonic-logger-track-user-activity"/>' .
                              __("&nbsp;Track user activity", 'inesonic-logger') . '                                                                                    </label>
                          </td>
                          <td>
                            <div class="inesonic-logger-button-wrapper">
                              <a id="inesonic-logger-configure-submit-button"
                                 class="button action inesonic-logger-button-anchor"
                              >' .
                                __("Submit", 'inesonic-logger') . '
                              </a>
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>';

            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'inesonic-logger-plugins-page',
                \Inesonic\Logger\javascript_url('plugins-page'),
                array('jquery'),
                null,
                true
            );
            wp_localize_script(
                'inesonic-logger-plugins-page',
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
         * Method that is triggered to get the configuration.
         */
        public function get_configuration() {
            if (current_user_can('activate_plugins')) {
                $response = array(
                    'status' => 'OK',
                    'apache_log_path' => $this->options->apache_log_path(),
                    'apache_errors_path' => $this->options->apache_errors_path(),
                    'track_user_activity' => $this->options->track_user_activity()
                );
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }

        /**
         * Method that is triggered to update the configuration.
         */
        public function update_configuration() {
            if (current_user_can('activate_plugins')            &&
                array_key_exists('apache_log_path', $_POST)     &&
                array_key_exists('apache_errors_path', $_POST)  &&
                array_key_exists('track_user_activity', $_POST)    ) {
                $apache_log_path = sanitize_text_field($_POST['apache_log_path']);
                $apache_errors_path = sanitize_text_field($_POST['apache_errors_path']);
                $track_user_activity = sanitize_text_field($_POST['track_user_activity']);

                if ($track_user_activity === true || $track_user_activity === 'true') {
                    $track_user_activity = true;
                } else {
                    $track_user_activity = false;
                }

                $this->options->set_apache_log_path($apache_log_path);
                $this->options->set_apache_errors_path($apache_errors_path);
                $this->options->set_track_user_activity($track_user_activity);

                $success = $this->apache_log_file->set_path($apache_log_path);
                if ($success) {
                    $success = $this->apache_errors_file->set_path($apache_errors_path);
                    if ($success) {
                        $response = array('status' => 'OK');
                    } else {
                        $response = array('status' => 'failed to open errors file: ' . $apache_errors_path);
                    }
                } else {
                    $response = array('status' => 'failed to open log file: ' . $apache_log_path);
                }
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }
    };
