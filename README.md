# Fix Delete Modules #

Plugin used to check and resolve any incomplete course_delete_module adhoc tasks.

## Check ##
- If the task(s) contains multiple course modules, it will recommend
  separating each module into separate adhoc tasks. See "Resolve" for more
  information.
- If there is/are task(s) which are incomplete, it will identify which database
  tables lack records, which cause the breakdown for the normal process deleting
  course modules. For example, the course module record or the assign table
  record might be missing. See "Resolve" for information about how the plugin
  can resolve these.

## Resolve ##
- Separate "clustered" adhoc tasks:
  If the adhoc task contains multiple modules, the plugin can separate them into
  individualised adhoc tasks (one per course module) so that modules without
  issue can be resolved as normal course_delete_module adhoc tasks. Any
  remaining tasks which have issues can be checked and resolved on their own.
- Delete remnant module data and clear off adhoc tasks:
  If a task is incomplete, the plugin can delete the remnant data records
  and clear off the course_delete_adhoc task. Although this process will NOT
  backup the module to the recycle bin.

## GUI and CLI ##
There is a GUI side and a CLI to the plugin.

### GUI ###
There is a config setting to filter out any tasks with a very low
"faildelay" status. For example, if the task has a faildelay of 0, it's
probably doesn't need to be viewed.

### CLI ###
The "faildelay" filter can also be modified via a param.
Instructions of how to use the CLI can be found in the help page:
$ php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php

## Branches ##

Moodle version     | Branch
------------------ | -----------------
Moodle 3.5 - 3.6   | main
Moodle 3.7 - 3.11  | MOODLE_37_STABLE
Moodle 4.0+        | MOODLE_400_STABLE

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/fix_delete_modules

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Configuration
- minimumfaildelay defines the minimum faildelay (in seconds) of
  course_delete_modules adhoc tasks to be displayed in the report page.

## License ##

2022 Catalyst-IT
Author Brad Pasley <brad.pasley@catalyst-au.net>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
