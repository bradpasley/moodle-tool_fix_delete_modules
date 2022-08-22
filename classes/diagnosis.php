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
 * class which provides a diagnosis for a failed Course Module delete task.
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
/**
 * class which provides a diagnosis for a failed Course Module delete task.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnosis {
    /**
     * @var delete_task $task - the course_delete_module adhoc task.
     * @var array $symptoms - an array of strings which describe (coursemoduleid as index).
     * @var bool $ismultimoduletask - true if the symptoms included multimodule task in constructor param.
     * @var bool $adhoctaskmissing  - true if the adhoctask record is missing.
     * @var bool $modulehasmissingdata - true if there is some kind of missing data related to the course module being deleted.
     */
    private $task;
    private $symptoms;
    private $ismultimoduletask;
    private $adhoctaskmissing;
    private $modulehasmissingdata;

    public const GOOD                             = 'symptom_good_no_issues';
    public const TASK_MULTIMODULE                 = 'symptom_multiple_modules_in_task';
    public const TASK_ADHOCRECORDMISSING          = 'symptom_adhoc_task_record_missing';
    public const MODULE_MODULERECORDMISSING       = 'symptom_module_table_record_missing';
    public const MODULE_COURSEMODULERECORDMISSING = 'symptom_course_module_table_record_missing';
    public const MODULE_CONTEXTRECORDMISSING      = 'symptom_context_table_record_missing';

    public function __construct(delete_task $task, array $symptoms) {
        $stringtmm = get_string($this::TASK_MULTIMODULE, 'tool_fix_delete_modules');
        $stringtam = get_string($this::TASK_ADHOCRECORDMISSING, 'tool_fix_delete_modules');
        $stringmrm = get_string($this::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules');
        $stringcmm = get_string($this::MODULE_COURSEMODULERECORDMISSING, 'tool_fix_delete_modules');
        $stringcrm = get_string($this::MODULE_CONTEXTRECORDMISSING, 'tool_fix_delete_modules');

        if (in_array($stringtmm, array_values($symptoms))) {
            $this->ismultimoduletask = true;
        } else {
            $this->ismultimoduletask = false;
        }

        if (in_array($stringtam, array_values($symptoms))) {
            $this->adhoctaskmissing = true;
        } else {
            $this->adhoctaskmissing = false;
        }

        // If it's not one of the above, check individual cm symptoms.
        $this->modulehasmissingdata = false;
        if (!in_array($stringtam, array_values($symptoms))
            && !in_array($stringtmm, array_values($symptoms))) {
            foreach ($symptoms as $cmid => $cmsymptoms) {
                if (in_array($stringmrm, $cmsymptoms)
                    || in_array($stringcmm, $cmsymptoms)
                    || in_array($stringcrm, $cmsymptoms)) {
                        $this->modulehasmissingdata = true;
                }
            }
        }

        $this->task = $task;
        $this->symptoms = $symptoms;
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
     * get_symptoms() - Get the array of symptoms for this course_delete_task.
     *
     * @return array
     */
    public function get_symptoms() {
        return $this->symptoms;
    }

    /**
     * is_multi_module_task() - Returns true if there is more than 1 element in $deletemodules array.
     *
     * @return bool
     */
    public function is_multi_module_task() {
        return $this->ismultimoduletask;
    }

    /**
     * adhoctask_is_missing() - Returns true if the adhoc task record is missing from the database.
     *
     * @return bool
     */
    public function adhoctask_is_missing() {
        return $this->adhoctaskmissing;
    }

    /**
     * module_has_missing_data() - Returns true if some kind of data is missing related to the course module.
     *
     * @return bool
     */
    public function module_has_missing_data() {
        return $this->modulehasmissingdata;
    }

}
