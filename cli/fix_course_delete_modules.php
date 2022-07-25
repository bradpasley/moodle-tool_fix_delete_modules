<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * CLI script for tool_fix_delete_modules.
 *
 * @package     tool_fix_delete_modules
 * @subpackage  cli
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Get the cli options.
list($options, $unrecognized) = cli_get_params(array(
    'courses' => false,
    'fix'   => false,
    'help'  => false
),
array(
    'c' => 'courses',
    'f' => 'fix',
    'h' => 'help'
));

$help =
"
Checks and fixes incomplete course_delete_modules adhoc tasks.

Please include a list of options and associated actions.

Avoid executing the script when another user may simultaneously edit any of the
courses being checked (recommended to run in mainenance mode).

Options:
-c, --courses         List courses that need to be checked (comma-separated
                      values or * for all). Required
-f, --fix             Fix the mismatches in DB. If not specified check only and
                      report problems to STDERR
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/cli/fix_course_delete_modules.php --courses=*
\$sudo -u www-data /usr/bin/php admin/cli/fix_course_delete_modules.php --courses=2,3,4 --fix
";

if ($unrecognized) {
    $unrecognized = implode("\n\t", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    cli_writeln($help);
    die();
}

$courseslist = preg_split('/\s*,\s*/', $options['courses'], -1, PREG_SPLIT_NO_EMPTY);
if (in_array('*', $courseslist)) {
    $where = '';
    $params = array();
} else {
    list($sql, $params) = $DB->get_in_or_equal($courseslist, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;
}
$coursescount = $DB->get_field_sql('SELECT count(id) FROM {course} '. $where, $params);

if (!$coursescount) {
    cli_error('No courses found');
}
echo "Checking $coursescount courses...\n\n";

require_once($CFG->dirroot. '/course/lib.php');

$problems = array();
$courses = $DB->get_fieldset_sql('SELECT id FROM {course} '. $where, $params);
foreach ($courses as $courseid) {
    $errors = course_integrity_check($courseid, null, null, true, empty($options['fix']));
    if ($errors) {
        if (!empty($options['fix'])) {
            // Reset the course cache to make sure cache is recalculated next time the course is viewed.
            rebuild_course_cache($courseid, true);
        }
        foreach ($errors as $error) {
            cli_problem($error);
        }
        $problems[] = $courseid;
    } else {
        echo "Course [$courseid] is OK\n";
    }
}
if (!count($problems)) {
    echo "\n...All courses are OK\n";
} else {
    if (!empty($options['fix'])) {
        echo "\n...Found and fixed ".count($problems)." courses with problems". "\n";
    } else {
        echo "\n...Found ".count($problems)." courses with problems. To fix run:\n";
        echo "\$sudo -u www-data /usr/bin/php admin/cli/fix_course_sequence.php --courses=".join(',', $problems)." --fix". "\n";
    }
}
