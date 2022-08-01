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
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/blog/lib.php');
require_login();

// Retrieve parameters.
$action       = required_param('action', PARAM_ALPHANUMEXT);
$cmid         = required_param('cmid', PARAM_INT);
$modulename   = required_param('cmname', PARAM_ALPHAEXT);
$taskid       = required_param('taskid', PARAM_INT);

$prevurl = new moodle_url('/admin/tool/fix_delete_modules/index.php');

if ($action == 'delete_module') {

    $url = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
    $PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'). " - deleting module");
    $renderer = $PAGE->get_renderer('core');

    echo $OUTPUT->header();

    // Get the course module.
    if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
        echo "<p>Course Module instance (cmid $cmid) doesn't exist. Perhaps you already deleted it</p>";
        echo '<p>Refresh <a href="index.php">Fix Delete Modules Report page</a> after the adhoc task has been run again.</p>';
        return true;
    }

    // Get the module context.
    $modcontext = context_module::instance($cmid);

    // Remove all module files in case modules forget to do that.
    $fs = get_file_storage();
    $fs->delete_area_files($modcontext->id);

    echo '<p><b>Files deleted for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Delete events from calendar.
    if ($events = $DB->get_records('event', array('instance' => $cm->instance, 'modulename' => $modulename))) {
        $coursecontext = context_course::instance($cm->course);
        foreach ($events as $event) {
            $event->context = $coursecontext;
            $calendarevent = calendar_event::load($event);
            $calendarevent->delete();
        }
        if (count($event) > 0) {
            echo '<p><b>'.count($events).' Calendar events for module cmid $cmid contextid '.$modcontext->id.'</b></p>';
        }
    }

    // Delete grade items, outcome items and grades attached to modules.
    if ($gradeitems = grade_item::fetch_all(array('itemtype' => 'mod', 'itemmodule' => $modulename,
                                                   'iteminstance' => $cm->instance, 'courseid' => $cm->course))) {
        foreach ($gradeitems as $gradeitem) {
            $gradeitem->delete('moddelete');
        }
        if (count($gradeitems) > 0) {
            echo '<p><b>'.count($gradeitems).' Grade items for module cmid $cmid contextid '.$modcontext->id.'</b></p>';
        }
    }

    // Delete associated blogs and blog tag instances.
    blog_remove_associations_for_module($modcontext->id);
    echo '<p><b>Deleted blogs for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Delete completion and availability data; it is better to do this even if the
    // features are not turned on, in case they were turned on previously (these will be
    // very quick on an empty table).
    $DB->delete_records('course_modules_completion', array('coursemoduleid' => $cm->id));
    echo '<p><b>Deleted Module Completion data for module cmid $cmid contextid '.$modcontext->id.'</b></p>';
    $DB->delete_records('course_completion_criteria', array('moduleinstance' => $cm->id,
                                                            'course' => $cm->course,
                                                            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY));
    echo '<p><b>Deleted Module Completion Criteria data for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Delete all tag instances associated with the instance of this module.
    \core_tag_tag::delete_instances('mod_' . $modulename, null, $modcontext->id);
    \core_tag_tag::remove_all_item_tags('core', 'course_modules', $cm->id);
    echo '<p><b>Deleted Tag data for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Notify the competency subsystem.
    \core_competency\api::hook_course_module_deleted($cm);

    // Delete the context.
    \context_helper::delete_instance(CONTEXT_MODULE, $cm->id);
    echo '<p><b>Context data for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Delete the module from the course_modules table.
    if ($DB->delete_records('course_modules', array('id' => $cm->id))) {
        echo 'Deleted Course Module record for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;
    } else {
        echo 'Deleted Course Module record: No record to delete for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;
    }

    // Delete module from that section.
    if (!delete_mod_from_section($cm->id, $cm->section)) {
        throw new moodle_exception('cannotdeletemodulefromsection', '', '', null,
            "Cannot delete the module $modulename (instance) from section.");
    }
    echo '<p><b>Deleted Module From Section for module cmid $cmid contextid '.$modcontext->id.'</b></p>';

    // Trigger event for course module delete action.
    $event = \core\event\course_module_deleted::create(array(
        'courseid' => $cm->course,
        'context'  => $modcontext,
        'objectid' => $cm->id,
        'other'    => array(
            'modulename' => $modulename,
            'instanceid'   => $cm->instance,
        )
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->trigger();
    rebuild_course_cache($cm->course, true);

    echo '<p><b class="text-success">SUCCESSFUL Deletion of Module and related data'
         .' (cmid $cmid contextid '.$modcontext->id.')</b></p>';

    // Reset adhoc task to run asap.
    if ($thisadhoctask = get_adhoctask_from_taskid($taskid)) {
        $thisadhoctask->set_fail_delay(0);
        $thisadhoctask->set_next_run_time(time());
        \core\task\manager::reschedule_or_queue_adhoc_task($thisadhoctask);
        echo '<p><b class="text-success">course_delete_module Adhoc task (id $taskid) set to run asap</b></p>';
    } else {
        echo '<p><b class="text-danger">course_delete_module Adhoc task (id $taskid) could not be found.</b></p>';
        echo '<p>Refresh <a href="index.php">Fix Delete Modules Report page</a> and check the status.</p>';
    }


    echo $OUTPUT->footer();
} else {
    throw new moodle_exception('error:actionnotfound', 'block_teachercontact', $prevurl, $action);
}
