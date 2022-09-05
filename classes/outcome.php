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
 * class which provides outcome string(s) after fixing a course_delete_module task.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

defined('MOODLE_INTERNAL') || die();
require_once("deletetask.php");
/**
 * class which provides outcome string(s) after fixing a course_delete_module task.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome {
    /** @var delete_task $task - the course_delete_module adhoc task. */
    private $task;
    /** @var string[] $messages - an array of strings, one for each action taken and/or its result. */
    private $messages;

    /** @var TASK_SEPARATE_TASK_MADE an outcome state for separating tasks. */
    public const TASK_SEPARATE_TASK_MADE               = 'outcome_separate_into_individual_task';
    /** @var TASK_SEPARATE_TASK_NOT_MADE an outcome state for separating tasks.*/
    public const TASK_SEPARATE_TASK_NOT_MADE           = 'outcome_separate_into_individual_task_fail';
    /** @var TASK_SEPARATE_OLDTASK_DELETED an outcome state for separating tasks.*/
    public const TASK_SEPARATE_OLDTASK_DELETED         = 'outcome_separate_old_task_deleted';
    /** @var TASK_SEPARATE_OLDTASK_NOT_DELETED an outcome state for separating tasks.*/
    public const TASK_SEPARATE_OLDTASK_NOT_DELETED     = 'outcome_separate_old_task_delete_fail';
    /** @var TASK_ADHOCRECORDABSENT_ADVICE an outcome; when the task_adhoc record is missing.*/
    public const TASK_ADHOCRECORDABSENT_ADVICE         = 'outcome_adhoc_task_record_advice';
    /** @var TASK_ADHOCTASK_EXECUTE describes an outcome for re-executing an adhoc task.*/
    public const TASK_ADHOCTASK_EXECUTE             = 'outcome_adhoc_task_record_rescheduled';
    /** @var TASK_ADHOCTASK_EXECUTE_FAIL describes an outcome for re-executing an adhoc task.*/
    public const TASK_ADHOCTASK_EXECUTE_FAIL        = 'outcome_adhoc_task_record_reschedule_fail';
    /** @var TASK_ADHOCTASK_RESCHEDULE describes an outcome for rescheduling a task.*/
    public const TASK_ADHOCTASK_RESCHEDULE             = 'outcome_adhoc_task_record_rescheduled';
    /** @var TASK_ADHOCTASK_RESCHEDULE_FAIL describes an outcome for rescheduling a task.*/
    public const TASK_ADHOCTASK_RESCHEDULE_FAIL        = 'outcome_adhoc_task_record_reschedule_fail';
    /** @var TASK_SUCCESS an outcome state for fixing tasks.*/
    public const TASK_SUCCESS                          = 'outcome_task_fix_successful';
    /** @var TASK_FAIL an outcome state for fixing tasks.*/
    public const TASK_FAIL                             = 'outcome_task_fix_fail';
    /** @var MODULE_MODULERECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_MODULERECORD_DELETED           = 'outcome_module_table_record_deleted';
    /** @var MODULE_MODULEINSTANCEID_NOTFOUND an outcome state for fixing a module being deleted.*/
    public const MODULE_MODULEINSTANCEID_NOTFOUND      = 'outcome_module_table_cminstance_not_found';
    /** @var MODULE_COURSEMODULEID_NOTFOUND an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSEMODULEID_NOTFOUND        = 'outcome_course_module_id_not_found';
    /** @var MODULE_COURSEMODULE_NOTFOUND an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSEMODULE_NOTFOUND          = 'outcome_course_module_table_record_not_found';
    /** @var MODULE_COURSEMODULERECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSEMODULERECORD_DELETED     = 'outcome_course_module_table_record_deleted';
    /** @var MODULE_COURSESECTION_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSESECTION_DELETED          = 'outcome_course_section_data_deleted';
    /** @var MODULE_COURSESECTION_NOT_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSESECTION_NOT_DELETED      = 'outcome_course_section_data_delete_fail';
    /** @var MODULE_CONTEXTRECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_CONTEXTRECORD_DELETED          = 'outcome_context_table_record_deleted';
    /** @var MODULE_COURSEMODULERECORD_NOT_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COURSEMODULERECORD_NOT_DELETED = 'outcome_context_table_record_delete_fail';
    /** @var MODULE_CONTEXTID_NOTFOUND an outcome state for fixing a module being deleted.*/
    public const MODULE_CONTEXTID_NOTFOUND             = 'outcome_context_id_not_found';
    /** @var MODULE_FILERECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_FILERECORD_DELETED             = 'outcome_file_table_record_deleted';
    /** @var MODULE_CALENDAREVENT_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_CALENDAREVENT_DELETED          = 'outcome_calendar_event_deleted';
    /** @var MODULE_GRADEITEMRECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_GRADEITEMRECORD_DELETED        = 'outcome_grade_tables_records_deleted';
    /** @var MODULE_BLOGRECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_BLOGRECORD_DELETED             = 'outcome_blog_table_record_deleted';
    /** @var MODULE_COMPLETIONRECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COMPLETIONRECORD_DELETED       = 'outcome_completion_table_record_deleted';
    /** @var MODULE_COMPLETIONCRITERIA_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_COMPLETIONCRITERIA_DELETED     = 'outcome_completion_criteria_table_record_deleted';
    /** @var MODULE_TAGRECORD_DELETED an outcome state for fixing a module being deleted.*/
    public const MODULE_TAGRECORD_DELETED              = 'outcome_tag_table_record_deleted';
    /** @var MODULE_SUCCESS an outcome state for fixing a module being deleted.*/
    public const MODULE_SUCCESS                        = 'outcome_module_fix_successful';
    /** @var MODULE_FAIL an outcome state for fixing a module being deleted.*/
    public const MODULE_FAIL                           = 'outcome_module_fix_fail';

    /**
     * Constructor makes an array of outcome messages (i.e. standard strings).
     *
     * @param delete_task $task The course_delete_module task related to the outcomes.
     * @param string[] $messages The outcome messages for this outcome.
     */
    public function __construct(delete_task $task, array $messages) {
        $this->task = $task;
        $this->messages = $messages;
    }

    /**
     * Get the array of delete_module objects.
     *
     * @return delete_task
     */
    public function get_task() {
        return $this->task;
    }

    /**
     * Get the array of outcome messages.
     *
     * @return array
     */
    public function get_messages() {
        return $this->messages;
    }

}
