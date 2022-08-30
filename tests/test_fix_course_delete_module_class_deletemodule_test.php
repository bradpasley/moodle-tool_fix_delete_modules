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
require_once(__DIR__ . "/../classes/deletemodule.php");

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
class test_fix_course_delete_module_class_deletemodule_test extends \advanced_testcase {

    /**
     * Test deletion of data after test.
     *
     * @coversNothing
     */
    public function test_deleting() {
        global $DB;
        $this->resetAfterTest(true);
        $DB->delete_records('course');
        $DB->delete_records('course_modules');
        $DB->delete_records('context');
        $DB->delete_records('assign');
        $DB->delete_records('quiz');
        $DB->delete_records('page');
        $DB->delete_records('book');
        $DB->delete_records('url');
        $DB->delete_records('task_adhoc');

        $this->assertEmpty($DB->get_records('course'));
        $this->assertEmpty($DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('context'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('book'));
        $this->assertEmpty($DB->get_records('url'));
        $this->assertEmpty($DB->get_records('task_adhoc'));
    }

    /**
     * Test data reset successfully.
     *
     * @coversNothing
     */
    public function test_user_table_was_reset() {
        global $DB;
        $this->assertEquals(0, $DB->count_records('enrol', array()));
        $this->assertEquals(1, $DB->count_records('course', array()));
        $this->assertEquals(2, $DB->count_records('user', array()));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('book'));
        $this->assertEmpty($DB->get_records('url'));
        $this->assertEmpty($DB->get_records('task_adhoc'));
    }


    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\delete_module
     */
    public function test_delete_module_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Setup a course with an assignment and a quiz module.
        $course    = $this->getDataGenerator()->create_course();
        $modassign = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));

        // Test set/get_modulename() and set/get_contextid() via constructor.
        $deletemodule = new delete_module($modassign->cmid, $modassign->id, $course->id);
        $modcontext = \context_module::instance($modassign->cmid);
        $this->assertEquals('assign', $deletemodule->get_modulename());
        $this->assertEquals($modassign->id, $deletemodule->moduleinstanceid);
        $this->assertEquals($modcontext->id, $deletemodule->get_contextid());
        $this->assertEquals($course->id, $deletemodule->courseid);

        // Test also on a quiz module.
        $modquiz   = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $deletemodule = new delete_module($modquiz->cmid, $modquiz->id, $course->id);
        $modcontext = \context_module::instance($modquiz->cmid);
        $this->assertEquals('quiz', $deletemodule->get_modulename());
        $this->assertEquals($modquiz->id, $deletemodule->moduleinstanceid);
        $this->assertEquals($modcontext->id, $deletemodule->get_contextid());
        $this->assertEquals($course->id, $deletemodule->courseid);
    }
}
