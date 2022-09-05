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
require_once("delete_task.php");
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
