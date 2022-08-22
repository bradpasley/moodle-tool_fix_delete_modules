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
 * class to define a Course Module delete task, which can contain one or many Modules in the process of being deleted.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

defined('MOODLE_INTERNAL') || die();
require_once("deletemodule.php");
/**
 * class to define a Course Module delete task, which can contain one or many Modules in the process of being deleted.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_task {
    /**
     * @var int $taskid - the course_delete_module adhoc task id for this course module.
     * @var array $deletemodules - an array of delete_module objects.
     */
    public $taskid;
    private $deletemodules;

    public function __construct(int $taskid, \stdClass $customdata) {
        $this->taskid = $taskid;
        $this->set_deletemodules_from_customdata($customdata);
    }

    /**
     * get_deletemodules() - Get the array of delete_module objects.
     *
     * @return array
     */
    public function get_deletemodules() {
        return $this->deletemodules;
    }

    /**
     * get_coursemoduleids() - Get each coursemoduleid for this task.
     *
     * @return array
     */
    public function get_coursemoduleids() {
        $cmids = array();
        foreach ($this->deletemodules as $dm) {
            $cmids[] = $dm->coursemoduleid;
        }
        return $cmids;
    }

    /**
     * get_moduleinstanceids() - Get each moduleinstanceid for this task.
     *
     * @return array
     */
    public function get_moduleinstanceids() {
        $instanceids = array();
        foreach ($this->deletemodules as $dm) {
            $instanceids[] = $dm->moduleinstanceid;
        }
        return $instanceids;
    }

    /**
     * get_courseids() - Get each module's course id for this task.
     *
     * @param bool $uniqueids - return only unique ids (false to return all).
     * @param bool $skipnulls - true to skip, false to include.

     * @return array
     */
    public function get_courseids(bool $uniqueids = true, bool $skipnulls = true) {
        $courseids = array();
        foreach ($this->deletemodules as $dm) {
            if (!$skipnulls || isset($dm->courseid)) {
                $courseids[$dm->coursemoduleid] = $dm->courseid;
            }
        }
        return $uniqueids ? array_unique($courseids) : $courseids;
    }

    /**
     * get_contextids() - Get each contextid for this task.
     *
     * @return array
     */
    public function get_contextids() {
        $contextids = array();
        foreach ($this->deletemodules as $dm) {
            $contextids[] = $dm->get_contextid();
        }
        return $contextids;
    }

    /**
     * get_modulenames() - Get each modulename (key is coursemoduleid) for this task.
     *
     * @param bool $uniquenames - return only unique names (false to return all).
     * @param bool $skipnulls - true to skip, false to include.
     * @param string $namefilter - only include a certain modulename. empty string ignores this.
     * @return array
     */
    public function get_modulenames(bool $uniquenames = true, bool $skipnulls = true, string $namefilter = '') {
        $modulenames = array();
        foreach ($this->deletemodules as $dm) {
            $modulename = $dm->get_modulename();
            if (!$skipnulls || isset($modulename)) {
                if ($namefilter == '' || $modulename == $namefilter) {
                    $modulenames[$dm->coursemoduleid] = $modulename;
                }
            }
        }
        return $uniquenames ? array_unique($modulenames) : $modulenames;
    }

    /**
     * is_multi_module_task() - Returns true if there is more than 1 element in $deletemodules array.
     *
     * @return bool
     */
    public function is_multi_module_task() {
        return count($this->deletemodules) > 1;
    }

    /**
     * task_record_exists() - Returns true if the task record currently exists in the database.
     *
     * @return bool
     */
    public function task_record_exists() {
        global $DB;
        return $DB->record_exists('task_adhoc', array('id' => $this->taskid));
    }

    /**
     * set_deletemodules_from_customdata() - Set the deletemodules array, retrived customdata objects.
     *
     * @param \stdClass $customdata - stdClass obect (customdata) retrieved from an adhoc_task object.
     */
    public function set_deletemodules_from_customdata(\stdClass $customdata) {
        global $DB;
        $cms = (array) $customdata->cms;
        $this->deletemodules = array();
        foreach ($cms as $cmdata) {
            $instanceid = isset($cmdata->instance) ? $cmdata->instance : null;
            if (!isset($instanceid)) { // Attempt to retrieve from database.
                if ($record = $DB->get_field('course_modules', 'instance', array('id' => $cmdata->id))) {
                    $instanceid = $record;
                }
            }
            $courseid = isset($cmdata->course) ? $cmdata->course : null;
            if (!isset($courseid)) { // Attempt to retrieve from database.
                if ($record = $DB->get_field('course_modules', 'course', array('id' => $cmdata->id))) {
                    $courseid = $record;
                }
            }
            $section = isset($cmdata->section) ? $cmdata->section : null;
            if (!isset($section)) { // Attempt to retrieve from database.
                if ($record = $DB->get_field('course_modules', 'section', array('id' => $cmdata->id))) {
                    $section = $record;
                }
            }
            $dm = new delete_module($this->taskid, $cmdata->id, $instanceid, $courseid, $section);
            $this->deletemodules[''.$cmdata->id] = $dm;
        }

    }
}
