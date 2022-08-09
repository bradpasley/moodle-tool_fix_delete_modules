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
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require(__DIR__.'/../classes/reporter.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/clilib.php');

use tool_fix_delete_modules\reporter as reporter;

// Get the cli options.
list($options, $unrecognized) = cli_get_params(array(
    'fix'      => false,
    'minimumfaildelay' => false,
    'taskids'  => false,
    'help'     => false
),
array(
    'f' => 'fix',
    'm' => 'minimumfaildelay',
    't' => 'taskids',
    'h' => 'help'
));

$help =
"
Checks and fixes incomplete course_delete_modules adhoc tasks.

Please include a list of options and associated actions.

Avoid executing the script when another user may simultaneously edit any of the
course modules being checked (recommended to run in mainenance mode).

Options:
-t, --taskids            List adhoc tasks (by their id) that need to be
                         checked/fixed.
                         (comma-separated values or * for all).
                         Only required for fixing any tasks.
-m, --minimumfaildelay   Filter by the minimum faildelay field (in seconds)
-f, --fix                Fix the incomplete course_delete_module adhoc tasks.
                         To fix tasks '--taskids' must be explicitly
                         specified which modules.
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --taskids=*
\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --taskids=2,3,4 --fix
";

if ($unrecognized) {
    $unrecognized = implode("\n\t", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    cli_writeln($help);
    die();
}

$minimumfaildelay = 60; // Default to 60 seconds to exclude any tasks which haven't run yet.
if ($options['minimumfaildelay'] !== false) {
    if (is_numeric($options['minimumfaildelay'])) {
        $minimumfaildelay = intval($options['minimumfaildelay']);
    }
}

$taskids = preg_split('/\s*,\s*/', $options['taskids'], -1, PREG_SPLIT_NO_EMPTY);
if (in_array('*', $taskids) || empty($taskids)) {
    $where = '';
    $params = array();
} else {
    list($sql, $params) = $DB->get_in_or_equal($taskids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;
}

// Require --fix to also have the --modules param (with specific modules listed).
$isfix                     = $options['fix'];
$isfixwithmodulesspecified = $isfix && $options['taskids'] && !empty($params);
if ($isfix && !$isfixclusteredtask && !$isfixwithmodulesspecified) {
    cli_error("fix_course_delete_modules.php '--fix' requires '--taskids=[comma separated taskids]'.");
    cli_writeln($help);
    die();
}

$taskcount = $DB->get_field_sql('SELECT count(id) FROM {task_adhoc} '. $where, $params);

if (!$taskcount && !$options['fix']) { // If "fix" is included, attempt to resolve.
    cli_error('No course_delete_module adhoc tasks found');
}

$coursemoduledeletetasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
$totaltaskscount = count($coursemoduledeletetasks);

echo "Checking $taskcount/$totaltaskscount course_delete_module adhoc tasks...\n\n";

$taskids = ($totaltaskscount == $taskcount) ? array() : $taskids;

if ($totaltaskscount == 0) {
    echo "\n...No course_delete_module adhoc tasks found.\n\n";
    die();
}

$problems = array();
$allerrors = array();

$reporter = new reporter(false, $minimumfaildelay, $taskids);

// Check for errors.
$diagnoses = $reporter->get_diagnosis();

if ($diagnoses !== '' || !empty($options['fix'])) {
    echo $diagnoses;
    if (!empty($options['fix'])) { // Fix if 'fix' param settings made correctly.
        $outcomes = $reporter->make_fix($taskids);
        echo $outcomes;
    }
} else {
    echo "\n... No issues found (minimum faildelay filter: $minimumfaildelay seconds)\n\n";
}
