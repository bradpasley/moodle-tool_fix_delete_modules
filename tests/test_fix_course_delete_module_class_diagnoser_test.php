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
class test_fix_course_delete_module_class_diagnoser_test extends \advanced_testcase {

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
     * @covers \tool_fix_course_delete_module\diagnoser
     */
    public function test_diagnoser_class() {
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
        $precoursemodulecount = count($coursedmodules);
        $this->assertCount($precoursemodulecount, get_course_mods($course->id));

        // Delete page & quiz table record to replicate failed course_module_delete adhoc tasks.
        $this->assertTrue($DB->record_exists('page', array('id' => $pagecm->instance)));
        $DB->delete_records('page');
        $this->assertFalse($DB->record_exists('page', array('id' => $pagecm->instance)));
        $this->assertEmpty($DB->get_records('page'));
        $this->assertTrue($DB->record_exists('quiz', array('id' => $quizcm->instance)));
        $DB->delete_records('quiz');
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance)));
        $this->assertEmpty($DB->get_records('quiz'));

        // Delete the url mod's course_module record to replicate a failed course_module_delete adhoc task.
        $this->assertCount($precoursemodulecount, get_course_mods($course->id));
        $DB->delete_records('course_modules', array('id' => $url->cmid));
        $this->assertCount($precoursemodulecount - 1, get_course_mods($course->id));
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
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $assigncm->id)));
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $urlcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $pagecm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $quizcm->id)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $bookcm->id)));
        $this->assertFalse($DB->record_exists('assign', array('id' => $assigncm->instance)));
        $this->assertFalse($DB->record_exists('page', array('id' => $pagecm->instance)));
        $this->assertFalse($DB->record_exists('quiz', array('id' => $quizcm->instance)));
        $this->assertTrue($DB->record_exists('url', array('id' => $urlcm->instance)));
        $this->assertTrue($DB->record_exists('book', array('id' => $bookcm->instance)));
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
        $deletebooktask     = new delete_task(999999, $bookcms); // This task will not exist in the task_adhoc table.

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(3, $dbtasks);

        // Test creating a diagnosis object.
        $diagnoserpagetask  = new diagnoser($deletepagetask);
        $diagnoserurltask   = new diagnoser($deleteurltask);
        $diagnosermultitask = new diagnoser($deletemultitask);
        $diagnoserbooktask  = new diagnoser($deletebooktask);

        $expectedsymptomspage  = [''.$page->cmid =>
                                  [get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules')]];
        $expectedsymptomsurl   = [''.$url->cmid =>
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
