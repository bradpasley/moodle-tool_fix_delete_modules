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
        $DB->delete_records('page');
        $DB->delete_records('assign');
        $DB->delete_records('quiz');
        $DB->delete_records('user');
        $DB->delete_records('task_adhoc');
        $this->assertEmpty($DB->get_records('course'));
        $this->assertEmpty($DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('context'));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEmpty($DB->get_records('user'));
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

        // Setup 1st adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Setup 2nd adhoc task for a multi-module delete (both quiz and assign).
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

        // Test creating a deletetasklist object.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(2, $dbtasks);

        // Include all tasks (even 0 fail delay).
        $deletetasklist = new delete_task_list(0);

        $deletetasks        = array();
        $deletetasks        = $deletetasklist->get_deletetasks();
        $deletepagetask     = current($deletetasks);
        $deletemultitask    = end($deletetasks);
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
        $this->assertEquals(current($dbtasks)->id, $deletepagetask->taskid);
        $this->assertEquals(current($dbtasks)->id, $dmpage->taskid);

        // Check the second task (multi mod deletion).
        $this->assertCount(2, $deletemodulesmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertEquals($assign->cmid, $dmassign->coursemoduleid);
        $this->assertEquals($assign->id,   $dmassign->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmassign->courseid); // Should be set via database check.
        $this->assertEquals($quiz->cmid,   $dmquiz->coursemoduleid);
        $this->assertEquals($quiz->id,     $dmquiz->moduleinstanceid); // Should be set via database check.
        $this->assertEquals($course->id,   $dmquiz->courseid); // Should be set via database check.
        $this->assertEquals(end($dbtasks)->id, $dmassign->taskid);
        $this->assertEquals(end($dbtasks)->id, $dmquiz->taskid);
        $this->assertEquals(end($dbtasks)->id, end($deletetasks)->taskid);

        // Execute tasks (first one should complete, second should fail).
        try { // This will fail due to the quiz record already being deleted.
            $removaltaskmulti->execute();
        } catch (Exception $e) {
            $this->assertCount(2, $DB->get_records('task_adhoc'));
        }
        // The assign module has deleted from the course.
        // ... quiz are still thought to be present.
        // ... page has not been deleted.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(2, $coursedmodules);
        $this->assertCount(1, $DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));

        // Test creating a deletetasklist object after failed adhoc_task run.

        // Change faildelay fields for testing.
        $DB->set_field('task_adhoc', 'faildelay', 0, array('id'  => $deletepagetask->taskid));
        $DB->set_field('task_adhoc', 'faildelay', 60, array('id' => $deletemultitask->taskid));

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(2, $dbtasks);

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
        $this->assertEquals(end($dbtasks)->id, $dmassign->taskid);
        $this->assertEquals(end($dbtasks)->id, $dmquiz->taskid);
        $this->assertEquals(end($dbtasks)->id, end($deletetasks)->taskid);
    }
}
