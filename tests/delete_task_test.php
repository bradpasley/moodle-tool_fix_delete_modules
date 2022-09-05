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
require_once(__DIR__ . "/../classes/delete_task.php");
require_once("fix_course_delete_module_test.php");

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
class delete_task_test extends fix_course_delete_module_test {

    /**
     * Test for get/set functions for delete task object.
     *
     * @covers \tool_fix_course_delete_module\delete_task
     */
    public function test_delete_task_class() {
        global $DB;

        // Queue assign adhoc task.
        \core\task\manager::queue_adhoc_task($this->removaltaskassign);

        // Test creating a deletetask object.
        $taskid = $this->find_taskid($this->removaltaskassign);
        $deletetask = new delete_task($taskid, json_decode($this->removaltaskassign->get_custom_data_as_string()));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        $this->assertEquals($this->assign->cmid, current($deletemodules)->coursemoduleid);
        $this->assertEquals($this->assign->id,   current($deletemodules)->moduleinstanceid);
        $this->assertEquals($this->course->id,   current($deletemodules)->courseid);
        $this->assertEquals($taskid, $deletetask->taskid);
        $this->assertCount(1, $deletemodules);
        $this->assertFalse($deletetask->is_multi_module_task());

        // Test task currently exists.
        $this->assertTrue($deletetask->task_record_exists());

        // Delete task and check false (this is not part of the flow, but testing the function).
        $DB->delete_records('task_adhoc', array('id' => $deletetask->taskid));
        $this->assertFalse($deletetask->task_record_exists());

        // Re-create and test again.
        \core\task\manager::queue_adhoc_task($this->removaltaskassign);
        $taskid = $this->find_taskid($this->removaltaskassign);
        $deletetask = new delete_task($taskid, json_decode($this->removaltaskassign->get_custom_data_as_string()));
        $this->assertTrue($deletetask->task_record_exists());
        unset($deletetask, $dbtask); // Re-set later.

        // Remove previous adhoc task.
        $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $taskid)));
        $DB->delete_records('task_adhoc');
        $this->assertFalse($DB->record_exists('task_adhoc', array('id' => $taskid)));
        $this->assertEmpty($DB->get_records('task_adhoc'));

        // Queue multi-module adhoc task.
        \core\task\manager::queue_adhoc_task($this->removaltaskmulti);

        // Test creating a deletetask object.
        $taskid = $this->find_taskid($this->removaltaskmulti);
        $deletetask = new delete_task($taskid, json_decode($this->removaltaskmulti->get_custom_data_as_string()));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        foreach ($deletemodules as $deletemodule) {
            if ($deletemodule->coursemoduleid == $this->assign->cmid) {
                $assignmodule = $deletemodule;
            } else if ($deletemodule->coursemoduleid == $this->quiz->cmid) {
                $quizmodule = $deletemodule;
            }
        }

        // Check task.
        $this->assertEquals($deletetask->taskid, $taskid);
        $this->assertTrue($deletetask->is_multi_module_task());
        $this->assertTrue(count($deletemodules) > 1); // Should have 2 (i.e. multiple).
        // Check Assign module.
        $this->assertEquals($this->assign->cmid, $assignmodule->coursemoduleid);
        $this->assertEquals($this->assign->id,   $assignmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id,   $assignmodule->courseid); // Should be set via database check.
        // Check Quiz module.
        $this->assertEquals($this->quiz->cmid, $quizmodule->coursemoduleid);
        $this->assertEquals($this->quiz->id,   $quizmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id, $quizmodule->courseid); // Should be set via database check.

        // Check get ids functions.
        $this->assertEquals([$this->assign->cmid, $this->quiz->cmid], $deletetask->get_coursemoduleids());
        $this->assertEquals([$this->assign->id, $this->quiz->id], $deletetask->get_moduleinstanceids());
        $this->assertEquals([$this->assigncontextid, $this->quizcontextid], $deletetask->get_contextids());
        $this->assertEquals([$this->assign->cmid => 'assign', $this->quiz->cmid => 'quiz'], $deletetask->get_modulenames());

        // Check DB status of Modules before execute task.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance)));
        $this->assertTrue($DB->record_exists('assign', array('id' => $this->assigncm->instance)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm present.

        // Execute task (assign module should complete, quiz should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $this->removaltaskmulti = \core\task\manager::get_next_adhoc_task($now);
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown = false;
        try {
            $this->removaltaskmulti->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            \core\task\manager::adhoc_task_failed($this->removaltaskmulti);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown = $exception; // Run exeception case at end of function.
        }

        // The module has deleted from the course.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance))); // Now deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm deleted.

        // Test creating a deletetask object after failed adhoc_task run.
        $dbtask = $DB->get_record('task_adhoc', array('id' => $taskid, 'classname' => '\core_course\task\course_delete_modules'));
        $this->assertTrue($dbtask->faildelay > 0); // Should be a failed task.
        $deletetask = new delete_task($dbtask->id, json_decode($dbtask->customdata));

        $deletemodules = array();
        $deletemodules = $deletetask->get_deletemodules();
        foreach ($deletemodules as $deletemodule) {
            if ($deletemodule->coursemoduleid == $this->assign->cmid) {
                $assignmodule = $deletemodule;
            } else if ($deletemodule->coursemoduleid == $this->quiz->cmid) {
                $quizmodule = $deletemodule;
            }
        }
        // Check task.
        $this->assertEquals($deletetask->taskid, $dbtask->id);
        $this->assertTrue($deletetask->is_multi_module_task());
        $this->assertEquals(2, count($deletemodules));
        // Check Assign module.
        $this->assertEquals($this->assign->cmid, $assignmodule->coursemoduleid);
        $this->assertNull($assignmodule->moduleinstanceid); // Should fail to set from db.
        $this->assertNull($assignmodule->courseid); // Should fail to set from db.
        // Check Quiz module.
        $this->assertEquals($this->quiz->cmid,   $quizmodule->coursemoduleid);
        $this->assertEquals($this->quiz->id,   $quizmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id, $quizmodule->courseid); // Should be set via database check.

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 148");
        }

    }
}
