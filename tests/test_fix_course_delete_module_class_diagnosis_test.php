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
use tool_fix_delete_modules\diagnosis;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/diagnosis.php");
require_once(__DIR__ . "/../classes/deletemodule.php");
require_once(__DIR__ . "/../classes/deletetasklist.php");

/**
 * The test_fix_course_delete_module_class_diagnosis test class.
 *
 * Tests for the diagnosis class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_fix_course_delete_module_class_diagnosis_test extends \advanced_testcase {

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
     * @covers \tool_fix_course_delete_module\diagnosis
     */
    public function test_diagnosis_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Ensure all adhoc tasks/cache are cleared.
        \core\task\manager::$miniqueue = []; // Clear the cached queue.
        $DB->delete_records('task_adhoc');

        // Setup a course with a page, a url, a book, and an assignment and a quiz module.
        $user     = $this->getDataGenerator()->create_user();
        $course   = $this->getDataGenerator()->create_course();
        $page     = $this->getDataGenerator()->create_module('page', array('course' => $course->id));
        $pagecm   = get_coursemodule_from_id('page', $page->cmid);
        $urlm     = $this->getDataGenerator()->create_module('url', array('course' => $course->id));
        $urlcm    = get_coursemodule_from_id('url', $urlm->cmid);
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $quiz     = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm   = get_coursemodule_from_id('quiz', $quiz->cmid);

        // The module exists in the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(4, $coursedmodules);

        // Delete page & quiz table record to replicate failed course_module_delete adhoc tasks.
        $this->assertCount(1, $DB->get_records('page'));
        $DB->delete_records('page');
        $this->assertEmpty($DB->get_records('page'));

        $this->assertCount(1, $DB->get_records('quiz'));
        $DB->delete_records('quiz');
        $this->assertEmpty($DB->get_records('quiz'));

        // Delete the url mod's course_module record to replicate a failed course_module_delete adhoc task.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $urlm->cmid)));
        $DB->delete_records('course_modules', array('id' => $urlm->cmid));
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $urlm->cmid)));

        // Setup adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Get task's id.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $pagetaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskpage->get_custom_data_as_string()) {
                $pagetaskid = $dbtaskid;
            }
        }

        // Execute task for page.
        // This will fail due to the page record already being deleted.
        $now = time();
        $removaltaskpage = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($pagetaskid, $removaltaskpage->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown145 = false;
        try {
            $this->expectException('moodle_exception');
            $removaltaskpage->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            \core\task\manager::adhoc_task_failed($removaltaskpage);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown145 = $exception; // Run exeception case at end of function.
        }

        // The page module will be thought of as still present in the course (but deleted in page table).
        $this->assertFalse($DB->record_exists('page', array('id' => $pagecm->instance))); // Deleted already.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $pagecm->id))); // Still present due to failed task.

        // Setup adhoc task for url module deletion & get the task details before executing.
        $removaltaskurl = new \core_course\task\course_delete_modules();
        $urldata = [
            'cms' => [$urlcm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskurl->set_custom_data($urldata);
        \core\task\manager::queue_adhoc_task($removaltaskurl);

        $deletetasklist = new delete_task_list(0);
        $deletetasks        = $deletetasklist->get_deletetasks();
        $deleteurltask      = end($deletetasks); // Need to retrieve before execution.
        unset($deletetasks, $deletetasklist);

        // Get task's id.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $urltaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskurl->get_custom_data_as_string()) {
                $urltaskid = $dbtaskid;
            }
        }

        // Execute task for url - will pass (course_module record absent).
        $now = time();
        $removaltaskurl = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($urltaskid, $removaltaskurl->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        $removaltaskurl->execute();
        // Replicate passed task.
        $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
        \core\task\manager::adhoc_task_complete($removaltaskpage);
        $this->assertCount($adhoctaskprecount - 1, $DB->get_records('task_adhoc'));

        // The url module was already deleted from course_modules but still present in url table.
        $this->assertTrue($DB->record_exists('url', array('id' => $urlcm->instance))); // Orphaned record.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $urlcm->id))); // Quiz cm already manual deleted.

        // Setup adhoc task for a multi-module delete (both quiz and assign).
        $removaltaskmulti = new \core_course\task\course_delete_modules();
        $cmsarray = array(''.$assigncm->id => array('id' => $assigncm->id),
                            ''.$quizcm->id => array('id' => $quizcm->id));
        $multidata = [
            'cms' => $cmsarray,
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskmulti->set_custom_data($multidata);
        \core\task\manager::queue_adhoc_task($removaltaskmulti);

        // Get task's id.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = 0;
        $urltaskid = 0;
        $pagetaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskmulti->get_custom_data_as_string()) {
                $multitaskid = $dbtaskid;
            } else if ($dbtask->customdata === $removaltaskurl->get_custom_data_as_string()) {
                $urltaskid = $dbtaskid;
            } else if ($dbtask->customdata === $removaltaskpage->get_custom_data_as_string()) {
                $pagetaskid = $dbtaskid;
            }
        }

        // Execute task (assign cm should complete, quiz cm should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $removaltaskmulti = null;
        \core\task\manager::$miniqueue = [];
        // Find multimodule task.
        $tasks = \core\task\manager::get_adhoc_tasks('\\core_course\\task\\course_delete_modules');
        foreach ($tasks as $task) {
            if ($task->get_id() == $multitaskid) {
                $removaltaskmulti = $task;
            }
        }
        // Check this actually is the multi adhoc task.
        $this->assertEquals($multitaskid, $removaltaskmulti->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown204 = false;
        try {
            $removaltaskmulti->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            \core\task\manager::adhoc_task_failed($removaltaskmulti);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown204 = $exception; // Run exeception case at end of function.
        }

        // The assign module has deleted from the course.
        // ... quiz still thought to be present.
        // ... page still thought to be present.
        // ... url has an orphaned record but deleted from course_modules.
        $this->assertFalse($DB->record_exists('assign', array('id' => $assigncm->instance))); // Now deleted.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('page', array('id' => $pagecm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('url', array('id' => $urlcm->instance))); // Orphaned record.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $assigncm->id))); // Assign cm deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $pagecm->id))); // Assign cm deleted.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $urlcm->id))); // Assign cm deleted.

        // First create a delete_task_list object first.
        $deletetasklist = new delete_task_list(0);

        $deletetasks    = $deletetasklist->get_deletetasks();
        foreach ($deletetasks as $dt) {
            if (!$dt->is_multi_module_task()) { // Only one module in task.
                $deletemodule = current($dt->get_deletemodules());
                if ($deletemodule->get_modulename() == 'page') {
                    $deletepagetask  = $dt;
                }
            }
        }
        // See above: $deleteurltask - was set pre-execution because missing course_module record will clear.
        $deletemultitask    = end($deletetasks);

        // Build symptoms.
        $pagesymptoms = array(''.$page->cmid =>
                              [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules')]);

        $urlsymptoms  = array(''.$urlm->cmid =>
                              [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules'),
                               get_string(diagnosis::MODULE_COURSEMODULERECORDMISSING, 'tool_fix_delete_modules')
                              ]
                             );
        $multimodulesymptoms = array(get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules') =>
                                     get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules'));

        // Test creating a diagnosis object.
        $diagnosispagetask  = new diagnosis($deletepagetask, $pagesymptoms);
        $diagnosisurltask   = new diagnosis($deleteurltask, $urlsymptoms);
        $diagnosismultitask = new diagnosis($deletemultitask, $multimodulesymptoms);

        // Check page deletion task.
        $this->assertFalse($diagnosispagetask->is_multi_module_task());
        $this->assertEquals($pagesymptoms, $diagnosispagetask->get_symptoms());

        // Check url deletion task.
        $this->assertFalse($diagnosisurltask->is_multi_module_task());
        $this->assertEquals($urlsymptoms, $diagnosisurltask->get_symptoms());

        // Check multi-module deletion task.
        $this->assertTrue($diagnosismultitask->is_multi_module_task());
        $this->assertEquals(get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules'),
                            current($diagnosismultitask->get_symptoms()));

        if ($exceptionthrown145 && $exceptionthrown145) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown145;
        } else if (!$exceptionthrown145) {
            $this->assertTrue($exceptionthrown145, "Expected Exception wasn't thrown for line 177");
        } else if (!$exceptionthrown204) {
            $this->assertTrue($exceptionthrown204, "Expected Exception wasn't thrown for line 261");
        }

    }
}
