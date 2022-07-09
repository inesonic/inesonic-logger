<?php
/***********************************************************************************************************************
 * Copyright 2021, Inesonic, LLC
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along with this program; if not, write to
 * the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************************************************************************
 */

namespace Inesonic\Logger;
    /**
     * Trivial class that provides an API to plug-in specific options.
     */
    class Options {
        /**
         * Static method that is triggered when the plug-in is activated.
         */
        public function plugin_activated() {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         */
        public function plugin_deactivated() {}

        /**
         * Static method that is triggered when the plug-in is uninstalled.
         */
        public function plugin_uninstalled() {
            $this->delete_option('version');
            $this->delete_option('apache_log_path');
            $this->delete_option('apache_errors_path');
        }

        /**
         * Constructor
         *
         * \param $options_prefix The options prefix to apply to plug-in specific options.
         */
        public function __construct(string $options_prefix) {
            $this->options_prefix = $options_prefix . '_';
        }

        /**
         * Method you can use to obtain the current plugin version.
         *
         * \return Returns the current plugin version.  Returns null if the version has not been set.
         */
        public function version() {
            return $this->get_option('version', null);
        }

        /**
         * Method you can use to set the current plugin version.
         *
         * \param $version The desired plugin version.
         *
         * \return Returns true on success.  Returns false on error.
         */
        public function set_version(string $version) {
            return $this->update_option('version', $version);
        }

        /**
         * Method you can use to obtain the Apache log file path.
         *
         * \return Returns the path to the Apache log file.  Returns null if the path has not been set.
         */
        public function apache_log_path() {
            return $this->get_option('apache_log_path', null);
        }

        /**
         * Method you can use to update the Apache log path.
         *
         * \param[in] $new_path The new Apache log file path.
         */
        public function set_apache_log_path(string $new_path) {
            $this->update_option('apache_log_path', $new_path);
        }

        /**
         * Method you can use to obtain the Apache errors file path.
         *
         * \return Returns the path to the Apache errors file.  Returns null if the path has not been set.
         */
        public function apache_errors_path() {
            return $this->get_option('apache_errors_path', null);
        }

        /**
         * Method you can use to update the Apache errors path.
         *
         * \param[in] $new_path The new Apache errors file path.
         */
        public function set_apache_errors_path(string $new_path) {
            $this->update_option('apache_errors_path', $new_path);
        }

        /**
         * Method you can use to determine if user activity should be tracked.
         *
         * \return Returns true if user activity should be tracked.  Returns false if user activity should not be
         *         tracked.
         */
        public function track_user_activity() {
            $result = $this->get_option('track_user_activity', "false");
            return $result == 'true';
        }

        /**
         * Method you can use to specify whether user activity should be tracked.
         *
         * \param[in] $now_track_user_activity If true, then user activity will be tracked.  If false, then user
         *                                     activity will not be tracked.
         */
        public function set_track_user_activity(bool $now_track_user_activity) {
            $this->update_option('track_user_activity', $now_track_user_activity ? 'true' : 'false');
        }

        /**
         * Method you can use to obtain a specific option.  This function is a thin wrapper on the WordPress get_option
         * function.
         *
         * \param $option  The name of the option of interest.
         *
         * \param $default The default value.
         *
         * \return Returns the option content.  A value of false is returned if the option value has not been set and
         *         the default value is not provided.
         */
        private function get_option(string $option, $default = false) {
            return \get_option($this->options_prefix . $option, $default);
        }

        /**
         * Method you can use to add a specific option.  This function is a thin wrapper on the WordPress update_option
         * function.
         *
         * \param $option The name of the option of interest.
         *
         * \param $value  The value to assign to the option.  The value must be serializable or scalar.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function update_option(string $option, $value = '') {
            return \update_option($this->options_prefix . $option, $value);
        }

        /**
         * Method you can use to delete a specific option.  This function is a thin wrapper on the WordPress
         * delete_option function.
         *
         * \param $option The name of the option of interest.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function delete_option(string $option) {
            return \delete_option($this->options_prefix . $option);
        }
    }
