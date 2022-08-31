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
require_once("test_fix_course_delete_module_test.php");

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
class test_fix_course_delete_module_class_diagnosis_test extends test_fix_course_delete_module_test {

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

        // Queue adhoc task for page module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltaskpage);

        // Get page task's id.
        $pagetaskid = $this->find_taskid($this->removaltaskpage);

        // Execute task for page.
        // This will fail due to the page record already being deleted.
        $now = time();
        $this->removaltaskpage = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($pagetaskid, $this->removaltaskpage->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown145 = false;
        try {
            $this->expectException('moodle_exception');
            $this->removaltaskpage->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $pagetaskid)));
            \core\task\manager::adhoc_task_failed($this->removaltaskpage);
            $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $pagetaskid)));
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $exceptionthrown145 = $exception; // Run exeception case at end of function.
        }

        // The page module will be thought of as still present in the course (but deleted in page table).
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance))); // Deleted already.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->pagecm->id))); // Still present (task failed).

        // Queue adhoc task for url module deletion & get the task details before executing.
        \core\task\manager::queue_adhoc_task($this->removaltaskurl);
        // Get url task's id.
        $urltaskid = $this->find_taskid($this->removaltaskurl);

        // Get url task's id.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $urltaskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $removaltaskurl->get_custom_data_as_string()) {
                $urltaskid = $dbtaskid;
            }
        }

        $deletetasklist = new delete_task_list(0);
        $deletetasks    = $deletetasklist->get_deletetasks();
        // Find the delete_task for url before execution.
        $deleteurltask = current($deletetasks); // Find correct one in foreach loop.
        foreach ($deletetasks as $deletetask) {
            if ($deletetask->taskid == $urltaskid) {
                $deleteurltask = $deletetask;
                break;
            }
        }
        unset($deletetasks, $deletetasklist);

        // Execute task for url - will pass (course_module record absent).
        $now = time();
        $this->removaltaskurl = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($urltaskid, $this->removaltaskurl->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        $this->removaltaskurl->execute();
        // Module URL deleted successfully. Replicate passed task.
        $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
        $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $urltaskid)));
        \core\task\manager::adhoc_task_complete($this->removaltaskurl);
        $this->assertCount($adhoctaskprecount - 1, $DB->get_records('task_adhoc'));
        $this->assertFalse($DB->record_exists('task_adhoc', array('id' => $urltaskid)));

        // The url module was already deleted from course_modules but still present in url table.
        $this->assertTrue($DB->record_exists('url', array('id' => $this->urlcm->instance))); // Orphaned record.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->urlcm->id))); // Quiz cm already deleted.

        // Queue adhoc task for a multi-module delete (both quiz and assign).
        \core\task\manager::queue_adhoc_task($this->removaltaskmulti);

        // Get task's id.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $multitaskid = $this->find_taskid($this->removaltaskmulti);
        $urltaskid = $this->find_taskid($this->removaltaskurl);
        $pagetaskid = $this->find_taskid($this->removaltaskpage);

        // Execute task (assign cm should complete, quiz cm should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $this->removaltaskmulti = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($multitaskid, $this->removaltaskmulti->get_id());
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        // Exception expected to be thrown, but tested at end to allow rest of code to run.
        $exceptionthrown204 = false;
        try {
            $this->removaltaskmulti->execute();
        } catch (\moodle_exception $exception) {
            // Replicate failed task.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $multitaskid)));
            \core\task\manager::adhoc_task_failed($this->removaltaskmulti);
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $multitaskid)));
            $exceptionthrown204 = $exception; // Run exeception case at end of test function.
        }

        // The assign module has deleted from the course.
        // ... quiz still thought to be present.
        // ... page still thought to be present.
        // ... url has an orphaned record but deleted from course_modules.
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance))); // Now deleted.
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance))); // Was already deleted.
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance))); // Was already deleted.
        $this->assertTrue($DB->record_exists('url', array('id' => $this->urlcm->instance))); // Orphaned record.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id))); // Assign cm deleted.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id))); // Quiz cm still present.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->pagecm->id))); // Assign cm deleted.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->urlcm->id))); // Assign cm deleted.

        // First create a delete_task_list object first.
        $deletetasklist = new delete_task_list(0);

        $deletetasks    = $deletetasklist->get_deletetasks();
        foreach ($deletetasks as $dt) {
            if (!$dt->is_multi_module_task()) { // Only one module in task.
                $deletemodule = current($dt->get_deletemodules());
                if ($deletemodule->get_modulename() == 'page') {
                    $deletepagetask  = $dt;
                }
            } else {
                $deletemultitask = $dt;
            }
            // See above: $deleteurltask - was set pre-execution because missing course_module record will clear.
        }

        // Build symptoms.
        $pagesymptoms = array((string) $this->page->cmid =>
                              [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules')]);

        $urlsymptoms  = array((string) $this->url->cmid =>
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
