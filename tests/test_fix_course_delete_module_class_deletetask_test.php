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

use Exception;
use tool_fix_delete_modules\delete_task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/deletetask.php");

/**
 * The test_fix_course_delete_module_class_delete_module test class.
 *
 * Tests for the delete_task class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class test_fix_course_delete_module_class_deletetask_test extends \advanced_testcase {

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
        $DB->delete_records('user');
        $DB->delete_records('task_adhoc');
        $this->assertEmpty($DB->get_records('course'));
        $this->assertEmpty($DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('context'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEmpty($DB->get_records('user'));
        $this->assertEmpty($DB->get_records('task_adhoc'));
    }

    /**
     * Test for get/set functions for delete task object.
     *
     * @covers \tool_fix_course_delete_module\delete_task
     */
    public function test_delete_task_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Setup a course with an assignment and a quiz module.
        $user     = $this->getDataGenerator()->create_user();
        $course   = $this->getDataGenerator()->create_course();
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $assigncontextid = (\context_module::instance($assign->cmid))->id;

        // The module exists in the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(1, $coursedmodules);

        // Setup adhoc task.
        $removaltaskassign = new \core_course\task\course_delete_modules();
        $assigndata = [
            'cms' => [$assigncm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskassign->set_custom_data($assigndata);
        \core\task\manager::queue_adhoc_task($removaltaskassign);

        // Test creating a deletetask object.
        $dbtask = $DB->get_record('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertEquals($assign->id,   current($deletemodules)->moduleinstanceid);
        $this->assertEquals($course->id,   current($deletemodules)->courseid);
        $this->assertEquals($dbtask->id,   current($deletemodules)->taskid);
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        $this->assertCount(1, $deletemodules);
        $this->assertFalse($deletetask->is_multi_module_task());

        // Test task currently exists.
        $this->assertTrue($deletetask->task_record_exists());

        // Delete task and check false (this is not part of the flow, but testing the function).
        $DB->delete_records('task_adhoc', array('id' => $deletetask->taskid));
        $this->assertFalse($deletetask->task_record_exists());

        // Re-create and test again.
        \core\task\manager::queue_adhoc_task($removaltaskassign);
        $dbtask = $DB->get_record('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));
        $this->assertTrue($deletetask->task_record_exists());
        unset($deletetask, $dbtask); // Re-set later.

        // Test also with a task with 2 modules, the second being a quiz module.
        $quiz   = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $quizcontextid = (\context_module::instance($quiz->cmid))->id;

        // The module exists in the course (now 2 because assign not yet deleted).
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(2, $coursedmodules);

        // Remove previous adhoc task.
        $this->assertCount(1, $DB->get_records('task_adhoc'));
        $DB->delete_records('task_adhoc');
        $this->assertEmpty($DB->get_records('task_adhoc'));

        // Delete quiz table record to replicate failed course_module_delete adhoc task.
        $this->assertCount(1, $DB->get_records('quiz'));
        $DB->delete_records('quiz');
        $this->assertEmpty($DB->get_records('quiz'));

        // Setup adhoc task.
        $removaltaskmulti = new \core_course\task\course_delete_modules();
        $cmsarray = array(''.$assigncm->id => array('id' => $assigncm->id),
                          ''.$quizcm->id   => array('id' => $quizcm->id));
        $multidata = [
            'cms' => $cmsarray,
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskmulti->set_custom_data($multidata);
        \core\task\manager::queue_adhoc_task($removaltaskmulti);

        // Test creating a deletetask object.
        $dbtask = $DB->get_record('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        // Check Assign module.
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertEquals($assign->id,   current($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($dbtask->id,   current($deletemodules)->taskid);
        $this->assertEquals($course->id,   current($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        // Check Quiz module.
        $this->assertEquals($quiz->cmid, end($deletemodules)->coursemoduleid);
        $this->assertEquals($quiz->id,   end($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($dbtask->id, end($deletemodules)->taskid);
        $this->assertEquals($course->id, end($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        $this->assertEquals(2, count($deletemodules));
        $this->assertTrue($deletetask->is_multi_module_task());

        // Check get ids functions.
        $this->assertEquals([$assign->cmid, $quiz->cmid], $deletetask->get_coursemoduleids());
        $this->assertEquals([$assign->id, $quiz->id], $deletetask->get_moduleinstanceids());
        $this->assertEquals([$assigncontextid, $quizcontextid], $deletetask->get_contextids());
        $this->assertEquals([$assign->cmid => 'assign', $quiz->cmid => 'quiz'], $deletetask->get_modulenames());

        // Execute tasks (first one should complete, second should fail).
        try { // This will fail due to the quiz record already being deleted.
            $removaltaskmulti->execute();
        } catch (Exception $e) {
            $this->assertCount(1, $DB->get_records('task_adhoc'));
        }
        // The module has deleted from the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(1, $coursedmodules);

        // Test creating a deletetask object after failed adhoc_task run.
        $dbtask = $DB->get_record('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        // Check Assign module.
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertNull(current($deletemodules)->moduleinstanceid); // Should fail to set from db.
        $this->assertEquals($dbtask->id,   current($deletemodules)->taskid);
        $this->assertNull(current($deletemodules)->courseid); // Should fail to set from db.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        // Check Quiz module.
        $this->assertEquals($quiz->cmid,   end($deletemodules)->coursemoduleid);
        $this->assertEquals($quiz->id,   end($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($dbtask->id,   end($deletemodules)->taskid);
        $this->assertEquals($course->id, end($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        $this->assertEquals(2, count($deletemodules));
        $this->assertTrue($deletetask->is_multi_module_task());
    }
}
