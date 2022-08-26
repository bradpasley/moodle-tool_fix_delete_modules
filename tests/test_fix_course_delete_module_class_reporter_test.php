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
require_once("test_fix_course_delete_module_test.php");

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
class test_fix_course_delete_module_class_reporter_test extends test_fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\reporter
     */
    public function test_reporter_class() {
        global $DB;

        // Queue adhoc task for a multi-module delete (both quiz and assign).
        \core\task\manager::queue_adhoc_task($this->removaltaskmulti);

        // Execute task (assign cm should complete, quiz cm should fail).
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

        // Queue adhoc task for page module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltaskpage);

        // Queue adhoc task for url module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltaskurl);

        // DON'T Queue adhoc task for book module deletion.
        // This will be used to test a task which is absent from the task_adhoc table.

        // The assign & url module have been deleted from the course.
        // ... quiz are still thought to be present.
        // ... page are stil thought to be present.
        // ... url has an orphaned record.
        // ... book remains undeleted.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id)));
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->urlcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->pagecm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->bookcm->id)));
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance)));
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance)));
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance)));
        $this->assertTrue($DB->record_exists('url', array('id' => $this->urlcm->instance)));
        $this->assertTrue($DB->record_exists('book', array('id' => $this->bookcm->instance)));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertNotEmpty($DB->get_records('url'));
        $this->assertNotEmpty($DB->get_records('book'));

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
        // This task will not exist in the task_adhoc table.
        $deletebooktask = new delete_task(999999, json_decode($this->removaltaskbook->get_custom_data_as_string()));

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
        $this->assertTrue(mb_strpos($testdiagnoses, get_string('diagnosis', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(mb_strpos($testdiagnoses, get_string('symptoms', 'tool_fix_delete_modules')) !== false);

        // Test output displays for get_tables_report().
        $testreports = $testreporter->get_tables_report();
        $this->assertNotEquals('', $testreports);
        $this->assertTrue(mb_strpos($testreports, get_string('report_heading', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(mb_strpos($testreports, get_string('table_title_adhoctask', 'tool_fix_delete_modules')) !== false);

        // Test output displays for fix_tasks().
        $fixresults = $testreporter->fix_tasks();
        $this->assertNotEquals('', $fixresults);
        $this->assertTrue(mb_strpos($fixresults, get_string('results', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(mb_strpos($fixresults, get_string('result_messages', 'tool_fix_delete_modules')) !== false);

        // Run Adhoc Tasks.
        // Get Tasks from the scheduler and run them.
        $adhoctaskprecount = count($DB->get_records('task_adhoc'));
        $now = time();
        while (($task = \core\task\manager::get_next_adhoc_task($now + 120)) !== null) {
            // Check is a course_delete_modules adhoc task.
            $this->assertInstanceOf('\\core_course\\task\\course_delete_modules', $task);
            // Check faildelay is 0.
            $this->assertEquals(0, $task->get_fail_delay());
            // Check nextrun is equal or later than "now".
            $this->assertTrue($now >= $task->get_next_run_time());
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
