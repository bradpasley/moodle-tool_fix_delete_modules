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
use tool_fix_delete_modules\delete_task_list;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/deletetasklist.php");
require_once("test_fix_course_delete_module_test.php");

/**
 * The test_fix_course_delete_module_class_delete_module test class.
 *
 * Tests for the delete_task_list class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_fix_course_delete_module_class_deletetasklist_test extends test_fix_course_delete_module_test {

    /**
     * Test for get/set functions for delete task list object.
     *
     * @covers \tool_fix_course_delete_module\delete_task_list
     */
    public function test_delete_task_list_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Queue adhoc task for a multi-module delete (both quiz and assign).
        \core\task\manager::queue_adhoc_task($this->removaltaskmulti);

        // The pre-execute status of modules.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('assign', array('id' => $this->assigncm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('book', array('id' => $this->bookcm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm exists.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->bookcm->id))); // Book cm exists.

        // Check creation of deletetask list only with multimodule taskm before execution.
        // Test creating a deletetasklist object.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = $this->find_taskid($this->removaltaskmulti);
        // Include all tasks (even 0 fail delay).
        $deletetasklist = new delete_task_list(0);
        $deletetasks    = $deletetasklist->get_deletetasks();

        $deletemultitask = null;
        foreach ($deletetasks as $dt) {
            if ($dt->is_multi_module_task()) {
                $deletemultitask = $dt;
            }
        }
        $this->assertNotNull($deletemultitask);

        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        foreach ($deletemodulesmulti as $deletemodule) {
            if ($deletemodule->coursemoduleid == $this->assign->cmid) {
                $assignmodule = $deletemodule;
            } else if ($deletemodule->coursemoduleid == $this->quiz->cmid) {
                $quizmodule = $deletemodule;
            }
        }

        // Check the multi mod deletion task.
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($this->assign->cmid, $assignmodule->coursemoduleid);
        $this->assertEquals($this->assign->id,   $assignmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id,   $assignmodule->courseid); // Should be set via database check.
        $this->assertEquals($this->quiz->cmid,   $quizmodule->coursemoduleid);
        $this->assertEquals($this->quiz->id,     $quizmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id,   $quizmodule->courseid); // Should be set via database check.
        $this->assertEquals($multitaskid, $deletemultitask->taskid);

        // Execute tasks (which should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $removaltaskmulti = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multitask adhoc task.
        $this->assertEquals($multitaskid, $removaltaskmulti->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));

        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown181 = false;
        try {
            $removaltaskmulti->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            \core\task\manager::adhoc_task_failed($removaltaskmulti);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown181 = $exception; // Run exeception case at end of function.
        }

        // The assign module has deleted from the course.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance))); // Now deleted.
        $this->assertTrue($DB->record_exists('book', array('id' => $this->bookcm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->bookcm->id))); // Assign cm deleted.

        // Queue adhoc task for book module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltaskbook);

        // Test creating a deletetasklist object.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = $this->find_taskid($this->removaltaskmulti);
        $booktaskid = $this->find_taskid($this->removaltaskbook);

        // Include all tasks (even 0 fail delay).
        $deletetasklist = new delete_task_list(0);
        $deletetasks    = $deletetasklist->get_deletetasks();

        foreach ($deletetasks as $dt) {
            if ($dt->is_multi_module_task()) {
                $deletemultitask = $dt;
            } else { // Only one module in task.
                $deletemodule = current($dt->get_deletemodules());
                if ($deletemodule->get_modulename() == 'book') {
                    $deletebooktask  = $dt;
                }
            }
        }

        $deletemodulesbook  = $deletebooktask->get_deletemodules();
        $dmbook             = current($deletemodulesbook);
        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        foreach ($deletemodulesmulti as $deletemodule) {
            if ($deletemodule->coursemoduleid == $this->assign->cmid) {
                $assignmodule = $deletemodule;
            } else if ($deletemodule->coursemoduleid == $this->quiz->cmid) {
                $quizmodule = $deletemodule;
            }
        }
        // Check the first task (book mod deletion).
        $this->assertCount(1, $deletemodulesbook);
        $this->assertFalse($deletebooktask->is_multi_module_task());
        $this->assertEquals($this->book->cmid, $dmbook->coursemoduleid);
        $this->assertEquals($this->book->id,   $dmbook->moduleinstanceid);
        $this->assertEquals($booktaskid, $deletebooktask->taskid);

        // Check the second task (multi mod deletion).
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($this->assign->cmid, $assignmodule->coursemoduleid);
        $this->assertNull($assignmodule->moduleinstanceid); // Should be gone (deleted).
        $this->assertNull($assignmodule->courseid); // Should be gone (deleted).
        $this->assertEquals($this->quiz->cmid,   $quizmodule->coursemoduleid);
        $this->assertEquals($this->quiz->id,     $quizmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id,   $quizmodule->courseid); // Should be set via database check.
        $this->assertEquals($multitaskid, $deletemultitask->taskid);

        // Execute book task - should execute successfully.
        $now = time();
        $this->removaltaskbook = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the book adhoc task (should be due to faildelay of multitask).
        $this->assertEquals($booktaskid, $this->removaltaskbook->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        $this->removaltaskbook->execute();
        // Replicate completed task.
        $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
        \core\task\manager::adhoc_task_complete($this->removaltaskbook);
        $this->assertCount($adhoctaskprecount - 1, $DB->get_records('task_adhoc'));

        // The assign module has deleted from the course.
        // ... quiz are still thought to be present.
        // ... book has not been deleted.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance))); // Now deleted.
        $this->assertFalse($DB->record_exists('book', array('id' => $this->bookcm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm deleted.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->bookcm->id))); // Book just deleted.

        // Test creating a deletetasklist object after failed adhoc_task run.

        // Check faildelay fields for testing.
        $multitaskfaildelay = $DB->get_field('task_adhoc', 'faildelay', array('id' => $deletemultitask->taskid));
        $this->assertEquals('60', $multitaskfaildelay);

        // Confirm only multi-module task remains.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(1, $dbtasks);
        $this->assertTrue($DB->record_exists('task_adhoc',
                                             array('id' => $deletemultitask->taskid,
                                                   'classname' => '\core_course\task\course_delete_modules')));

        // Include only tasks with minimum faildelay of 60.
        $deletetasklist = new delete_task_list();

        $deletetasks        = array();
        $deletetasks        = $deletetasklist->get_deletetasks();
        // Book task shouldn't be included due to faildelay filter.
        $this->assertCount(1, $deletetasks);
        $deletemultitask    = current($deletetasks);
        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        $this->assertCount(2, $deletemodulesmulti);
        foreach ($deletemodulesmulti as $deletemodule) {
            if ($deletemodule->coursemoduleid == $this->assign->cmid) {
                $assignmodule = $deletemodule;
            } else if ($deletemodule->coursemoduleid == $this->quiz->cmid) {
                $quizmodule = $deletemodule;
            }
        }
        // Check the second task (multi mod deletion).
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($this->assign->cmid, $assignmodule->coursemoduleid);
        $this->assertNull($assignmodule->moduleinstanceid); // Null on muli-mod delete.
        $this->assertEquals($this->quiz->cmid, $quizmodule->coursemoduleid);
        $this->assertEquals($this->quiz->id,     $quizmodule->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($this->course->id,   $quizmodule->courseid); // Should be set via database check.
        $this->assertEquals(end($dbtasks)->id, end($deletetasks)->taskid);

        if ($exceptionthrown181) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown181;
        } else {
            $this->assertTrue($exceptionthrown181, "Expected Exception wasn't thrown for line 181");
        }

    }
}
