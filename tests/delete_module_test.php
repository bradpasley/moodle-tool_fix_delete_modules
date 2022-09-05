<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_fix_delete_modules;

use tool_fix_delete_modules\delete_module;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/delete_module.php");
require_once("fix_course_delete_module_test.php");

/**
 * The test_fix_course_delete_module_class_deletemodule test class.
 *
 * Tests for the delete_module class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_module_test extends fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\delete_module
     */
    public function test_delete_module_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Test set/get_modulename() and set/get_contextid() via constructor.
        $deletemodule = new delete_module($this->assign->cmid, $this->assign->id, $this->course->id);
        $modcontext = \context_module::instance($this->assign->cmid);
        $this->assertEquals('assign', $deletemodule->get_modulename());
        $this->assertEquals($this->assign->id, $deletemodule->moduleinstanceid);
        $this->assertEquals($modcontext->id, $deletemodule->get_contextid());
        $this->assertEquals($this->course->id, $deletemodule->courseid);

        // Test also on a quiz module.
        $deletemodule = new delete_module($this->quiz->cmid, $this->quiz->id, $this->course->id);
        $modcontext = \context_module::instance($this->quiz->cmid);
        $this->assertEquals('quiz', $deletemodule->get_modulename());
        $this->assertEquals($this->quiz->id, $deletemodule->moduleinstanceid);
        $this->assertEquals($modcontext->id, $deletemodule->get_contextid());
        $this->assertEquals($this->course->id, $deletemodule->courseid);
    }
}
