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
use tool_fix_delete_modules\outcome;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/outcome.php");
require_once(__DIR__ . "/../classes/delete_module.php");
require_once(__DIR__ . "/../classes/delete_task_list.php");
require_once("fix_course_delete_module_test.php");

/**
 * The test_fix_course_delete_module_class_outcome test class.
 *
 * Tests for the outcome class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_test extends fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\outcome
     */
    public function test_outcome_class() {
        global $DB;

        // Queue adhoc task for a multi-module delete (both quiz and assign).
        \core\task\manager::queue_adhoc_task($this->removaltaskmulti);
        $multitaskid = $this->find_taskid($this->removaltaskmulti);

        $record = $DB->get_records('task_adhoc');
        $this->assertCount(1, $record);
        // Execute tasks (first one should complete, second should fail).
        // This will fail due to the quiz record already being deleted.
        $now = time();
        $this->removaltaskmulti = \core\task\manager::get_next_adhoc_task($now);
        // Check this actually is the multi adhoc task.
        $this->assertEquals($multitaskid, $this->removaltaskmulti->get_id());
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

        // Setup adhoc task for page module deletion.
        \core\task\manager::queue_adhoc_task($this->removaltaskpage);

        // The assign module has deleted from the course.
        // ... quiz are still thought to be present.
        // ... page are still thought to be present.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->assigncm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->pagecm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quizcm->id)));
        $this->assertFalse($DB->record_exists('assign', array('id' => $this->assigncm->instance)));
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance)));
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance)));

        // First create a delete_task_list object first.
        $deletetasklist = new delete_task_list(0);

        // Create delete_tasks from the delete_task.
        $deletetasks = array_values($deletetasklist->get_deletetasks());
        foreach ($deletetasks as $deletetask) {
            $deletemodules = $deletetask->get_deletemodules();
            if (count($deletemodules) > 1) { // It's the multi module task.
                $deletemultitask = $deletetask;
            } else { // It's the page, single module tasks.
                $deletepagetask = $deletetask;
            }
        }

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(2, $dbtasks);

        // Test creating a diagnosis object.
        $messagespage = [get_string('outcome_module_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_course_module_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_course_section_data_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_context_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_file_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_grade_tables_records_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_blog_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_completion_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_completion_criteria_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_tag_table_record_deleted', 'tool_fix_delete_modules'),
                         get_string('outcome_module_fix_successful', 'tool_fix_delete_modules')
        ];

        $messagesmulti = [get_string('outcome_separate_into_individual_task', 'tool_fix_delete_modules'),
                          get_string('outcome_adhoc_task_record_rescheduled', 'tool_fix_delete_modules'),
                          get_string('outcome_task_fix_successful', 'tool_fix_delete_modules')
        ];

        $outcomepage      = new outcome($deletepagetask, $messagespage);
        $outcomemultitask = new outcome($deletemultitask, $messagesmulti);

        // Check page outcome.
        $this->assertEquals($deletepagetask, $outcomepage->get_task());
        $this->assertEquals($messagespage, $outcomepage->get_messages());

        // Check multi-module deletion task.
        $this->assertEquals($deletemultitask, $outcomemultitask->get_task());
        $this->assertEquals($messagesmulti, $outcomemultitask->get_messages());

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 139");
        }

    }
}
