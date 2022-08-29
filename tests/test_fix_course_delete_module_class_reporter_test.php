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
use tool_fix_delete_modules\reporter;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/diagnosis.php");
require_once(__DIR__ . "/../classes/deletemodule.php");
require_once(__DIR__ . "/../classes/deletetasklist.php");

/**
 * The test_fix_course_delete_module_class_reporter test class.
 *
 * Tests for the reporter class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_fix_course_delete_module_class_reporter_test extends \advanced_testcase {

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
     * @covers \tool_fix_course_delete_module\reporter
     */
    public function test_reporter_class() {
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
        $url      = $this->getDataGenerator()->create_module('url', array('course' => $course->id));
        $urlcm    = get_coursemodule_from_id('url', $url->cmid);
        $book     = $this->getDataGenerator()->create_module('book', array('course' => $course->id));
        $bookcm   = get_coursemodule_from_id('book', $book->cmid);
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $quiz     = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm   = get_coursemodule_from_id('quiz', $quiz->cmid);

        // The module exists in the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(5, $coursedmodules);

        // Delete page & quiz table record to replicate failed course_module_delete adhoc tasks.
        $this->assertCount(1, $DB->get_records('page'));
        $DB->delete_records('page');
        $this->assertEmpty($DB->get_records('page'));
        $this->assertCount(1, $DB->get_records('quiz'));
        $DB->delete_records('quiz');
        $this->assertEmpty($DB->get_records('quiz'));

        // Delete the url mod's course_module record to replicate a failed course_module_delete adhoc task.
        $this->assertCount(5, $DB->get_records('course_modules'));
        $DB->delete_records('course_modules', array('id' => $url->cmid));
        $this->assertCount(4, $DB->get_records('course_modules'));
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $url->cmid)));

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

        // Execute task (assign cm should complete, quiz cm should fail).
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

        // Setup adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Setup adhoc task for url module deletion.
        $removaltaskurl = new \core_course\task\course_delete_modules();
        $urldata = [
            'cms' => [$urlcm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskurl->set_custom_data($urldata);
        \core\task\manager::queue_adhoc_task($removaltaskurl);

        // DON'T Setup adhoc task for book module deletion.
        // This will be used to test a task which is absent from the task_adhoc table.
        $removalbooktask = new \core_course\task\course_delete_modules();
        $bookdata = [
            'cms' => [$bookcm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removalbooktask->set_custom_data($bookdata);
        $bookcms = $removalbooktask->get_custom_data();

        // The assign & url module have been deleted from the course.
        // ... quiz are still thought to be present.
        // ... page are stil thought to be present.
        // ... url has an orphaned record.
        // ... book remains undeleted.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(3, $coursedmodules);
        $this->assertCount(3, $DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertCount(1, $DB->get_records('url'));
        $this->assertCount(1, $DB->get_records('book'));

        // First create a delete_task_list object first.
        $deletetasklist = new delete_task_list(0);

        // Create delete_tasks from the delete_task.
        $deletetasks        = array_values($deletetasklist->get_deletetasks());
        foreach ($deletetasks as $deletetask) {
            $deletemodules = $deletetask->get_deletemodules();
            if (count($deletemodules) > 1) { // It's the multi module task.
                $deletemultitask = $deletetask;
            } else { // It's one of the single module tasks.
                $deletemodule = current($deletemodules);
                $modulename = $deletemodule->get_modulename();
                if (isset($modulename) && $modulename == 'page') {
                    $deletepagetask = $deletetask;
                } else {
                    $deleteurltask = $deletetask;
                }
            }
        }
        $deletebooktask     = new delete_task(999999, $bookcms); // This task will not exist in the task_adhoc table.

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(3, $dbtasks);

        // Creating diagnosis objects.
        $diagnosermultitask = new diagnoser($deletemultitask);
        $diagnoserpagetask  = new diagnoser($deletepagetask);
        $diagnoserurltask   = new diagnoser($deleteurltask);
        $diagnoserbooktask  = new diagnoser($deletebooktask);

        // Create Test surgeon objects.
        $surgeonmultitask = new surgeon($diagnosermultitask->get_diagnosis());
        $surgeonpagetask  = new surgeon($diagnoserpagetask->get_diagnosis());
        $surgeonurltask   = new surgeon($diagnoserurltask->get_diagnosis());
        $surgeonbooktask  = new surgeon($diagnoserbooktask->get_diagnosis());

        // Expected outcome messages.
        $messagesmulti = [get_string(outcome::TASK_SEPARATE_TASK_MADE, 'tool_fix_delete_modules'),
                          get_string(outcome::TASK_SEPARATE_TASK_MADE, 'tool_fix_delete_modules'),
                          get_string(outcome::TASK_SEPARATE_OLDTASK_DELETED, 'tool_fix_delete_modules'),
                          get_string(outcome::TASK_SUCCESS, 'tool_fix_delete_modules'),
                          get_string(outcome::TASK_ADHOCTASK_RUN_CLI, 'tool_fix_delete_modules')
        ];

        $messagespage = [get_string(outcome::MODULE_FILERECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_BLOGRECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_COMPLETIONRECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_COMPLETIONCRITERIA_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_TAGRECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_CONTEXTRECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_COURSEMODULERECORD_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_COURSESECTION_NOT_DELETED, 'tool_fix_delete_modules'),
                         get_string(outcome::MODULE_SUCCESS, 'tool_fix_delete_modules')
        ];
        $messagesurl = $messagespage;
        array_unshift($messagesurl, get_string(outcome::MODULE_COURSEMODULE_NOTFOUND, 'tool_fix_delete_modules'));

        $messagesbook = [get_string(outcome::TASK_ADHOCRECORDABSENT_ADVICE, 'tool_fix_delete_modules')];

        // Extra outcome messages for Moodle 3.7+.
        if (method_exists('\core\task\manager', 'reschedule_or_queue_adhoc_task')) {
            $successfulreschedule = get_string(outcome::TASK_ADHOCTASK_RESCHEDULE, 'tool_fix_delete_modules');
            array_splice($messagespage, (count($messagespage) - 1), 0, $successfulreschedule);
            array_splice($messagesurl,  (count($messagesurl) - 1), 0, $successfulreschedule);
        }

        $expectedoutcomemultitask = new outcome($deletemultitask, $messagesmulti);
        $expectedoutcomepage      = new outcome($deletepagetask,  $messagespage);
        $expectedoutcomeurltask   = new outcome($deleteurltask,   $messagesurl);
        $expectedoutcomebooktask  = new outcome($deletebooktask,  $messagesbook);

        $testoutcomemulti = $surgeonmultitask->get_outcome();
        $testoutcomepage  = $surgeonpagetask->get_outcome();
        $testoutcomeurl   = $surgeonurltask->get_outcome();
        $testoutcomebook  = $surgeonbooktask->get_outcome();

        $this->assertEquals($expectedoutcomemultitask->get_messages(), $testoutcomemulti->get_messages());
        $this->assertEquals($expectedoutcomepage->get_messages(), $testoutcomepage->get_messages());
        $this->assertEquals($expectedoutcomeurltask->get_messages(), $testoutcomeurl->get_messages());
        $this->assertEquals($expectedoutcomebooktask->get_messages(), $testoutcomebook->get_messages());

        // Test reporter: CLI.
        $testreporter = new reporter(false, 0);

        // Test output displays for get_diagnosis_data().
        $testdiagnoses = $testreporter->get_diagnosis();
        $this->assertNotEquals('', $testdiagnoses);
        $this->assertStringContainsString(get_string('diagnosis', 'tool_fix_delete_modules'), $testdiagnoses);
        $this->assertStringContainsString(get_string('symptoms', 'tool_fix_delete_modules'), $testdiagnoses);

        // Test output displays for get_tables_report().
        $testreports = $testreporter->get_tables_report();
        $this->assertNotEquals('', $testreports);
        $this->assertStringContainsString(get_string('report_heading', 'tool_fix_delete_modules'), $testreports);
        $this->assertStringContainsString(get_string('table_title_adhoctask', 'tool_fix_delete_modules'), $testreports);
        $this->assertStringContainsString(get_string('table_title_adhoctask', 'tool_fix_delete_modules'), $testreports);

        // Test output displays for fix_tasks().
        $fixresults = $testreporter->fix_tasks();
        $this->assertNotEquals('', $fixresults);
        $this->assertStringContainsString(get_string('results', 'tool_fix_delete_modules'), $fixresults);
        $this->assertStringContainsString(get_string('result_messages', 'tool_fix_delete_modules'), $fixresults);

        // Run Adhoc Tasks.
        // Get Tasks from the scheduler and run them.
        $now = time();
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        while (($task = \core\task\manager::get_next_adhoc_task($now + 120)) !== null) {
            // Check is a course_delete_modules adhoc task.
            $this->assertInstanceOf('\\core_course\\task\\course_delete_modules', $task);
            // Check faildelay is 0.
            $this->assertEquals(0, $task->get_fail_delay());
            // Check nextrun is later than "$timeafterqueue".
            $this->assertEquals($now, $task->get_next_run_time());
            // Check adhoc task count.
            $this->assertCount($adhoctaskprecount, $DB->get_records('task_adhoc'));
            $task->execute(); // Not expecting any failed tasks.
            \core\task\manager::adhoc_task_complete($task);
            $this->assertCount(--$adhoctaskprecount, $DB->get_records('task_adhoc'));
            // Check Adhoc Task is now cleared.
            $this->assertEmpty($DB->get_records('task_adhoc', array('id' => $task->get_id())));
        }

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 148");
        }

    }
}
