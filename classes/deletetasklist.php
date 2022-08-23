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
 * class to define an list of Course Module delete tasks.
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
 * class to define an list of Course Module delete tasks.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_task_list {
    /** @var int $minimumfaildelay - only include adhoc tasks with a faildelay field value with at least this value. */
    private $minimumfaildelay;
    /** @var array $deletetasks - an array of delete_module objects. */
    private $deletetasks;

    /**
     * Constructor makes an array of delete_tasks objects.
     *
     * @param int $minimumfaildelay The minimum value (seconds) for the faildelay field of the adhoc task.
     */
    public function __construct(int $minimumfaildelay = 60) {
        $this->minimumfaildelay = $minimumfaildelay;
        $this->set_deletetasks();
    }

    /**
     * get_deletetasks() - Get the array of delete_task objects.
     *
     * @return array
     */
    public function get_deletetasks() {
        return $this->deletetasks;
    }

    /**
     * set_deletetasks() - Set the deletetasks array.
     *
     */
    public function set_deletetasks() {
        $this->deletetasks = array();
        $cdmadhoctasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        foreach ($cdmadhoctasks as $taskid => $cdadhoctask) {
            if ($cdadhoctask->get_fail_delay() >= $this->minimumfaildelay) {
                $customdata = $cdadhoctask->get_custom_data();
                $dt = new delete_task($taskid, $customdata);
                $this->deletetasks["$taskid"] = $dt;
            }
        }
    }
}
