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
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

defined('MOODLE_INTERNAL') || die();
require_once("delete_task.php");
/**
 * class which provides a diagnosis for a failed Course Module delete task.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnosis {
    /** @var delete_task $task - the course_delete_module adhoc task. */
    private $task;
     /** @var string[] $symptoms - an array of strings which describe (coursemoduleid as index). */
    private $symptoms;
     /** @var bool $ismultimoduletask - true if the symptoms included multimodule task in constructor param. */
    private $ismultimoduletask;
     /** @var bool $adhoctaskmissing  - true if the adhoctask record is missing. */
    private $adhoctaskmissing;
     /** @var bool $modulehasmissingdata - true if there is some kind of missing data related to the course module being deleted. */
    private $modulehasmissingdata;

    /**
     * Constructor makes an array of symptoms (i.e. standard strings).
     *
     * @param delete_task $task The course_delete_module task related to the diagnosis.
     * @param array $symptoms An array (moduleids) each with an array of strings which are the issues/symptoms for this delete_task.
     */
    public function __construct(delete_task $task, array $symptoms) {
        $strmultimoduletask = get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules');
        $stradhoctaskmissing = get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules');
        $strmodulerecordmissing = get_string('symptom_module_table_record_missing', 'tool_fix_delete_modules');
        $strcmrecordmissing = get_string('symptom_course_module_table_record_missing', 'tool_fix_delete_modules');
        $strcontextrecordmissing = get_string('symptom_context_table_record_missing', 'tool_fix_delete_modules');
        $strsectionrecordmissing = get_string('symptom_course_section_table_record_missing', 'tool_fix_delete_modules');

        if (in_array($strmultimoduletask, array_values($symptoms))) {
            $this->ismultimoduletask = true;
        } else {
            $this->ismultimoduletask = false;
        }

        if (in_array($stradhoctaskmissing, array_values($symptoms))) {
            $this->adhoctaskmissing = true;
        } else {
            $this->adhoctaskmissing = false;
        }

        // If it's not one of the above, check individual cm symptoms.
        $this->modulehasmissingdata = false;
        if (!in_array($stradhoctaskmissing, array_values($symptoms))
            && !in_array($strmultimoduletask, array_values($symptoms))) {
            foreach ($symptoms as $cmid => $cmsymptoms) {
                if (in_array($strmodulerecordmissing, $cmsymptoms)
                    || in_array($strcmrecordmissing, $cmsymptoms)
                    || in_array($strcontextrecordmissing, $cmsymptoms)
                    || in_array($strsectionrecordmissing, $cmsymptoms)) {
                        $this->modulehasmissingdata = true;
                }
            }
        }

        $this->task = $task;
        $this->symptoms = $symptoms;
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
     * Get the array of symptoms for this course_delete_task.
     *
     * @return array
     */
    public function get_symptoms() {
        return $this->symptoms;
    }

    /**
     * Returns true if there is more than 1 element in $deletemodules array.
     *
     * @return bool
     */
    public function is_multi_module_task() {
        return $this->ismultimoduletask;
    }

    /**
     * Returns true if the adhoc task record is missing from the database.
     *
     * @return bool
     */
    public function adhoctask_is_missing() {
        return $this->adhoctaskmissing;
    }

    /**
     * Returns true if some kind of data is missing related to the course module.
     *
     * @return bool
     */
    public function module_has_missing_data() {
        return $this->modulehasmissingdata;
    }

}
