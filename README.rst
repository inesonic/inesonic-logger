===============
inesonic-logger
===============
You can use this plugin to both look at Apache logs as well as an internally
managed log from the WordPress Admin panel.

To use, simply copy this entire directory into your WordPress plugins directory
and then activate the plugin from the WordPress admin panel.

You may also be interested in the
`Inesonic History Plugin <https://github.com/tuxidriver/inesonic-history>`
which provides similar capabilities but geared towards tracking customer
activity.  Both plugins were developed for the
`Inesonic company website <https://inesonic.com>`.


Configuring The Log Viewer
==========================
You can click on the "Configure" link on the Plugins page area for the Inesonic
Logger to display a small configuration panel.  On this panel, you can specify
the full path to the Apache log and errors files.

Clicking on the "Track user activity" checkbox will add entries to the internal
log on pages visited by a given user along with the user ID.  This allows you
to trace events between WordPress, your own plugins or child themes, and
Apache.  Note that this feature is intended for debug purposes only.

.. note::

   Inesonic does not use this feature on our production site unless needed
   to diagnose an existing issue.


Using The Log Viewer
====================
Clicking on "Log Viewer" will display a log screen.  You can select the logs
you wish to view using the checkboxes at the top.  You can also specify an
update rate for the page contents.

Log data will be displayed in reverse time order.  Enabling multiple logs will
cause the logs to be interleaved with entries for each log uniquely colored.

Clicking the "Purge Internal Logs" button will purge the internal debug log.


Actions
=======
You can add new entries to the internal log from your PHP code using one of
three actions:

* ``inesonic-logger-1`` will simply add text to the log.
* ``inesonic-logger-2`` will add text tied to a specific user ID.
* ``inesonic-logger-3`` will add text tied to a specific user ID and IP
  address.  This action is primarily intended to be used by the track user
  activity feature.

The example below shows how to add a simple log entry with text and the
contents of a variable:

.. code-block:: php

   do_action(
       'inesonic-logger-1',
       'Danger Will Robinson: ' . var_export($my_variable, true)
   );

The example shows how you can add a log entry tied to a specific user ID.

.. code-block:: php

   add_action('set_user_role', 'user_role_changed');

   . . .

   function user_role_changed($user_id, $role, $old_roles) {
       do_action(
           'inesonic-logger-2',
           'User role changed from ' . end($old_roles) . ' to ' . $role,
           $user_id
       );
   });
