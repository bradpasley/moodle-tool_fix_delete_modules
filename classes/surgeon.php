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
 * surgeon class which fixes a Course Module delete task and provides an outcome object.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once("deletetask.php");
require_once("deletemodule.php");
require_once("diagnosis.php");
require_once("outcome.php");
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/datalib.php');
require_once($CFG->dirroot.'/blog/lib.php');

/**
 * surgeon class which fixes a Course Module delete task and provides an outcome object.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class surgeon {
    /**
     * @var private outcome $outcome - the outcome of actions performed.
     */
    private $outcome;

    /**
     * Perform fix actions and establish a list of outcomes.
     *
     * @param diagnosis $diagnosis The diagnosis object, containing details of what needs fixing.
     */
    public function __construct(diagnosis $diagnosis) {

        $outcomemessages = array();
        // Run fix and get outcome messages.
        $outcomemessages = $this->fix($diagnosis);
        $this->outcome = new outcome($diagnosis->get_task(), $outcomemessages);
    }

    /**
     * Get the diagnosis attribute.
     *
     * @return diagnosis
     */
    public function get_diagnosis() {
        return $this->diagnosis;
    }

    /**
     * Get the outcome attribute.
     *
     * @return outcome
     */
    public function get_outcome() {
        return $this->outcome;
    }

    /**
     * Returns an array of outcome strings, after the diagnosed problems are fixed.
     *
     * @param diagnosis $diagnosis - the diagnosis of a course_delete_module task.
     *
     * @return array
     */
    public function fix(diagnosis $diagnosis) {
        $symptoms = $diagnosis->get_symptoms();
        $outcomemessages = array();

        // Deal with task issues first.

        // If there the adhoc task is absent, advise adhoc_task cli command to be run.
        if (in_array(get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules'),
                     array_keys($symptoms))) {
            $outcomemessages[] = get_string('outcome_adhoc_task_record_advice', 'tool_fix_delete_modules');
        } else if (in_array(get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules'),
                            array_keys($symptoms))) {
            // If there the adhoc task is a multi-module task, split it into many tasks.
            $outcomemessages = $this->separate_multitask_into_moduletasks($diagnosis);
        } else { // Now, without any task issues, proceed to fix this singular module's issue(s).
            $outcomemessages = $this->delete_module_cleanly($diagnosis);
        }
        return $outcomemessages;
    }

    /**
     * Creates a course_delete_module task for each module & deletes original task.
     *
     * @param diagnosis $diagnosis
     *
     * @return array - outcome strings.
     */
    private function separate_multitask_into_moduletasks(diagnosis $diagnosis) {
        global $DB;
        $outcomemessages = array();
        $multimoduletask = $diagnosis->get_task();
        $deletemodules   = $multimoduletask->get_deletemodules();

        // Keep track of each task-module's cm data to check during re-execution.
        $newtaskscms = array();
        // Create individual adhoc task for each module.
        foreach ($deletemodules as $cmid => $deletemodule) {
            // Get the course module.
            if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
                // Attempt to build from delete_module object.
                $cm = new stdClass();
                $cm->id = $deletemodule->coursemoduleid;
                if (isset($deletemodule->courseid)) {
                    $cm->course = $deletemodule->courseid;
                }
                if (isset($deletemodule->moduleinstanceid)) {
                    $cm->instance = $deletemodule->moduleinstanceid;
                }
                if (isset($deletemodule->section)) {
                    $cm->section = $deletemodule->section;
                }
            } else { // Only update existing records.
                // Update record, if not already updated.
                $cm->deletioninprogress = '1';
                $DB->update_record('course_modules', $cm);
            }

            // Create an adhoc task for the deletion of the course module. The task takes an array of course modules for removal.
            $newdeletetask = new \core_course\task\course_delete_modules();
            $mainadminid = get_admin()->id;
            $newdeletetask->set_custom_data(array(
                'cms' => array($cm),
                'userid' => $mainadminid,    // Set user to main admin.
                'realuserid' => $mainadminid // Set user to main admin.
            ));
            $newtaskscms[] = $newdeletetask->get_custom_data();

            // Queue the task for the next run.
            \core\task\manager::queue_adhoc_task($newdeletetask);
            $outcomemessages[] = get_string('outcome_separate_into_individual_task', 'tool_fix_delete_modules');
        }

        // Remove old task.
        if ($DB->delete_records('task_adhoc', array('id' => $multimoduletask->taskid))) {
            $outcomemessages[] = get_string('outcome_separate_old_task_deleted', 'tool_fix_delete_modules');
        } else {
            $outcomemessages[] = get_string('outcome_separate_old_task_delete_fail', 'tool_fix_delete_modules');
            $outcomemessages[] = get_string('outcome_task_fix_fail', 'tool_fix_delete_modules');
        }

        // Execute the new course_detele_module adhoc tasks now.
        $executionoutcomes = $this->execute_course_delete_module_tasks($newtaskscms);
        $outcomemessages = array_merge($outcomemessages, $executionoutcomes);
        $outcomemessages[] = get_string('outcome_task_fix_successful', 'tool_fix_delete_modules');

        return $outcomemessages;
    }

    /**
     * Deletes all remnant data related to a failed course_delete_module task.
     *
     * @param diagnosis $diagnosis
     *
     * @return array - outcome strings.
     */
    private function delete_module_cleanly(diagnosis $diagnosis) {
        global $DB, $OUTPUT;

        $outcomemessages  = array();
        $task = $diagnosis->get_task();
        if ($task->is_multi_module_task()) {
            // Should not have been passed to here, but just in case!
            $outputmessages[] = get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules');
            $outputmessages[] = get_string('outcome_module_fix_fail', 'tool_fix_delete_modules');
            return $outputmessages;
        }
        // Take first module; there should only be one anyway!
        $deletemodule = current($task->get_deletemodules());

        if (is_null($deletemodule->coursemoduleid)) {
            $outputmessages[] = get_string('outcome_course_module_id_not_found', 'tool_fix_delete_modules');
            $outputmessages[] = get_string('outcome_module_fix_fail', 'tool_fix_delete_modules');
            return $outputmessages;
        }

        // Get the course module.
        if (!$cm = $DB->get_record('course_modules', array('id' => $deletemodule->coursemoduleid))) {
            $outcomemessages[] = get_string('outcome_course_module_table_record_not_found', 'tool_fix_delete_modules');
            $cm = new stdClass();
            $cm->id = $deletemodule->coursemoduleid;
            $cm->course = $deletemodule->courseid;
            $cm->instance = $deletemodule->moduleinstanceid;
            $cm->section = $deletemodule->section;
        }
        // Get the module context.
        try {
            $modcontext = \context_module::instance($deletemodule->coursemoduleid);
        } catch (\dml_missing_record_exception $e) {
            $outputmessages[] = get_string('outcome_context_id_not_found', 'tool_fix_delete_modules');
            $modcontext = false;
        }

        // Get the module name.
        $modulename = $this->get_module_name($cm, $deletemodule);

        // Remove all module files in case modules forget to do that.
        if ($modcontext) {
            $fs = get_file_storage();
            $fs->delete_area_files($modcontext->id);
            $outcomemessages[] = get_string('outcome_file_table_record_deleted', 'tool_fix_delete_modules');
        }

        // Delete events from calendar.
        if ($modulename && isset($cm->course) && $events = $DB->get_records('event', array('instance' => $cm->instance,
                                                                                           'modulename' => $modulename))) {
            $coursecontext = \context_course::instance($cm->course);
            foreach ($events as $event) {
                $event->context = $coursecontext;
                $calendarevent = \calendar_event::load($event);
                $calendarevent->delete();
                $outcomemessages[] = get_string('outcome_calendar_event_deleted', 'tool_fix_delete_modules');
            }
        }

        // Delete grade items, outcome items and grades attached to modules.
        if ($modulename && isset($cm->course)
            && $DB->record_exists($modulename, array('id' => $cm->instance))
            && $gradeitems = \grade_item::fetch_all(array('itemtype' => 'mod',
                                                          'itemmodule' => $modulename,
                                                          'iteminstance' => $cm->instance,
                                                          'courseid' => $cm->course))) {
            foreach ($gradeitems as $gradeitem) {
                $gradeitem->delete('moddelete');
                $outcomemessages[] = get_string('outcome_grade_tables_records_deleted', 'tool_fix_delete_modules');
            }
        }

        // Delete associated blogs and blog tag instances.
        if ($modcontext) {
            blog_remove_associations_for_module($modcontext->id);
            $outcomemessages[] = get_string('outcome_blog_table_record_deleted', 'tool_fix_delete_modules');
        }

        // Delete completion and availability data; it is better to do this even if the
        // features are not turned on, in case they were turned on previously (these will be
        // very quick on an empty table).
        if ($modcontext) {
            if ($DB->delete_records('course_modules_completion', array('coursemoduleid' => $cm->id))) {
                $outcomemessages[] = get_string('outcome_completion_table_record_deleted', 'tool_fix_delete_modules');
            }
            if ($DB->delete_records('course_completion_criteria',
                                    array('moduleinstance' => $cm->id,
                                          'course' => $cm->course,
                                          'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
                $outcomemessages[] = get_string('outcome_completion_criteria_table_record_deleted', 'tool_fix_delete_modules');
            }
        }

        // Delete all tag instances associated with the instance of this module.
        if ($modcontext) {
            \core_tag_tag::delete_instances('mod_' . $modulename, null, $modcontext->id);
            \core_tag_tag::remove_all_item_tags('core', 'course_modules', $cm->id);
            $outcomemessages[] = get_string('outcome_tag_table_record_deleted', 'tool_fix_delete_modules');
        }

        // Notify the competency subsystem.
        \core_competency\api::hook_course_module_deleted($cm);

        // Delete the context.
        if ($modcontext) {
            \context_helper::delete_instance(CONTEXT_MODULE, $cm->id);

            $outcomemessages[] = get_string('outcome_context_table_record_deleted', 'tool_fix_delete_modules');
        }

        // Delete the module from the course_modules table.
        if ($DB->delete_records('course_modules', array('id' => $cm->id))) {
            $outcomemessages[] = get_string('outcome_course_module_table_record_deleted', 'tool_fix_delete_modules');
        } else {
            $outcomemessages[] = get_string('outcome_context_table_record_delete_fail', 'tool_fix_delete_modules');
        }

        // Delete module from that section.
        if (!delete_mod_from_section($cm->id, $cm->section)) {
            $outcomemessages[] = get_string('outcome_course_section_data_deleted', 'tool_fix_delete_modules');
        } else {
            $outcomemessages[] = get_string('outcome_course_section_data_delete_fail', 'tool_fix_delete_modules');
        }

        // Trigger event for course module delete action.
        if ($modcontext && $modulename) {
            $event = \core\event\course_module_deleted::create(array(
                'courseid' => $cm->course,
                'context'  => $modcontext,
                'objectid' => $cm->id,
                'other'    => array(
                    'modulename'   => $modulename,
                    'instanceid'   => $cm->instance,
                )
            ));
            $event->add_record_snapshot('course_modules', $cm);
            $event->trigger();
            // Function for Moodle 4.0+.
            if (method_exists('\course_modinfo', 'purge_course_module_cache')) {
                \course_modinfo::purge_course_module_cache($cm->course, $cm->id);
            }
            rebuild_course_cache($cm->course, true);
        }

        // Reset adhoc task to run asap.
        $now = time();
        if ($thisadhoctask = $this->get_adhoctask_from_taskid($task->taskid)) {
            $thisadhoctask->set_fail_delay(0);
            $thisadhoctask->set_next_run_time($now);
            // Function exists on Moodle 3.7+.
            if (method_exists('\core\task\manager', 'reschedule_or_queue_adhoc_task')) {
                \core\task\manager::reschedule_or_queue_adhoc_task($thisadhoctask);
            } else {
                $this->reschedule_or_queue_adhoc_task($thisadhoctask);
            }
            $outcomemessages[] = get_string('outcome_adhoc_task_record_rescheduled', 'tool_fix_delete_modules');
            try {
                // Retrieve rescheduled task from queue.
                $adhoctasks = $DB->get_records('task_adhoc');
                while ($rescheduledadhoctask = \core\task\manager::get_next_adhoc_task($now + 60)) {
                    if ($rescheduledadhoctask->get_id() === $thisadhoctask->get_id()) {
                        break; // Found it!
                    } else { // Delay this 'other' task.
                        \core\task\manager::adhoc_task_failed($rescheduledadhoctask);
                    }
                }

                // Execute the task.
                $thisadhoctask->execute();
                \core\task\manager::adhoc_task_complete($rescheduledadhoctask);
                $outcomemessages[] = get_string('outcome_adhoc_task_record_reexecution', 'tool_fix_delete_modules');
            } catch (moodle_exception $e) {
                \core\task\manager::adhoc_task_failed($thisadhoctask);
                $outcomemessages[] = get_string('outcome_adhoc_task_record_reexecution_failed', 'tool_fix_delete_modules');
            }
        } else {
            $dbtasks = $DB->get_records('task_adhoc');
            $outcomemessages[] = get_string('outcome_adhoc_task_record_rescheduled_failed', 'tool_fix_delete_modules');
        }
        $outcomemessages[] = get_string('outcome_module_fix_successful', 'tool_fix_delete_modules');

        return $outcomemessages;
    }

    /**
     * Attempts to get the module type name from the delete_module object or via the database.
     *
     * @param stdClass $cm - course module record.
     * @param delete_module $deletemodule
     *
     * @return string
     */
    private function get_module_name(stdClass $cm, delete_module $deletemodule) {

        $returnname = null;
        // If the delete_module name is not set, try to get it from cm/db.
        $returnname = $deletemodule->get_modulename();
        if (!isset($returnname)) {
            global $DB;
            // If $cm doesn't have the modulename, try to retrieve that from the {module} table's name field.
            $moduleid = isset($cm->module) ? $cm->module : null;
            if (!isset($moduleid)) {
                // Grab the {module} table id from this module's course_module record.
                if (!$moduleid = $DB->get_field('course_modules', 'module', array('id' => $deletemodule->coursemoduleid))) {
                    $moduleid = null;
                }
            }
            // If moduleid is now set, attempt to retrieve the modulename from the modules table.
            if (isset($moduleid) && $modulename = $DB->get_field('modules', 'name', array('id' => $moduleid))) {
                    $returnname = $modulename;
            } else { // If moduleid is still not set or the name can't be retrieved, return null.
                    $returnname = null;
            }
        }
        return $returnname;
    }

    /**
     * get_adhoctask_from_taskid()
     *
     * @param int $taskid - the taskid of a course_delete_modules adhoc task.
     * @return \core\task\adhoc_task|bool - false if not found.
     */
    private function get_adhoctask_from_taskid(int $taskid) {
        $thisadhoctask = null;
        $cdmadhoctasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        foreach ($cdmadhoctasks as $adhoctask) {
            if ($adhoctask->get_id() == $taskid) {
                $thisadhoctask = $adhoctask;
                break;
            }
        }
        if (isset($thisadhoctask)) {
            return $thisadhoctask;
        } else {
            return false;
        }
    }

    /**
     * Schedule a new task, or reschedule an existing adhoc task which has matching data.
     *
     * Only a task matching the same user, classname, component, and customdata will be rescheduled.
     * If these values do not match exactly then a new task is scheduled.
     *
     * Adapted from Moodle 3.7+ version function.
     *
     * @param \core\task\adhoc_task $task - The new adhoc task information to store.
     * @return void
     */
    public static function reschedule_or_queue_adhoc_task(\core\task\adhoc_task $task) {
        global $DB;

        // Find classname.
        $classname = (strpos(get_class($task), '\\') !== 0) ? '\\'.get_class($task) : get_class($task);

        // Check that it is a course_delete_module adhoc task.
        if ($classname == '\core_course\task\course_delete_modules') {
            // Check the task exists in the database.
            if ($existingrecord = self::get_queued_adhoc_task_record($task)) {
                // Only update the next run time if it is explicitly set on the task.
                $nextruntime = $task->get_next_run_time();
                if ($nextruntime && ($existingrecord->nextruntime != $nextruntime)) {
                    $DB->set_field('task_adhoc', 'nextruntime', $nextruntime, ['id' => $existingrecord->id]);
                }
            } else {
                // There is nothing queued yet. Just queue as normal.
                \core\task\manager::queue_adhoc_task($task);
            }
        }
    }

    /**
     * Checks if the task with the same classname, component and customdata is already scheduled
     *
     * Adapted from Moodle 3.7+ version function.
     *
     * @param adhoc_task $task
     * @return stdClass
     */
    private static function get_queued_adhoc_task_record($task) {
        global $DB;

        $record = \core\task\manager::record_from_adhoc_task($task);
        $params = [$record->classname, $record->component, $record->customdata];
        $sql = 'classname = ? AND component = ? AND ' .
            $DB->sql_compare_text('customdata', \core_text::strlen($record->customdata) + 1) . ' = ?';

        if ($record->userid) {
            $params[] = $record->userid;
            $sql .= " AND userid = ? ";
        }
        return $DB->get_record_select('task_adhoc', $sql, $params);
    }

    /**
     * Run course_delete_module tasks asap.
     *
     * Used after multi-module course_delete_module tasks have been split into separate, individualised tasks.
     *
     * @param stdClass[] $newtaskscms array of the custom_data objects to ensure only the newly made adhoctasks are executed.
     *
     * @return string[] - returns the outcome messages of each execution (either successful or failed).
     */
    private static function execute_course_delete_module_tasks(array $newtaskscms) {
        global $DB;

        $executionoutcomes = array();

        $alldeletemoduletasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        $now = time();
        while ($nextadhoctask = \core\task\manager::get_next_adhoc_task($now + 60)) {
            // Only run newly made course_delete_module adhoc tasks; delay others.
            if (in_array($nextadhoctask->get_id(), array_keys($alldeletemoduletasks)) &&
                in_array($nextadhoctask->get_custom_data(), $newtaskscms)) {
                try { // Some of these will pass, some will fail.
                    $nextadhoctask->execute();
                    \core\task\manager::adhoc_task_complete($nextadhoctask);
                    $executionoutcomes[] = get_string('outcome_adhoc_task_record_reexecution', 'tool_fix_delete_modules');
                } catch (moodle_exception $e) {
                    \core\task\manager::adhoc_task_failed($nextadhoctask);
                    $executionoutcomes[] = get_string('outcome_adhoc_task_record_reexecution_failed', 'tool_fix_delete_modules');
                }
            } else { // Delay other tasks.
                \core\task\manager::adhoc_task_failed($nextadhoctask);
            }
        }
        return $executionoutcomes;
    }
}
