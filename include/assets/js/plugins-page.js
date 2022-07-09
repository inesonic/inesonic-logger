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
 * \file plugins-page.js
 *
 * JavaScript module that manages logger configuration via the WordPress Plug-Ins page.
 */

/***********************************************************************************************************************
 * Functions:
 */

/**
 * Function that displays the manual configuration fields.
 */
function inesonicLoggerToggleConfiguration() {
    let areaRow = jQuery("#inesonic-logger-configuration-area-row");
    if (areaRow.hasClass("inesonic-row-hidden")) {
        areaRow.prop("class", "inesonic-logger-configuration-area-row inesonic-row-visible");
    } else {
        areaRow.prop("class", "inesonic-logger-configuration-area-row inesonic-row-hidden");
    }
}

/**
 * Function that updates the logger settings fields.
 *
 * \param[in] apacheLogPath     The path to the Apache log file.
 *
 * \param[in] apacheErrorsPath  The path to the Apache erros file.
 *
 * \param[in] trackUserActivity Boolean holding true if user activity is to be tracked.
 */
function inesonicLoggerUpdateFields(apacheLogPath, apacheErrorsPath, trackUserActivity) {
    jQuery("#inesonic-logger-apache-log-input").val(apacheLogPath);
    jQuery("#inesonic-logger-apache-errors-input").val(apacheErrorsPath);
    jQuery("#inesonic-logger-track-user-activity").prop("checked", trackUserActivity);
}

/**
 * Function that is triggered to update the logger configuration fields.
 */
function inesonicLoggerUpdateConfigurationFields() {
    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: { "action" : "inesonic_logger_get_configuration" },
            dataType: "json",
            success: function(response) {
                if (response !== null && response.status == 'OK') {
                    let apacheLogPath = response.apache_log_path;
                    let apacheErrorsPath = response.apache_errors_path;
                    let trackUserActivity = response.track_user_activity;

                    inesonicLoggerUpdateFields(apacheLogPath, apacheErrorsPath, trackUserActivity);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get configuration: " + errorThrown);
            }
        }
    );
}

/**
 * Function that is triggered to update the logger settings.
 */
function inesonicLoggerConfigureSubmit() {
    let apacheLogPath = jQuery("#inesonic-logger-apache-log-input").val();
    let apacheErrorsPath = jQuery("#inesonic-logger-apache-errors-input").val();
    let trackUserActivity = jQuery("#inesonic-logger-track-user-activity").prop("checked");

    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: {
                "action" : "inesonic_logger_update_configuration",
                "apache_log_path" : apacheLogPath,
                "apache_errors_path" : apacheErrorsPath,
                "track_user_activity" : trackUserActivity
            },
            dataType: "json",
            success: function(response) {
                if (response !== null) {
                    if (response.status == 'OK') {
                        inesonicLoggerToggleConfiguration();
                    } else {
                        alert("Failed to update Logger settings:\n" + response.status);
                    }
                } else {
                    alert("Failed to update Logger settings");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get configuration: " + errorThrown);
            }
        }
    );
}

/***********************************************************************************************************************
 * Main:
 */

jQuery(document).ready(function($) {
    inesonicLoggerUpdateConfigurationFields();
    $("#inesonic-logger-configure-link").click(inesonicLoggerToggleConfiguration);
    $("#inesonic-logger-configure-submit-button").click(inesonicLoggerConfigureSubmit);
});
