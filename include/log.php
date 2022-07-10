<?php
/***********************************************************************************************************************
 * Copyright 2021, Inesonic, LLC
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *   
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *   
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 */

namespace Inesonic\Logger;
    /**
     * Trivial class that provides an API to the log database.
     */
    class Log {
        /**
         * Static method that is triggered when the plug-in is activated.
         */
        static public function plugin_activated() {
            if (defined('ABSPATH') && current_user_can('activate_plugins')) {
                $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
                if (check_admin_referer('activate-plugin_' . $plugin)) {
                    global $wpdb;
                    $wpdb->query(
                        'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_log' . ' (' .
                            'event_id BIGINT UNSIGNED AUTO_INCREMENT,' .
                            'event_timestamp BIGINT UNSIGNED NOT NULL,' .
                            'event_ip_address VARCHAR(40) NOT NULL,' .
                            'event_user_id BIGINT UNSIGNED NOT NULL,' .
                            'event_content TEXT NOT NULL,' .
                            'PRIMARY KEY (event_id)' .
                        ')'
                    );
                }
            }
        }

        /**
         * Static method that is triggered when the plug-in is deactivated.
         */
        static public function plugin_deactivated() {}

        /**
         * Static method that is triggered when the plug-in is uninstalled.
         */
        static public function plugin_uninstalled() {
            if (defined('ABSPATH') && current_user_can('activate_plugins')) {
                $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
                if (check_admin_referer('deactivate-plugin_' . $plugin)) {
                    global $wpdb;
                    $wpdb->query('DROP TABLE ' . $wpdb->prefix . 'inesonic_log');
                }
            }
        }

        /**
         * Static method you can use to add a new entry to the database.
         *
         * \param[in] $content    The content to be inserted.
         *
         * \param[in] $user_id    The ID of the user.  Specify 0 to indicate no user.
         *
         * \param[in] $ip_address The IP address to include in the record.  An empty string indicates no IP address.
         */
        static public function add_entry(string $content, int $user_id = 0, string $ip_address = '') {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'inesonic_log',
                array(
                    'event_timestamp' => time(),
                    'event_ip_address' => $ip_address,
                    'event_user_id' => $user_id,
                    'event_content' => $content
                ),
                array(
                    '%d',
                    '%s',
                    '%d',
                    '%s'
                )
            );
        }

        /**
         * Static method you can use to obtain all log entries at or after a specific index.  Returned entries are
         * ordered by event_id and event_user_id.
         *
         * \param[in] $starting_index The starting index for the log entry.  A value of 0 will fetch all log entries.
         *
         * \param[in] $user_id        The user ID to limit log entries to.  A value of 0 or null indicates all users.
         *
         * \return Returns an array holding all the requested log entries.  Log entries are sorted by date/time.
         */
        static public function get_entries(int $starting_index = 0, int $user_id = 0) {
            global $wpdb;

            $query = 'SELECT * FROM ' . $wpdb->prefix . 'inesonic_log';
            if ($starting_index && $user_id) {
                $query .= ' WHERE event_id=' . $starting_index . ' AND event_user_id=' . $user_id;
            } else if ($starting_index) {
                $query .= ' WHERE event_id=' . $starting_index;
            } else if ($user_id) {
                $query .= ' WHERE event_user_id=' . $user_id;
            }

            $query .= ' ORDER BY event_id,event_user_id ASC';
            return $wpdb->get_results($query);
        }

        /**
         * Method you can use to purge all log entries at or before a given index.
         *
         * \param $ending_index The ending index to purge entries for.  A value of 0 will purge all log entries.
         */
        static public function purge_old_entries(int $ending_index = 0) {
            global $wpdb;
            if ($ending_index) {
                $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'inesonic_log' . ' WHERE event_id<=' . $ending_index);
            } else {
                $wpdb->query('DELETE FROM ' . $wpdb->prefix . 'inesonic_log');
            }
        }
    }
