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
     * Class that provides access to a log file.
     */
    class File {
        /**
         * Constructor
         *
         * \param[in] $file_path The path to the desired file.
         */
        public function __construct(string $file_path = '') {
            if ($file_path !== '') {
                $this->file_handle = @fopen($file_path, 'r');

                if ($this->file_handle === null || $this->file_handle === false) {
                    $this->file_handle = null;
                }
            } else {
                $this->file_handle = null;
            }

            $this->file_path = $file_path;
        }

        public function __destruct() {
            if ($this->file_handle !== null) {
                fclose($this->file_handle);
            }
        }

        /**
         * Method you can use get the current file path.
         *
         * \return Returns the current file path.
         */
        public function path() {
            return $this->file_path;
        }

        /**
         * Method you can use to update the current file path.
         *
         * \param[in] $new_file_path The new file path.
         *
         * \return Returns true on success.  Returns false on error.
         */
        public function set_path(string $file_path) {
            if ($this->file_handle !== null) {
                fclose($this->file_handle);
            }

            $success = true;

            if ($file_path !== '') {
                $this->file_handle = @fopen($file_path, 'r');

                if ($this->file_handle === null || $this->file_handle === false) {
                    $this->file_handle = null;
                    $success = false;
                }
            } else {
                $this->file_handle = null;
            }

            return $success;
        }

        /**
         * Function that obtains all content from a file at or after a specific file pointer.
         *
         * \param[in] $file_offset The offset to start fetching data from.  A value of 0 gets all file contents.
         *
         * \return Returns an array of the form: array('ending_offset' => int, 'content' => array())
         *         where the ending offset represents the current ending file offset and 'content' represents
         *         an array of content lines.  A value of null is returned on error.
         */
        public function get_entries(int $file_offset = 0) {
            if ($this->file_handle !== null) {
                if (fseek($this->file_handle, $file_offset) !== false) {
                    $content = array();
                    do {
                        $l = fgets($this->file_handle);
                        if ($l !== false) {
                            $content[] = $l;
                        }
                    } while ($l !== false);

                    $new_offset = ftell($this->file_handle);

                    $result = array(
                        'starting_offset' => $file_offset,
                        'ending_offset' => $new_offset,
                        'content' => $content
                    );
                } else {
                    $result = null;
                }
            } else {
                $result = array(
                    'starting_offset' => 0,
                    'ending_offset' => 0,
                    'content' => array()
                );
            }

            return $result;
        }
    }
