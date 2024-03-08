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
require_once(__DIR__ . "/../classes/delete_module.php");
require_once(__DIR__ . "/../classes/delete_task_list.php");
require_once("fix_course_delete_module_test.php");

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
class reporter_test extends fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\reporter
     */
    public function test_reporter_class() {
        global $DB;

        [$deletemultitask, $deletepagetask, $deleteurltask, $deletebooktask, $deletelabeltask, $exceptionthrown]
            = $this->setup_test();

        // Creating diagnosis objects.
        $diagnosermultitask = new diagnoser($deletemultitask);
        $surgeonmultitask = new surgeon($diagnosermultitask->get_diagnosis());

        // Test reporter: CLI.
        $testreporter = new reporter(false, 0);

        // Test output displays for get_diagnosis_data().
        $testdiagnoses = $testreporter->get_diagnosis();
        $this->assertNotEquals('', $testdiagnoses);
        $this->assertTrue(strpos($testdiagnoses, get_string('diagnosis', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(strpos($testdiagnoses, get_string('symptoms', 'tool_fix_delete_modules')) !== false);

        // Test output displays for get_tables_report().
        $testreports = $testreporter->get_tables_report();
        $this->assertNotEquals('', $testreports);
        $this->assertTrue(strpos($testreports, get_string('report_heading', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(strpos($testreports, get_string('table_title_adhoctask', 'tool_fix_delete_modules')) !== false);

        // Test output displays for fix_tasks().
        $fixresults = $testreporter->fix_tasks();
        $this->assertNotEquals('', $fixresults);
        $this->assertTrue(strpos($fixresults, get_string('results', 'tool_fix_delete_modules')) !== false);
        $this->assertTrue(strpos($fixresults, get_string('result_messages', 'tool_fix_delete_modules')) !== false);

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
