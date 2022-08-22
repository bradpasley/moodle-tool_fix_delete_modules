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
 * class to define a single Course Module which is in the progress of being deleted.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;
/**
 * class to define a single Course Module which is in the progress of being deleted.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_module {
    /** @var int $taskid - the course_delete_module adhoc task id for this course module. */
    public $taskid;
     /** @var int $coursemoduleid - the course_module table record id.*/
    public $coursemoduleid;
     /** @var ?int $moduleinstanceid - this module's record id for its specific module type table (e.g. quiz table or book table).*/
    public $moduleinstanceid;
     /** @var ?int $courseid - the courseid of the course in which this module is situated. */
    public $courseid;
    /** @var ?int $section - course section of this module. */
    public $section;
    /** @var ?int $modulecontextid - contextid for this module*/
    private $modulecontextid;
    /** @var ?string $modulename - the type of this module. */
    private $modulename;

    /**
     * Constructor makes an array of delete_tasks objects.
     *
     * @param int $taskid The taskid (aka id field for {task_adhoc} table)
     * @param int $coursemoduleid The course_module record id for the module being deleted.
     * @param int $moduleinstanceid The moduleinstance id for the module being deleted.
     * @param int $courseid The course id in which the module being deleted is situated.
     * @param int $section The section id for the module being deleted.
     */
    public function __construct(int $taskid,
                                int $coursemoduleid,
                                ?int $moduleinstanceid = null,
                                ?int $courseid = null,
                                ?int $section = null) {
        $this->taskid = $taskid;
        $this->coursemoduleid = $coursemoduleid;
        $this->moduleinstanceid = $moduleinstanceid;
        $this->courseid = $courseid;
        $this->section = $section;
        $this->set_contextid($this->coursemoduleid);
        $this->set_modulename($this->coursemoduleid, $this->moduleinstanceid);
    }

    /**
     * get_contextid() - Get the name of the table related to the course module which is failing to be deleted.
     *
     * @return string
     */
    public function get_contextid() {
        return $this->modulecontextid;
    }


    /**
     * get_modulename() - Get the name of the table related to the course module which is failing to be deleted.
     *
     * @return string
     */
    public function get_modulename() {
        return $this->modulename;
    }

    /**
     * set_contextid() - Set the contextid of the module, retrived from the database record.
     *
     * @param int $coursemoduleid - course module instance id.
     *
     * @return null
     */
    public function set_contextid(int $coursemoduleid) {
        if (isset($coursemoduleid)) {
            global $DB;

            if ($result = $DB->get_records('context', array('contextlevel' => '70', 'instanceid' => $coursemoduleid))) {
                $this->modulecontextid = current($result)->id;
            } else {
                $this->modulecontextid = null;
            }
        }
    }

    /**
     * set_modulename()-  Set the name of the table related to the course module which is failing to be deleted.
     * Retrived from the database record.
     *
     * @param int $coursemoduleid - coursemodule id.
     * @param int $moduleinstanceid - moduleinstance id.
     *
     * @return null
     */
    public function set_modulename(int $coursemoduleid, ?int $moduleinstanceid = null) {
        global $DB;
        // First get moduleid.
        if (!isset($moduleinstanceid)) {
            $queryarray = array('id' => $coursemoduleid);
        } else {
            $queryarray = array('id' => $coursemoduleid, 'instance' => $moduleinstanceid);
        }

        if ($cmrecord = $DB->get_records('course_modules', $queryarray, '', 'module')) {
            $moduleid = current($cmrecord)->module;
            if ($result = $DB->get_records('modules', array('id' => $moduleid), '', 'name')) {
                $this->modulename = current($result)->name;
            } else {
                $this->modulename = null;
            }
        } else { // No way to know which module it is without a course_module record.
            $this->modulename = null;
        }
    }

}
