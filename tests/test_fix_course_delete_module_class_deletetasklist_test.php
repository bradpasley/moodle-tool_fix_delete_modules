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
class test_fix_course_delete_module_class_deletetasklist_test extends \advanced_testcase {

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
     * Test for get/set functions for delete task list object.
     *
     * @covers \tool_fix_course_delete_module\delete_task_list
     */
    public function test_delete_task_list_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Ensure all adhoc tasks/cache are cleared.
        if (isset(\core\task\manager::$miniqueue)) {
            \core\task\manager::$miniqueue = [];
        } // Clear the cached queue.
        $DB->delete_records('task_adhoc');

        // Setup a course with a page, an assignment and a quiz module.
        $user     = $this->getDataGenerator()->create_user();
        $course   = $this->getDataGenerator()->create_course();
        $page     = $this->getDataGenerator()->create_module('page', array('course' => $course->id));
        $pagecm = get_coursemodule_from_id('page', $page->cmid);
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $quiz   = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm = get_coursemodule_from_id('quiz', $quiz->cmid);

        // The module exists in the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(3, $coursedmodules);

        // Delete quiz table record to replicate failed course_module_delete adhoc task.
        $this->assertCount(1, $DB->get_records('quiz'));
        $DB->delete_records('quiz');
        $this->assertEmpty($DB->get_records('quiz'));

        // Setup adhoc task for a multi-module delete (both quiz and assign).
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

        // The pre-execute status of modules.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('assign', array('id' => $assigncm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('page', array('id' => $pagecm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm exists.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $pagecm->id))); // Page cm exists.

        // Check creation of deletetask list only with multimodule taskm before execution.
        // Test creating a deletetasklist object.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskmulti->get_custom_data_as_string()) {
                $multitaskid = $dbtaskid;
            }
        }
        // Include all tasks (even 0 fail delay).
        $deletetasklist = new delete_task_list(0);
        $deletetasks    = $deletetasklist->get_deletetasks();

        $deletepagetask = null;
        foreach ($deletetasks as $dt) {
            if ($dt->is_multi_module_task()) {
                $deletemultitask = $dt;
            }
        }
        $this->assertNotNull($deletemultitask);

        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        $dmassign           = current($deletemodulesmulti);
        $dmquiz             = end($deletemodulesmulti);

        // Check the multi mod deletion task.
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($assign->cmid, $dmassign->coursemoduleid);
        $this->assertEquals($assign->id,   $dmassign->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmassign->courseid); // Should be set via database check.
        $this->assertEquals($quiz->cmid,   $dmquiz->coursemoduleid);
        $this->assertEquals($quiz->id,     $dmquiz->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmquiz->courseid); // Should be set via database check.
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
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $assigncm->instance))); // Now deleted.
        $this->assertTrue($DB->record_exists('page', array('id' => $pagecm->instance))); // Not yet deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $pagecm->id))); // Assign cm deleted.

        // Setup adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Test creating a deletetasklist object.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = 0;
        $pagetaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskmulti->get_custom_data_as_string()) {
                $multitaskid = $dbtaskid;
            } else if ($dbtask->customdata === $removaltaskpage->get_custom_data_as_string()) {
                $pagetaskid = $dbtaskid;
            }
        }

        // Include all tasks (even 0 fail delay).
        $deletetasklist = new delete_task_list(0);

        $deletetasks        = array();
        $deletetasks        = $deletetasklist->get_deletetasks();

        foreach ($deletetasks as $dt) {
            if ($dt->is_multi_module_task()) {
                $deletemultitask = $dt;
            } else { // Only one module in task.
                $deletemodule = current($dt->get_deletemodules());
                if ($deletemodule->get_modulename() == 'page') {
                    $deletepagetask  = $dt;
                }
            }
        }

        $deletemodulespage  = $deletepagetask->get_deletemodules();
        $dmpage             = current($deletemodulespage);
        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        $dmassign           = current($deletemodulesmulti);
        $dmquiz             = end($deletemodulesmulti);

        // Check the first task (page mod deletion).
        $this->assertCount(1, $deletemodulespage);
        $this->assertFalse($deletepagetask->is_multi_module_task());
        $this->assertEquals($page->cmid, $dmpage->coursemoduleid);
        $this->assertEquals($page->id,   $dmpage->moduleinstanceid);
        $this->assertEquals($pagetaskid, $deletepagetask->taskid);

        // Check the second task (multi mod deletion).
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($assign->cmid, $dmassign->coursemoduleid);
        $this->assertNull($dmassign->moduleinstanceid); // Should be gone (deleted).
        $this->assertNull($dmassign->courseid); // Should be gone (deleted).
        $this->assertEquals($quiz->cmid,   $dmquiz->coursemoduleid);
        $this->assertEquals($quiz->id,     $dmquiz->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmquiz->courseid); // Should be set via database check.
        $this->assertEquals($multitaskid, $deletemultitask->taskid);

        // Execute page task - should execute successfully.
        $now = time();
        $removaltaskpage = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the page adhoc task (should be due to faildelay of multitask).
        $this->assertEquals($pagetaskid, $removaltaskpage->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        $removaltaskpage->execute();
        // Replicate completed task.
        $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
        \core\task\manager::adhoc_task_complete($removaltaskpage);
        $this->assertCount($adhoctaskprecount - 1, $DB->get_records('task_adhoc'));

        // The assign module has deleted from the course.
        // ... quiz are still thought to be present.
        // ... page has not been deleted.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('assign', array('id' => $assigncm->instance))); // Now deleted.
        $this->assertFalse($DB->record_exists('page', array('id' => $pagecm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm deleted.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $pagecm->id))); // Page just deleted.

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
        // Page task shouldn't be included due to faildelay filter.
        $this->assertCount(1, $deletetasks);
        $deletemultitask    = current($deletetasks);
        $deletemodulesmulti = $deletemultitask->get_deletemodules();
        $this->assertCount(2, $deletemodulesmulti);
        $dmassign           = current($deletemodulesmulti);
        $dmquiz             = end($deletemodulesmulti);

        // Check the second task (multi mod deletion).
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($assign->cmid, $dmassign->coursemoduleid);
        $this->assertNull($dmassign->moduleinstanceid); // Null on muli-mod delete.
        $this->assertEquals($quiz->cmid, $dmquiz->coursemoduleid);
        $this->assertEquals($quiz->id,     $dmquiz->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmquiz->courseid); // Should be set via database check.
        $this->assertEquals(end($dbtasks)->id, end($deletetasks)->taskid);

        if ($exceptionthrown181) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown181;
        } else {
            $this->assertTrue($exceptionthrown181, "Expected Exception wasn't thrown for line 181");
        }

    }
}
