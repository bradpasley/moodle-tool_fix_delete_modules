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
use tool_fix_delete_modules\surgeon;
use tool_monitor\notification_task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/diagnosis.php");
require_once(__DIR__ . "/../classes/delete_module.php");
require_once(__DIR__ . "/../classes/delete_task_list.php");
require_once("fix_course_delete_module_test.php");

/**
 * The test_fix_course_delete_module_class_surgeon test class.
 *
 * Tests for the surgeon class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class surgeon_test extends fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\surgeon
     */
    public function test_surgeon_class() {
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

        // Queue adhoc task for label module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltasklabel);

        // DON'T Queue adhoc task for book module deletion.
        // This will be used to test a task which is absent from the task_adhoc table.

        // The assign & url module have been deleted from the course.
        // ... quiz are still thought to be present.
        // ... page are stil thought to be present.
        // ... url has an orphaned record.
        // ... book remains undeleted.
        // ... label doesn't exist in the section record.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id)));
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->urlcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->pagecm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->bookcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->labelcm->id)));
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance)));
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance)));
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance)));
        $this->assertTrue($DB->record_exists('url', array('id' => $this->urlcm->instance)));
        $this->assertTrue($DB->record_exists('book', array('id' => $this->bookcm->instance)));
        $this->assertTrue($DB->record_exists('label', array('id' => $this->labelcm->instance)));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertNotEmpty($DB->get_records('url'));
        $this->assertNotEmpty($DB->get_records('book'));
        $this->assertNotEmpty($DB->get_records('label'));

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
                switch ($modulename) {
                    case 'page':
                        $deletepagetask = $deletetask;
                        break;
                    case 'label':
                        $deletelabeltask = $deletetask;
                        break;
                    default:
                        $deleteurltask = $deletetask;
                        break;
                }
            }
        }
        // This task will not exist in the task_adhoc table.
        $deletebooktask = new delete_task(999999, json_decode($this->removaltaskbook->get_custom_data_as_string()));

        // Database records of adhoc tasks to compare against.
        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(4, $dbtasks);

        // Creating diagnosis objects.
        $diagnosermultitask = new diagnoser($deletemultitask);
        $diagnoserpagetask  = new diagnoser($deletepagetask);
        $diagnoserurltask   = new diagnoser($deleteurltask);
        $diagnoserbooktask  = new diagnoser($deletebooktask);
        $diagnoserlabeltask  = new diagnoser($deletelabeltask);

        // Create Test surgeon objects.
        $surgeonmultitask = new surgeon($diagnosermultitask->get_diagnosis());
        $surgeonpagetask  = new surgeon($diagnoserpagetask->get_diagnosis());
        $surgeonurltask   = new surgeon($diagnoserurltask->get_diagnosis());
        $surgeonbooktask  = new surgeon($diagnoserbooktask->get_diagnosis());
        $surgeonlabeltask  = new surgeon($diagnoserlabeltask->get_diagnosis());

        // Expected outcome messages.
        $messagesmulti = [get_string('outcome_separate_into_individual_task', 'tool_fix_delete_modules'),
                          get_string('outcome_separate_into_individual_task', 'tool_fix_delete_modules'),
                          get_string('outcome_separate_old_task_deleted', 'tool_fix_delete_modules'),
                          get_string('outcome_task_fix_successful', 'tool_fix_delete_modules')
        ];
        $messagespage = [get_string('outcome_file_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_blog_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_completion_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_completion_criteria_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_tag_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_context_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_course_module_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_course_section_data_delete_fail', 'tool_fix_delete_modules'),
                         get_string('outcome_adhoc_task_record_rescheduled', 'tool_fix_delete_modules'),
                         get_string('outcome_module_fix_successful', 'tool_fix_delete_modules')
        ];
        $messagesurl = $messagespage;
        array_unshift($messagesurl, get_string('outcome_course_module_table_record_not_found', 'tool_fix_delete_modules'));

        $messagesbook = [get_string('outcome_adhoc_task_record_advice', 'tool_fix_delete_modules')];
        $messageslabel = [get_string('outcome_course_section_data_fixed', 'tool_fix_delete_modules'),
            get_string('outcome_file_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_blog_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_completion_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_completion_criteria_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_tag_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_context_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_course_module_table_record_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_course_section_data_deleted', 'tool_fix_delete_modules'),
            get_string('outcome_adhoc_task_record_rescheduled', 'tool_fix_delete_modules'),
            get_string('outcome_module_fix_successful', 'tool_fix_delete_modules')
        ];

        $expectedoutcomemultitask = new outcome($deletemultitask, $messagesmulti);
        $expectedoutcomepage      = new outcome($deletepagetask,  $messagespage);
        $expectedoutcomeurltask   = new outcome($deleteurltask,   $messagesurl);
        $expectedoutcomebooktask  = new outcome($deletebooktask,  $messagesbook);
        $expectedoutcomelabeltask  = new outcome($deletelabeltask,  $messageslabel);

        $testoutcomemulti = $surgeonmultitask->get_outcome();
        $testoutcomepage  = $surgeonpagetask->get_outcome();
        $testoutcomeurl   = $surgeonurltask->get_outcome();
        $testoutcomebook  = $surgeonbooktask->get_outcome();
        $testoutcomelabel  = $surgeonlabeltask->get_outcome();

        $this->assertEquals($expectedoutcomemultitask->get_messages(), $testoutcomemulti->get_messages());
        $this->assertEquals($expectedoutcomepage->get_messages(), $testoutcomepage->get_messages());
        $this->assertEquals($expectedoutcomeurltask->get_messages(), $testoutcomeurl->get_messages());
        $this->assertEquals($expectedoutcomebooktask->get_messages(), $testoutcomebook->get_messages());
        $this->assertEquals($expectedoutcomelabeltask->get_messages(), $testoutcomelabel->get_messages());

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 139");
        }
    }

    // Tests for reschedule_or_queue_adhoc_task tests adapted from adhoc_task_test.php tests.

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will only queue a course_delete_module tasks.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_wrong_classname() {
        $this->resetAfterTest(true);

        // Schedule wrong type of adhoc task.
        $task = new notification_task();
        $task->set_custom_data(['courseid' => 10]);
        $precountwrongtask = count(\core\task\manager::get_adhoc_tasks('\tool_monitor\notification_task'));
        $precountrighttask = count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));
        surgeon::reschedule_or_queue_adhoc_task($task);
        // None added.
        $this->assertEquals($precountwrongtask,
                            count(\core\task\manager::get_adhoc_tasks('\tool_monitor\notification_task')));
        $this->assertEquals($precountrighttask,
                            count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));

        // Schedule right type of adhoc task.
        surgeon::reschedule_or_queue_adhoc_task($this->removaltaskassign);
        $this->assertEquals($precountwrongtask,
                            count(\core\task\manager::get_adhoc_tasks('\tool_monitor\notification_task')));
        $this->assertEquals($precountrighttask + 1,
                            count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will schedule a new task if no tasks exist.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_no_existing() {
        $this->resetAfterTest(true);

        // Schedule adhoc task.
        $precount = count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));
        surgeon::reschedule_or_queue_adhoc_task($this->removaltaskpage);
        $this->assertEquals($precount + 1, count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will schedule a new task if a task for the same user does
     * not exist.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_different_user() {
        $this->resetAfterTest(true);
        $user = \core_user::get_user_by_username('admin');

        // Schedule adhoc task.
        $precount = count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));
        surgeon::reschedule_or_queue_adhoc_task($this->removaltaskurl);
        $this->assertEquals($precount + 1, count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));

        // Schedule adhoc task for a different user.
        $this->removaltaskurl->set_userid($user->id);
        surgeon::reschedule_or_queue_adhoc_task($this->removaltaskurl);
        $this->assertEquals($precount + 2, count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will schedule a new task if a task with different custom
     * data exists.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_different_data() {
        $this->resetAfterTest(true);

        $precount = count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));

        // Schedule adhoc task.
        $task = $this->removaltaskassign;
        surgeon::reschedule_or_queue_adhoc_task($task);

        // Schedule adhoc task for a different data.
        $task = $this->removaltaskassign;
        $quizdata = [
            'cms' => [$this->quizcm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $task->set_custom_data($quizdata);
        surgeon::reschedule_or_queue_adhoc_task($task);

        $this->assertEquals($precount + 2, count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will not make any change for matching data if no time was
     * specified.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_match_no_change() {
        $this->resetAfterTest(true);

        $precount = count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));

        // Schedule adhoc task.
        $task = $this->removaltaskassign;
        $task->set_next_run_time(time() + DAYSECS);
        surgeon::reschedule_or_queue_adhoc_task($task);

        $before = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');

        // Schedule the task again but do not specify a time.
        $task = $this->removaltaskassign;
        surgeon::reschedule_or_queue_adhoc_task($task);

        $this->assertEquals($precount + 1, count(\core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules')));
        $this->assertEquals($before, \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules'));
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will update the run time if there are planned changes.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_match_update_runtime() {
        $this->resetAfterTest(true);
        $initialruntime = time() + DAYSECS;
        $newruntime = time() + WEEKSECS;

        // Schedule adhoc task.
        $task = $this->removaltaskassign;
        $task->set_next_run_time($initialruntime);
        surgeon::reschedule_or_queue_adhoc_task($task);

        $before = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');

        // Schedule the task again.
        $task = $this->removaltaskassign;
        $task->set_next_run_time($newruntime);
        surgeon::reschedule_or_queue_adhoc_task($task);

        $tasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        $this->assertEquals(1, count($tasks));
        $this->assertNotEquals($before, $tasks);
        $firsttask = reset($tasks);
        $this->assertEquals($newruntime, $firsttask->get_next_run_time());
    }

    /**
     * Ensure that the reschedule_or_queue_adhoc_task function will update the run time if there are planned changes.
     * @covers ::reschedule_or_queue_adhoc_task
     */
    public function test_reschedule_or_queue_adhoc_task_next_runtime_updated() {
        $this->resetAfterTest(true);
        $initialruntime = time();

        // Schedule adhoc task.
        $task = $this->removaltaskassign;
        $task->set_next_run_time($initialruntime);
        \core\task\manager::queue_adhoc_task($task);
        $before = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');

        // Run and fail the task.
        $task = \core\task\manager::get_next_adhoc_task($initialruntime + 60);
        $task->execute();
        \core\task\manager::adhoc_task_failed($task);

        // Schedule the task again.
        $tasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        $this->assertEquals(1, count($tasks));
        $originalnextrun = intval(current($tasks)->get_next_run_time());
        $newnextruntime = $originalnextrun + 60;

        $this->assertTrue($initialruntime < $originalnextrun);
        $this->assertTrue($originalnextrun < $newnextruntime);

        $task->set_next_run_time($newnextruntime);
        surgeon::reschedule_or_queue_adhoc_task($task);
        $tasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
        $this->assertEquals(1, count($tasks));
        $this->assertTrue($initialruntime < current($tasks)->get_next_run_time());
        $this->assertTrue($originalnextrun < current($tasks)->get_next_run_time());
        $this->assertNotEquals($before, $tasks);
        $firsttask = reset($tasks);
        $this->assertEquals($newnextruntime, $firsttask->get_next_run_time());
    }


}
