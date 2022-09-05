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
 * class which diagnoses a Course Module delete task and provides a diagnosis object.
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
 * class which diagnoses a Course Module delete task and provides a diagnosis object.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnoser {
    /**
     * @var diagnosis $diagnosis - the diagnosis (i.e. results of disagnosing the task).
     */
    private $diagnosis;

    /**
     * Establishes symptoms for the given delete_task.
     *
     * @param delete_task $task The adhoc task to be assessed.
     */
    public function __construct(delete_task $task) {

        $symptoms = array();
        // Diagnose any Task issues.
        $symptoms = $this->mergearrays($symptoms, $this->get_multimodule_status($task));
        $symptoms = $this->mergearrays($symptoms, $this->get_missing_task_adhoc_records($task));

        // Only process Modules if there are no task issues.
        if (empty($symptoms)) {
            // Diagnose any Module issues.
            $deletemodules = $task->get_deletemodules();
            foreach ($deletemodules as $deletemodule) {
                $missingmodules = $this->get_missing_module_records($deletemodule);
                $symptoms = $this->mergearrays($symptoms, $missingmodules);
                $missingcms = $this->get_missing_coursemodule_records($deletemodule);
                $symptoms = $this->mergearrays($symptoms, $missingcms);
                $missingcontexts = $this->get_missing_context_records($deletemodule);
                $symptoms = $this->mergearrays($symptoms, $missingcontexts);
            }
        }
        $this->diagnosis = new diagnosis($task, $symptoms);
    }

    /**
     * Get the array of delete_module objects.
     *
     * @return diagnosis
     */
    public function get_diagnosis() {
        return $this->diagnosis;
    }

    /**
     * Returns an array (key: coursemoduleid) if missing in their respective module table.
     *
     * @param delete_module $deletemodule - the module in progress of deletion, to be diagnosed.
     *
     * @return array
     */
    public function get_missing_module_records(delete_module $deletemodule) {
        // If the constructor of delete_module couldn't find the modulename via course_modules table, then it's missing.
        // At least, we don't know what type of module it is (even if the record still exists).
        $symptomstring = get_string('symptom_module_table_record_missing', 'tool_fix_delete_modules');
        if (is_null($modulename = $deletemodule->get_modulename())) {
            return array((string) $deletemodule->coursemoduleid => [$symptomstring]);
        } else {
            global $DB;
            // Check if this module's coursemodule id exists in course_modules table.
            if (!$DB->record_exists($modulename, array('id' => $deletemodule->moduleinstanceid))) {
                return array((string) $deletemodule->coursemoduleid => [$symptomstring]);
            }
        }
        return array();
    }

    /**
     * Returns an array (key: coursemoduleids) if missing from course modules table.
     *
     * @param delete_module $deletemodule - the module in progress of deletion, to be diagnosed.
     *
     * @return array
     */
    public function get_missing_coursemodule_records(delete_module $deletemodule) {
        global $DB;
        // Check if this module's coursemodule id exists in course_modules table.
        if (!$DB->record_exists('course_modules', array('id' => $deletemodule->coursemoduleid))) {
            $symptomstring = get_string('symptom_course_module_table_record_missing', 'tool_fix_delete_modules');
            return array((string) $deletemodule->coursemoduleid => [$symptomstring]);
        }
        return array();
    }

    /**
     * Returns an array (key: coursemoduleids) for any course modules missing in context table.
     *
     * @param delete_module $deletemodule - the task in progress of deletion, to be diagnosed.
     *
     * @return array
     */
    public function get_missing_context_records(delete_module $deletemodule) {
        global $DB;
        $returnarray = array();
        // Check if this module's coursemodule id exists in context table.
        if (!$DB->record_exists('context', array('contextlevel' => '70',
                                                 'instanceid' => $deletemodule->coursemoduleid))) {
            $symptomstring = get_string('symptom_context_table_record_missing', 'tool_fix_delete_modules');
            return array((string) $deletemodule->coursemoduleid => [$symptomstring]);
        }
        return array();
    }

    /**
     * Returns an array of one element if this is a multi-module task.
     *
     * @param delete_task $deletetask - the task in progress of deletion, to be diagnosed.
     *
     * @return array
     */
    public function get_multimodule_status(delete_task $deletetask) {
        if ($deletetask->is_multi_module_task()) {
            $symptomstring = get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules');
            return array($symptomstring => [$symptomstring]);
        }
        return array();
    }

    /**
     * Get an array of coursemoduleids for any course modules missing in context table.
     *
     * @param delete_task $deletetask - the task in progress of deletion, to be diagnosed.
     *
     * @return array
     */
    public function get_missing_task_adhoc_records(delete_task $deletetask) {
        // Check if this module's coursemodule id exists in context table.
        if (!$deletetask->task_record_exists()) {
            // Change element to array if there is already one diagnosis for this module.
            $symptomstring = get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules');
            return array($symptomstring => [$symptomstring]);
        }
        return array();
    }

    /**
     * Takes 2 associative arrays and merges them, retaining their keys
     *
     * @param array $mainarray
     * @param array $newarray
     *
     * @return array
     */
    private function mergearrays(array $mainarray, array $newarray) {

        if (empty($mainarray)) {
            return $newarray;
        }
        if (empty($newarray)) {
            return $mainarray;
        }
        foreach ($newarray as $key => $value) {
            // Each element should be an array associated with one module or task issue.
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    $mainarray[$key][] = $subvalue;
                }
            }
        }
        return $mainarray;
    }
}
