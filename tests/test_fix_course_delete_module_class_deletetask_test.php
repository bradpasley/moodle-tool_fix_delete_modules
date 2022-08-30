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
 * @copyright   2022 Catalyst IT
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
     * Test for get/set functions for delete task object.
     *
     * @covers \tool_fix_course_delete_module\delete_task
     */
    public function test_delete_task_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Ensure all adhoc tasks/cache are cleared.
        if (isset(\core\task\manager::$miniqueue)) {
            \core\task\manager::$miniqueue = [];
        } // Clear the cached queue.
        $DB->delete_records('task_adhoc');

        // Setup a course with an assignment and a quiz module.
        $user     = $this->getDataGenerator()->create_user();
        $course   = $this->getDataGenerator()->create_course();
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $assigncontextid = (\context_module::instance($assign->cmid))->id;

        // The module exists in the course.
        $this->assertNotEmpty($DB->get_records('course_modules', array("id" => $assign->cmid)));

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
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $taskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskassign->get_custom_data_as_string()) {
                $taskid = $dbtaskid;
            }
        }
        $deletetask = new delete_task($taskid, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertEquals($assign->id,   current($deletemodules)->moduleinstanceid);
        $this->assertEquals($course->id,   current($deletemodules)->courseid);
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
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $taskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskassign->get_custom_data_as_string()) {
                $taskid = $dbtaskid;
            }
        }
        $deletetask = new delete_task($taskid, json_decode($dbtask->customdata));
        $this->assertTrue($deletetask->task_record_exists());
        unset($deletetask, $dbtask); // Re-set later.

        // Test also with a task with 2 modules, the second being a quiz module.
        $quiz   = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $quizcontextid = (\context_module::instance($quiz->cmid))->id;

        // The quiz module & assign module both exist in the course.
        $this->assertNotEmpty($DB->get_records('course_modules', array("id" => $assign->cmid)));
        $this->assertNotEmpty($DB->get_records('course_modules', array("id" => $quiz->cmid)));

        // Remove previous adhoc task.
        $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $taskid)));
        $DB->delete_records('task_adhoc');
        $this->assertFalse($DB->record_exists('task_adhoc', array('id' => $taskid)));
        $this->assertEmpty($DB->get_records('task_adhoc'));

        // Delete quiz table record to replicate failed course_module_delete adhoc task.
        $this->assertTrue($DB->record_exists('quiz', array('id' => $quizcm->instance)));
        $DB->delete_records('quiz');
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance)));
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
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $taskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskmulti->get_custom_data_as_string()) {
                $taskid = $dbtaskid;
            }
        }
        $deletetask = new delete_task($taskid, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        // Check Assign module.
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertEquals($assign->id,   current($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   current($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $taskid);

        // Check Quiz module.
        $this->assertEquals($quiz->cmid, end($deletemodules)->coursemoduleid);
        $this->assertEquals($quiz->id,   end($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id, end($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $taskid);
        $this->assertTrue(count($deletemodules) > 1); // Should have 2 (i.e. multiple).
        $this->assertTrue($deletetask->is_multi_module_task());

        // Check get ids functions.
        $this->assertEquals([$assign->cmid, $quiz->cmid], $deletetask->get_coursemoduleids());
        $this->assertEquals([$assign->id, $quiz->id], $deletetask->get_moduleinstanceids());
        $this->assertEquals([$assigncontextid, $quizcontextid], $deletetask->get_contextids());
        $this->assertEquals([$assign->cmid => 'assign', $quiz->cmid => 'quiz'], $deletetask->get_modulenames());

        // Check DB status of Modules before execute task.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance)));
        $this->assertTrue($DB->record_exists('assign', array('id' => $assigncm->instance)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm present.

        // Execute task (assign module should complete, quiz should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $removaltaskmulti = \core\task\manager::get_next_adhoc_task($now);
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown = false;
        try {

            $removaltaskmulti->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            \core\task\manager::adhoc_task_failed($removaltaskmulti);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown = $exception; // Run exeception case at end of function.
        }

        // The module has deleted from the course.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $assigncm->instance))); // Now deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm deleted.

        // Test creating a deletetask object after failed adhoc_task run.
        $dbtask = $DB->get_record('task_adhoc', array('id' => $taskid, 'classname' => '\core_course\task\course_delete_modules'));
        $this->assertTrue($dbtask->faildelay > 0); // Should be a failed task.
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        // Check Assign module.
        $this->assertEquals($assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertNull(current($deletemodules)->moduleinstanceid); // Should fail to set from db.
        $this->assertNull(current($deletemodules)->courseid); // Should fail to set from db.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        // Check Quiz module.
        $this->assertEquals($quiz->cmid,   end($deletemodules)->coursemoduleid);
        $this->assertEquals($quiz->id,   end($deletemodules)->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id, end($deletemodules)->courseid); // Should be set via database check.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        $this->assertEquals(2, count($deletemodules));
        $this->assertTrue($deletetask->is_multi_module_task());

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 148");
        }

    }
}
