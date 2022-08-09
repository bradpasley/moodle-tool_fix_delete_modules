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
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

defined('MOODLE_INTERNAL') || die();
require_once("deletetask.php");

class outcome {
    /**
     * @var delete_task $task - the course_delete_module adhoc task.
     * @var array $messages - an array of strings, one for each action taken and/or its result.
     */
    private $task;
    private $messages;

    public const TASK_SEPARATE_TASK_MADE               = 'outcome_separate_into_individual_task';
    public const TASK_SEPARATE_TASK_NOT_MADE           = 'outcome_separate_into_individual_task_fail';
    public const TASK_SEPARATE_OLDTASK_DELETED         = 'outcome_separate_old_task_deleted';
    public const TASK_SEPARATE_OLDTASK_NOT_DELETED     = 'outcome_separate_old_task_delete_fail';
    public const TASK_ADHOCRECORDABSENT_ADVICE         = 'outcome_adhoc_task_record_advice';
    public const TASK_ADHOCTASK_RESCHEDULE             = 'outcome_adhoc_task_record_rescheduled';
    public const TASK_ADHOCTASK_RESCHEDULE_FAIL        = 'outcome_adhoc_task_record_reschedule_fail';
    public const TASK_ADHOCTASK_RUN_CLI                = 'outcome_task_run_cli';
    public const TASK_SUCCESS                          = 'outcome_task_fix_successful';
    public const TASK_FAIL                             = 'outcome_task_fix_fail';

    public const MODULE_MODULERECORD_DELETED           = 'outcome_module_table_record_deleted';
    public const MODULE_MODULEINSTANCEID_NOTFOUND      = 'outcome_module_table_cminstance_not_found';
    public const MODULE_COURSEMODULEID_NOTFOUND        = 'outcome_course_module_id_not_found';
    public const MODULE_COURSEMODULE_NOTFOUND          = 'outcome_course_module_table_record_not_found';
    public const MODULE_COURSEMODULERECORD_DELETED     = 'outcome_course_module_table_record_deleted';
    public const MODULE_COURSESECTION_DELETED          = 'outcome_course_section_data_deleted';
    public const MODULE_COURSESECTION_NOT_DELETED      = 'outcome_course_section_data_delete_fail';
    public const MODULE_CONTEXTRECORD_DELETED          = 'outcome_context_table_record_deleted';
    public const MODULE_COURSEMODULERECORD_NOT_DELETED = 'outcome_context_table_record_delete_fail';
    public const MODULE_CONTEXTID_NOTFOUND             = 'outcome_context_id_not_found';
    public const MODULE_FILERECORD_DELETED             = 'outcome_file_table_record_deleted';
    public const MODULE_CALENDAREVENT_DELETED          = 'outcome_calendar_event_deleted';
    public const MODULE_GRADEITEMRECORD_DELETED        = 'outcome_grade_tables_records_deleted';
    public const MODULE_BLOGRECORD_DELETED             = 'outcome_blog_table_record_deleted';
    public const MODULE_COMPLETIONRECORD_DELETED       = 'outcome_completion_table_record_deleted';
    public const MODULE_COMPLETIONCRITERIA_DELETED     = 'outcome_completion_criteria_table_record_deleted';
    public const MODULE_TAGRECORD_DELETED              = 'outcome_tag_table_record_deleted';
    public const MODULE_SUCCESS                        = 'outcome_module_fix_successful';
    public const MODULE_FAIL                           = 'outcome_module_fix_fail';

    public function __construct(delete_task $task, array $messages) {
        $this->task = $task;
        $this->messages = $messages;
    }

    /**
     * get_deletetask() - Get the array of delete_module objects.
     *
     * @return delete_task
     */
    public function get_task() {
        return $this->task;
    }

    /**
     * get_messages() - Get the array of outcome messages.
     *
     * @return array
     */
    public function get_messages() {
        return $this->messages;
    }

}
