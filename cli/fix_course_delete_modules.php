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
require(__DIR__.'/../lib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/clilib.php');

// Get the cli options.
list($options, $unrecognized) = cli_get_params(array(
    'fix'      => false,
    'delaymin' => false,
    'modules'  => false,
    'help'     => false
),
array(
    'f' => 'fix',
    'd' => 'delaymin',
    'm' => 'modules',
    'h' => 'help'
));

$help =
"
Checks and fixes incomplete course_delete_modules adhoc tasks.

Please include a list of options and associated actions.

Avoid executing the script when another user may simultaneously edit any of the
courses being checked (recommended to run in mainenance mode).

Options:
-m, --modules         List modules that need to be checked (comma-separated
                      values or * for all). Required for fixing modules.
-d, --delaymin        Filter by the minimum faildelay field (in seconds)
-f, --fix             Fix the incomplete course_delete_module adhoc tasks.
                      To fix modules '--modules' must be explicitly specified which modules.
                      To fix clustered tasks, use '--fix=separate'.
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --modules=*
\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --modules=2,3,4 --fix
";

if ($unrecognized) {
    $unrecognized = implode("\n\t", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    cli_writeln($help);
    die();
}

$minimumfaildelay = 0;
if ($options['delaymin']) {
    if (is_numeric($options['delaymin'])) {
        $minimumfaildelay = intval($options['delaymin']);
    }
}

$moduleslist = preg_split('/\s*,\s*/', $options['modules'], -1, PREG_SPLIT_NO_EMPTY);
if (in_array('*', $moduleslist) || empty($moduleslist)) {
    $where = '';
    $params = array();
} else {
    list($sql, $params) = $DB->get_in_or_equal($moduleslist, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;
}

// Require --fix to also have the --modules param (with specific modules listed).
$isfix                     = $options['fix'];
$isfixclusteredtask        = $isfix && $options['fix'] === "separate";
$isfixwithmodulesspecified = $isfix && $options['modules'] && !empty($params);
if ($isfix && !$isfixclusteredtask && !$isfixwithmodulesspecified) {
    cli_error("fix_course_delete_modules.php '--fix' requires '--fix=separate' or '--modules=[coursemoduleids]'.");
    cli_writeln($help);
    die();
}

$totalmodulescount = $DB->get_field_sql('SELECT count(id) FROM {course_modules}');
$modulescount = $DB->get_field_sql('SELECT count(id) FROM {course_modules} '. $where, $params);

if (!$modulescount && !$options['fix']) { // If "fix" is included, attempt to resolve.
    cli_error('No modules found');
}

$coursemoduledeletetasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
$coursemoduledeletetaskscount = count($coursemoduledeletetasks);

$coursemodules = $DB->get_fieldset_sql('SELECT id FROM {course_modules} '. $where, $params);

echo "Checking for $modulescount/$totalmodulescount modules"
    ." in $coursemoduledeletetaskscount course_delete_module adhoc tasks...\n\n";

$coursemodules = ($totalmodulescount == $modulescount) ? array() : $coursemodules;
$cmsdata = get_all_cmdelete_adhoctasks_data($coursemodules, $minimumfaildelay);

require_once(__DIR__ . '/../lib.php');

$problems = array();
if (is_null($cmsdata) || empty($cmsdata)) {
    echo "\n...No modules are found in these adhoc tasks.";
    echo "\n...Perhaps adjust the coursemodule & faildelay parameters.\n\n";
    die();
}

$allerrors = array();
foreach ($cmsdata as $taskid => $cms) {
    $errors = array();
    echo "\n...Checking taskid $taskid... ";
    // Check if this task has multiple course modules aka 'clustered task'.
    if (count($cms) > 1) {
        $errors['clusteredtask'] = "This Adhoc task (id: $taskid) contains multiple course modules."
                                   ." Separating this into individualised adhoc tasks (one per module)"
                                   ." will assist in reducing the complexity of the failed course_delete_modules task.";
    } else { // Process module issues on individualised tasks only.
        $errors = course_module_delete_issues($cms, $taskid, $minimumfaildelay);
    }
    if ($errors) {
        echo "PROBLEM\n";
        foreach ($errors as $errorcode => $errormessage) {
            cli_problem($errormessage);
            if (!in_array($errorcode,  array("adhoctasktable", "clusteredtask"))) {
                $problems[] = $errorcode; // This should be a coursemoduleid.
            }
        }
        if (!empty($options['fix'])) {

            if (count($cms) > 1 || in_array("clusteredtask", array_keys($errors))) {
                // Separate into individual tasks. Adhoc Tasks need to be re-executed.
                echo "... Separating clustered adhoc task (taskid $taskid) into individualised module tasks.\n";
                echo separate_clustered_task_into_modules($cms, $taskid);
            } else { // Delete the remnant data related to this singular module task.
                echo "... Deleting remnant data for adhoc task (taskid $taskid).\n";
                $cm = current($cms);
                echo force_delete_module_data($cm, $taskid);
            }

        }
    } else {
        echo "OK";
    }
    $allerrors += $errors;
}
if (!count($allerrors)) {
    echo "\n...All course_delete_module Adhoc Tasks are OK\n";
} else {
    $haserroradhoctable  = in_array("adhoctasktable", array_keys($allerrors));
    $haserrorclustertask = in_array("clusteredtask", array_keys($allerrors));
    if (!empty($options['fix'])) { // Fix option was included.
        if ($haserroradhoctable) {
            echo "\n...Attempted to fix but Adhoc Task could not be found.\n";
        }
        if ($haserroradhoctable) {
            echo "\n...Found clustered Adhoc Task. Separated into multiple tasks to simplify issue.\n";
        }
        if (!$haserroradhoctable && !$haserrorclustertask) {
            echo "\n...Found and fixed ".count($problems)." course_delete_modules adhoc task with problems". "\n";
            echo PHP_EOL.'Now, run the adhoctask CLI command:'.PHP_EOL
                 .'\$sudo -u www-data /usr/bin/php admin/tool/task/cli/adhoc_task.php --execute'.PHP_EOL;
        }
    } else { // Only "Check".
        if ($haserroradhoctable) {
            echo "\n...Attempted to check but Adhoc Task could not be found.\n";
        }
        if ($haserrorclustertask) {
            echo "\n...Found clustered Adhoc Task (i.e. containining multiple modules). To fix, run:\n";
            echo "\$sudo -u www-data /usr/bin/php"
                ." admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --fix=separate\n";
        }
        if (!$haserroradhoctable && !$haserrorclustertask) {
            echo "\n...Found ".count($problems)." course_delete_module adhoc task problem(s). To fix, run:\n";
            echo "\$sudo -u www-data /usr/bin/php"
                ." admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php --modules="
                .join(',', $problems)." --fix\n";
        }
    }
}
