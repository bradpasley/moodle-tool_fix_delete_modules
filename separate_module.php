<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Delete Modules but skip pre-delete functions of course/lib.php function
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot.'/lib/classes/task/logmanager.php');
require_login();

// Retrieve parameters.
$action       = required_param('action', PARAM_ALPHANUMEXT);
$taskid       = required_param('taskid', PARAM_INT);

if ($action == 'separate_module') {

    $url = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
    $prevurl = new moodle_url('/admin/tool/fix_delete_modules/index.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
    $PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'). " - separating module tasks");
    $renderer = $PAGE->get_renderer('core');

    echo $OUTPUT->header();

    // Get the adhoc task data.
    //if ($cm = $DB->get_record('task_adhoc', array('id' => $taskid, 'classname' => '\core_course\task\course_delete_modules'))) {
    // Create individual adhoc tasks & remove original task.
    if ($originaladhoctaskdata = get_original_cmdelete_adhoctask_data($taskid)) {

        $taskcount = 0;
        // Create individual adhoc task for each module.
        foreach ($originaladhoctaskdata as $cmid => $cmvalue) {
            // Get the course module.
            if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
                continue; // Skip it; it might have been deleted already.
            }

            // Update record, if not already updated.
            $cm->deletioninprogress = '1';
            $DB->update_record('course_modules', $cm);

            // Create an adhoc task for the deletion of the course module. The task takes an array of course modules for removal.
            $newdeletetask = new \core_course\task\course_delete_modules();
            $newdeletetask->set_custom_data(array(
                'cms' => array($cm),
                'userid' => $USER->id,
                'realuserid' => \core\session\manager::get_realuser()->id
            ));

            // Queue the task for the next run.
            \core\task\manager::queue_adhoc_task($newdeletetask);
        }
        echo '<p><b class="text-success">'.$taskcount.' New Individual course_delete_module tasks have been created</b></p>';

        // Remove old task.
        if ($originaladhoctask = get_adhoctask_from_taskid($taskid)) {

            // Delete the adhoc task record - it is finished.
            $DB->delete_records('task_adhoc', array('id' => $taskid));

            echo '<p><b class="text-success">Original course_delete_module task (id '.$taskid.') cleared</b></p>';
        } else {
            echo '<p><b class="text-danger">Original course_delete_module Adhoc task (id '.$taskid.') could not be found.</b></p>';
        }
        echo '<p>Refresh <a href="index.php">Fix Delete Modules Report page</a> and check the status.</p>';
    } else {
        echo '<p><b class="text-danger">Adhoc task course_delete_module (id '.$taskid.') could not be found.</b></p>';
        echo '<p>Refresh <a href="index.php">Fix Delete Modules Report page</a> and check the status.</p>';
    }

    echo '<p><b class="text-success">SUCCESSFUL Separation of Adhoc Tasks</b></p>';

    echo $OUTPUT->footer();
} else {
    throw new moodle_exception('error:actionnotfound', 'block_teachercontact', $prevurl, $action);
}
