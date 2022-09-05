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
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;
/**
 * class to define a single Course Module which is in the progress of being deleted.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_module {
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
     * @param int $coursemoduleid The course_module record id for the module being deleted.
     * @param int $moduleinstanceid The moduleinstance id for the module being deleted.
     * @param int $courseid The course id in which the module being deleted is situated.
     * @param int $section The section id for the module being deleted.
     */
    public function __construct(int $coursemoduleid,
                                ?int $moduleinstanceid = null,
                                ?int $courseid = null,
                                ?int $section = null) {
        $this->coursemoduleid = $coursemoduleid;
        $this->moduleinstanceid = $moduleinstanceid;
        $this->courseid = $courseid;
        $this->section = $section;
        $this->set_contextid();
        $this->set_modulename();
    }

    /**
     * Get the contextid of the course module which is failing to be deleted.
     *
     * @return int
     */
    public function get_contextid() {
        return $this->modulecontextid;
    }


    /**
     * Get the module's name, which is also the table related to the course module (e.q. 'quiz' or 'assign').
     *
     * @return string
     */
    public function get_modulename() {
        return $this->modulename;
    }

    /**
     * Set the contextid of the module, retrived from the database record.
     *
     * @return void
     */
    private function set_contextid() {
        if (isset($this->coursemoduleid)) {
            try {
                $this->modulecontextid = \context_module::instance($this->coursemoduleid)->id;
            } catch (\dml_missing_record_exception $e) {
                global $DB;
                if ($result = $DB->get_records('context', array('contextlevel' => '70', 'instanceid' => $this->coursemoduleid))) {
                    $this->modulecontextid = current($result)->id;
                } else {
                    $this->modulecontextid = null;
                }
            }
        }
    }

    /**
     * Set the name of this module, which is also the table related to the course module (e.g. 'quiz' or 'assign').
     * Retrived from the database record.
     *
     * @return void
     */
    private function set_modulename() {
        global $DB;
        // First get moduleid.
        if (!isset($this->moduleinstanceid)) {
            $queryarray = array('id' => $this->coursemoduleid);
        } else {
            $queryarray = array('id' => $this->coursemoduleid, 'instance' => $this->moduleinstanceid);
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
