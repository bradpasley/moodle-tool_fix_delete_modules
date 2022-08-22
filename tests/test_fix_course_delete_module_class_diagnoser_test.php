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
 * @copyright   Catalyst IT, 2022
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
        $this->assertEmpty($DB->get_records('course'));
        $this->assertEmpty($DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('context'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
    }

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\diagnoser
     */
    public function test_diagnoser_class() {
        global $DB;
        $this->resetAfterTest(true);

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

        // Setup 1st adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Setup 2nd adhoc task for url module deletion.
        $removaltaskurl = new \core_course\task\course_delete_modules();
        $urldata = [
            'cms' => [$urlcm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskurl->set_custom_data($urldata);
        \core\task\manager::queue_adhoc_task($removaltaskurl);

        // Setup 3rd adhoc task for a multi-module delete (both quiz and assign).
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

        // DON'T Setup 4th adhoc task for book module deletion.
        // This will be used to test a task which is absent from the task_adhoc table.
        // Setup 3rd adhoc task for a multi-module delete (both quiz and assign).
        $removalbooktask = new \core_course\task\course_delete_modules();
        $bookdata = [
            'cms' => [$bookcm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removalbooktask->set_custom_data($bookdata);
        $bookcms = $removalbooktask->get_custom_data();

        // Execute tasks (first one should complete, second should fail).
        try { // This will fail due to the quiz record already being deleted.
            $removaltaskmulti->execute();
        } catch (Exception $e) {
            $this->assertCount(3, $DB->get_records('task_adhoc'));
        }
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

        $deletetasks        = array_values($deletetasklist->get_deletetasks());
        $deletepagetask     = $deletetasks[0];
        $deleteurltask      = $deletetasks[1];
        $deletemultitask    = $deletetasks[2];
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
        $this->assertCount(3, $DB->get_records('task_adhoc'));
        $DB->delete_records('task_adhoc', array('id' => $deletemultitask->taskid));
        $this->assertCount(2, $DB->get_records('task_adhoc'));
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

    }
}
