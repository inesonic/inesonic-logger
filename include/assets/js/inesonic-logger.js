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
 * \file inesonic-logger.js
 *
 * JavaScript module that manages the logger listing page.
 */

/***********************************************************************************************************************
 * Constants:
 */

/**
 * Regular expression used to parse Apache log entries.
 *
 * Expected Apache 2 format: %h %l %u %t ...
 */
const apacheLogRe = new RegExp("^([0-9a-fA-F.:]+)\\s+([^\\s]+)\\s+([^\\s]+)\\s+\\[([^\\]]+)\\]\\s+(.*)");

/**
 * Regular expression used to parse Apache error log entries.
 */
const apacheErrorLogRe = new RegExp("^\\[[a-zA-Z]+\\s+([^\\]]+)\\]\\s+(.*)");

/***********************************************************************************************************************
 * Script scope locals:
 */

/**
 * Log refresh rate in mSec.
 */
let refreshRate = 30000;

/**
 * Flag indicating if we should read Apache logs.
 */
let apacheLogsEnabled = false;

/**
 * Flag indicating if we should read Apache error logs.
 */
let apacheErrorLogsEnabled = false;

/**
 * Flag indicating if we should read internal logs.
 */
let internalLogsEnabled = false;

/**
 * The last offset into the Apache logs -- Allows reading/processing deltas only.
 */
let apacheLogsOffset = 0;

/**
 * The last offset into the Apache error logs -- Allows reading/processing deltas only.
 */
let apacheErrorLogsOffset = 0;

/**
 * The last index into the internal logs -- Allows reading/processing deltas only.
 */
let internalLogsIndex = 0;

/**
 * The selected user to look at internal logs for.  A value of 0 indicates all users.
 */
let internalLogsUser = 0;

/**
 * The timer used to read logs.
 */
let refreshTimer = null;

/**
 * The current Apache log entries.
 */
let apacheLogs = [];

/**
 * The current Apache error log entries.
 */
let apacheErrorLogs = [];

/**
 * The current internal log entries.
 */
let internalLogs = [];

/***********************************************************************************************************************
 * Functions:
 */

/**
 * Function that appends an entry to the log table.
 *
 * \param[in] tableBody  The table to be inserted into.
 *
 * \param[in] logEntry   The log entry array to be inserted.
 *
 * \param[in] colorClass A class name used to color the text in the row.
 *
 * \param[in] purgeable  If true, this type of log entry can be purged by this plugin.
 */
function inesonicLoggerAddTableRow(tableBody, logEntry, colorClass, purgeable) {
    let tableRow = document.createElement("tr");
    tableRow.className = "inesonic-logger-table-row";

    let dateTimeElement = document.createElement("td");
    let ipAddressElement = document.createElement("td");
    let userIdElement = document.createElement("td");
    let contentElement = document.createElement("td");

    dateTimeElement.className = "inesonic-logger-table-data-datetime " + colorClass;
    ipAddressElement.className = "inesonic-logger-table-data-ipaddress " + colorClass;
    userIdElement.className = "inesonic-logger-table-data-user-id " + colorClass;
    contentElement.className = "inesonic-logger-table-data-content " + colorClass;

    if (logEntry === undefined || logEntry[0] === undefined) {
        console.log("Hit bad entry: " + logEntry);
    }

    let dateTimeString = logEntry[0] == 0 ? "" : moment.utc(1000 * logEntry[0]).format("D MMM YYYY HH:mm:ss");
    let ipAddressString = logEntry[1];
    let userIdString = logEntry[2];
    let contentString = logEntry[3];

    ("textContent" in dateTimeElement)
        ? dateTimeElement.textContent = dateTimeString
        : dateTimeElement.innerText = dateTimeString;

    if (ipAddressString != "") {
        ("textContent" in ipAddressElement)
            ? ipAddressElement.textContent = ipAddressString
            : ipAddressElement.innerText = ipAddressString;
    }

    if (userIdString != "") {
        ("textContent" in userIdElement)
            ? userIdElement.textContent = userIdString
            : userIdElement.innerText = userIdString;
    }

    if (contentString != '') {
        ("textContent" in contentElement)
            ? contentElement.textContent = contentString
            : contentElement.innerText = contentString;
    }

    tableRow.append(dateTimeElement);
    tableRow.append(ipAddressElement);
    tableRow.append(userIdElement);
    tableRow.append(contentElement);

    tableBody.append(tableRow);
}

/**
 * Function that updates the logger table.
 *
 * \param[in] apacheLogs      Array holding the apache logs sorted in time order.
 *
 * \param[in] apacheErrorLogs Array holding the apache error logs sorted in time order.
 *
 * \param[in] internalLogs    Array holding the internal logs sorted in time order.
 */
function inesonicLoggerUpdateTable(apacheLogs, apacheErrorLogs, internalLogs) {
    let doc = jQuery(document);
    let oldScrollPosition = doc.scrollTop();
    let oldDocumentHeight = doc.height();

    let tableBody = jQuery("#inesonic-logger-table-body");
    tableBody.empty();

    let numberApacheLogs = apacheLogs.length;
    let numberApacheErrorLogs = apacheErrorLogs.length;
    let numberInternalLogs = internalLogs.length;

    let ali = 0;
    let aeli = 0;
    let ili = 0;

    let apacheLogTimestamp = numberApacheLogs > 0 ? apacheLogs[0][0] : Number.MAX_VALUE;
    let apacheErrorLogTimestamp = numberApacheErrorLogs > 0 ? apacheErrorLogs[0][0] : Number.MAX_VALUE;
    let internalLogTimestamp = numberInternalLogs > 0 ? internalLogs[0][0] : Number.MAX_VALUE;

    while (ali < numberApacheLogs || aeli < numberApacheErrorLogs || ili < numberInternalLogs) {
        let bestTimestamp = Math.min(apacheLogTimestamp, apacheErrorLogTimestamp, internalLogTimestamp);

        if (bestTimestamp == apacheLogTimestamp) {
            inesonicLoggerAddTableRow(
                tableBody,
                apacheLogs[ali],
                "inesonic-logger-apache-log-color",
                false
            );

            ++ali;
            if (ali < numberApacheLogs) {
                apacheLogTimestamp = apacheLogs[ali][0];
            } else {
                apacheLogTimestamp = Number.MAX_VALUE;
            }
        } else if (bestTimestamp == apacheErrorLogTimestamp) {
            inesonicLoggerAddTableRow(
                tableBody,
                apacheErrorLogs[aeli],
                "inesonic-logger-apache-error-color",
                false
            );

            ++aeli;
            if (aeli < numberApacheErrorLogs) {
                apacheErrorLogTimestamp = apacheErrorLogs[aeli][0];
            } else {
                apacheErrorLogTimestamp = Number.MAX_VALUE;
            }
        } else {
            inesonicLoggerAddTableRow(
                tableBody,
                internalLogs[ili],
                "inesonic-logger-internal-color",
                false
            );

            ++ili;
            if (ili < numberInternalLogs) {
                internalLogTimestamp = internalLogs[ili][0];
            } else {
                internalLogTimestamp = Number.MAX_VALUE;
            }
        }
    }

    let r = (oldDocumentHeight - oldScrollPosition) / oldDocumentHeight;
    if (r < 0.01) {
        doc.scrollTop(doc.height());
    }
}

/**
 * Function that parses the Apache logs into constituent fields.
 *
 * \param[in] rawLogEntries An array of raw log entries to be parsed.
 *
 * \return Returns the entries converted to field values.
 */
function inesonicLoggerParseApacheLogs(rawLogEntries) {
    let result = [];
    let numberEntries = rawLogEntries.length;
    for (let i=0 ; i<numberEntries ; ++i) {
        let matches = rawLogEntries[i].match(apacheLogRe);
        if (matches !== null) {
            let ipAddress = matches[1];
            let identId = matches[2];
            let userId = matches[3];
            let dateTimeString = matches[4];
            let content = matches[5];

            let date = moment(dateTimeString, "DD/MMM/YYYY:HH:mm:ss ZZ");
            let unixTimestamp = date.utc().unix();

            result.push([ unixTimestamp, ipAddress, "", content ]);
        } else {
            result.push([ 0, "", "", rawLogEntries[i] ]);
        }
    }

    return result;
}

/**
 * Function that parses the Apache error logs into constituent fields.
 *
 * \param[in] rawLogEntries An array of raw log entries to be parsed.
 *
 * \return Returns the entries converted to field values.
 */
function inesonicLoggerParseApacheErrorLogs(rawLogEntries) {
    let result = [];
    let numberEntries = rawLogEntries.length;
    for (let i=0 ; i<numberEntries ; ++i) {
        let matches = rawLogEntries[i].match(apacheErrorLogRe);
        if (matches !== null) {
            let dateTimeString = matches[1];
            let content = matches[2];

            let date = moment(dateTimeString + " +0000", "MMM DD HH:mm:ss.SSSSSS YYYY ZZ");
            let unixTimestamp = date.utc().unix();

            result.push([ unixTimestamp, "", "", content ]);
        } else {
            result.push([ 0, "", "", rawLogEntries[i] ]);
        }
    }

    return result;
}

/**
 * Function that parses the internal logs.
 *
 * \param[in] rawLogEntries An array of raw log entries to be parsed.
 *
 * \return Returns the entries converted to field values.
 */
function inesonicLoggerParseInternalLogs(rawLogEntries) {
    return {
        entries : rawLogEntries.map(
            function(rawEntry) {
                return [ Number(rawEntry[0]), rawEntry[1], rawEntry[2], rawEntry[3] ];
            }
        ),
        next_index :   rawLogEntries.length == 0
                     ? internalLogsIndex
                     : Number(rawLogEntries[rawLogEntries.length - 1][4]) + 1
    }
}

/**
 * Function that is triggered to request log data.
 */
function inesonicLoggerReadLogs() {
    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: {
                "action" : "inesonic_logger_read",
                "apache_log" : apacheLogsEnabled,
                "apache_error_log" : apacheErrorLogsEnabled,
                "internal_log" : internalLogsEnabled,
                "apache_log_offset" : apacheLogsOffset,
                "apache_error_log_offset" : apacheErrorLogsOffset,
                "internal_log_index" : internalLogsIndex,
                "internal_log_user" : internalLogsUser
            },
            dataType: "json",
            success: function(response) {
                if (response !== null && response.status == "OK") {
                    if (apacheLogsEnabled) {
                        if (apacheLogsOffset == 0) {
                            apacheLogs = inesonicLoggerParseApacheLogs(response.apache_log.content);
                        } else {
                            apacheLogs = apacheLogs.concat(inesonicLoggerParseApacheLogs(response.apache_log.content));
                        }

                        apacheLogsOffset = response.apache_log.ending_offset;
                    } else {
                        apacheLogsOffset = 0;
                        apacheLogs = [];
                    }

                    if (apacheErrorLogsEnabled) {
                        if (apacheErrorLogsOffset == 0) {
                            apacheErrorLogs = inesonicLoggerParseApacheErrorLogs(response.apache_error_log.content);
                        } else {
                            apacheErrorLogs = apacheErrorLogs.concat(
                                inesonicLoggerParseApacheErrorLogs(response.apache_error_log.content)
                            );
                        }

                        apacheErrorLogsOffset = response.apache_error_log.ending_offset;
                    } else {
                        apacheErrorLogsOffset = 0;
                        apacheErrorLogs = [];
                    }

                    if (internalLogsEnabled) {
                        let internalLogsData = inesonicLoggerParseInternalLogs(response.internal_log);

                        if (internalLogsIndex == 0) {
                            internalLogs = internalLogsData.entries;
                        } else {
                            internalLogs = internalLogs.concat(internalLogsData.entries);
                        }

                        internalLogsIndex = internalLogsData.next_index;
                    } else {
                        internalLogIndex = 0;
                        internalLogs = [];
                    }

                    inesonicLoggerUpdateTable(apacheLogs, apacheErrorLogs,internalLogs);
                } else {
                    console.log("Failed to read log data.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get log data: " + errorThrown);
            }
        }
    );

    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(inesonicLoggerReadLogs, refreshRate);
}

/**
 * Function that is triggered when the Apache logs checkbox is changed.
 */
function inesonicLoggerApacheLogsCheckboxClicked() {
    apacheLogsEnabled = jQuery("#inesonic-logger-apache-logs-checkbox").prop("checked");

    apacheLogsOffset = 0;
    apacheLogs = [];

    if (apacheLogsEnabled) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(inesonicLoggerReadLogs, 10);
    } else {
        inesonicLoggerUpdateTable(apacheLogs, apacheErrorLogs,internalLogs);
    }
}

/**
 * Function that is triggered when the Apache error logs checkbox is changed.
 */
function inesonicLoggerApacheErrorLogsCheckboxClicked() {
    apacheErrorLogsEnabled = jQuery("#inesonic-logger-apache-errors-checkbox").prop("checked");

    apacheErrorLogsOffset = 0;
    apacheErrorLogs = []

    if (apacheErrorLogsEnabled) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(inesonicLoggerReadLogs, 10);
    } else {
        inesonicLoggerUpdateTable(apacheLogs, apacheErrorLogs,internalLogs);
    }
}

/**
 * Function that is triggered when the internal logs checkbox is changed.
 */
function inesonicLoggerInternalLogsCheckboxClicked() {
    internalLogsEnabled = jQuery("#inesonic-logger-internal-logs-checkbox").prop("checked");

    internalLogsIndex = 0;
    internalLogs = [];

    if (internalLogsEnabled) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(inesonicLoggerReadLogs, 10);
    } else {
        inesonicLoggerUpdateTable(apacheLogs, apacheErrorLogs,internalLogs);
    }
}

/**
 * Function that is triggered when the refresh rate changes.
 */
function inesonicLoggerRefreshRateChanged() {
    refreshRate = Number(jQuery("#inesonic-logger-refresh-interval-select").val());

    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(inesonicLoggerReadLogs, refreshRate);
}

/**
 * Function that is triggered to purge all internal log entries.
 */
function inesonicLoggerPurgeInternalLogs() {
    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: { "action" : "inesonic_logger_purge" },
            dataType: "json",
            success: function(response) {
                if (response !== null && response.status == "OK") {
                    internalLogsIndex = 0;
                    internalLogs = [];

                    if (internalLogsEnabled) {
                        clearTimeout(refreshTimer);
                        refreshTimer = setTimeout(inesonicLoggerReadLogs, 10);
                    }
                } else {
                    alert("Failed to purge log data.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get log data: " + errorThrown);
            }
        }
    );
}

/***********************************************************************************************************************
 * Main:
 */

jQuery(document).ready(function($) {
    $("#inesonic-logger-apache-logs-checkbox").change(inesonicLoggerApacheLogsCheckboxClicked);
    $("#inesonic-logger-apache-errors-checkbox").change(inesonicLoggerApacheErrorLogsCheckboxClicked);
    $("#inesonic-logger-internal-logs-checkbox").change(inesonicLoggerInternalLogsCheckboxClicked);
    $("#inesonic-logger-refresh-interval-select").change(inesonicLoggerRefreshRateChanged);
    $("#inesonic-logger-purge-internal-logs").click(inesonicLoggerPurgeInternalLogs);

    refreshTimer = setTimeout(inesonicLoggerReadLogs, 10);
});
