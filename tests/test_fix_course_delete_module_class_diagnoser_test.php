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
use tool_fix_delete_modules\diagnoser;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/diagnosis.php");
require_once(__DIR__ . "/../classes/deletemodule.php");
require_once(__DIR__ . "/../classes/deletetasklist.php");
require_once("test_fix_course_delete_module_test.php");

/**
 * The test_fix_course_delete_module_class_diagnoser test class.
 *
 * Tests for the diagnoser class.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_fix_course_delete_module_class_diagnoser_test extends test_fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\diagnoser
     */
    public function test_diagnoser_class() {
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

        // Test creating a diagnosis object.
        $diagnoserpagetask  = new diagnoser($deletepagetask);
        $diagnoserurltask   = new diagnoser($deleteurltask);
        $diagnosermultitask = new diagnoser($deletemultitask);
        $diagnoserbooktask  = new diagnoser($deletebooktask);

        $expectedsymptomspage  = [(string) $this->page->cmid =>
                                  [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules')]];
        $expectedsymptomsurl   = [(string) $this->url->cmid =>
                                  [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules'),
                                   get_string(diagnosis::MODULE_COURSEMODULERECORDMISSING, 'tool_fix_delete_modules')
                                  ]
        ];
        $expectedsymptomsmulti = [get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules') =>
                                  [get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules')]];
        $expectedsymptomsbook  = [get_string(diagnosis::TASK_ADHOCRECORDMISSING, 'tool_fix_delete_modules') =>
                                  [get_string(diagnosis::TASK_ADHOCRECORDMISSING, 'tool_fix_delete_modules')]];

        $expecteddiagnosispagetask  = new diagnosis($deletepagetask, $expectedsymptomspage);
        $expecteddiagnosisurltask   = new diagnosis($deleteurltask, $expectedsymptomsurl);
        $expecteddiagnosismultitask = new diagnosis($deletemultitask, $expectedsymptomsmulti);
        $expecteddiagnosisbooktask  = new diagnosis($deletebooktask, $expectedsymptomsbook);

        // Check diagnoser for page deletion task.
        $this->assertFalse($deletepagetask->is_multi_module_task());
        $this->assertTrue($deletepagetask->task_record_exists());
        $this->assertEquals($expecteddiagnosispagetask, $diagnoserpagetask->get_diagnosis());

        // Check diagnoser for url deletion task.
        $this->assertFalse($deleteurltask->is_multi_module_task());
        $this->assertTrue($deleteurltask->task_record_exists());
        $this->assertEquals($expecteddiagnosisurltask, $diagnoserurltask->get_diagnosis());

        // Check diagnoser for multi-module deletion task.
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertTrue($deletemultitask->task_record_exists());
        $this->assertEquals($expecteddiagnosismultitask, $diagnosermultitask->get_diagnosis());

        // Delete multitask from db and retest (both multi & missing adhoc task).
        $this->assertTrue($DB->record_exists('task_adhoc', array('id' => $deletemultitask->taskid)));
        $DB->delete_records('task_adhoc', array('id' => $deletemultitask->taskid));
        $this->assertFalse($DB->record_exists('task_adhoc', array('id' => $deletemultitask->taskid)));
        $diagnosermultitask = new diagnoser($deletemultitask);
        $expectedsymptomsmulti = [get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules') =>
                                  [get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules')],
                                  get_string(diagnosis::TASK_ADHOCRECORDMISSING, 'tool_fix_delete_modules') =>
                                  [get_string(diagnosis::TASK_ADHOCRECORDMISSING, 'tool_fix_delete_modules')]
        ];
        $expecteddiagnosismultitask = new diagnosis($deletemultitask, $expectedsymptomsmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertFalse($deletemultitask->task_record_exists());
        $this->assertEquals($expecteddiagnosismultitask, $diagnosermultitask->get_diagnosis());

        // Check diagnoser for book deletion task (non-existant task).
        $this->assertFalse($deletebooktask->is_multi_module_task());
        $this->assertFalse($deletebooktask->task_record_exists());
        $this->assertEquals($expecteddiagnosisbooktask, $diagnoserbooktask->get_diagnosis());

        unset($diagnosermultitask, $diagnoserpagetask, $diagnoserurltask, $diagnoserbooktask);

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 151");
        }

    }
}
