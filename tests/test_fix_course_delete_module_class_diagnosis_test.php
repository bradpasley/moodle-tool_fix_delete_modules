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
 * @copyright   Catalyst IT, 2022
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
        $this->assertEmpty($DB->get_records('course'));
        $this->assertEmpty($DB->get_records('course_modules'));
        $this->assertEmpty($DB->get_records('context'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));
    }

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\diagnosis
     */
    public function test_diagnosis_class() {
        global $DB;
        $this->resetAfterTest(true);

        // Setup a course with a page, an assignment and a quiz module.
        $user     = $this->getDataGenerator()->create_user();
        $course   = $this->getDataGenerator()->create_course();
        $page     = $this->getDataGenerator()->create_module('page', array('course' => $course->id));
        $pagecm   = get_coursemodule_from_id('page', $page->cmid);
        $assign   = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);
        $quiz     = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcm   = get_coursemodule_from_id('quiz', $quiz->cmid);

        // The module exists in the course.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(3, $coursedmodules);

        // Delete page & quiz table record to replicate failed course_module_delete adhoc tasks.
        $this->assertCount(1, $DB->get_records('page'));
        $DB->delete_records('page');
        $this->assertEmpty($DB->get_records('page'));

        $this->assertCount(1, $DB->get_records('quiz'));
        $DB->delete_records('quiz');
        $this->assertEmpty($DB->get_records('quiz'));

        // Setup 1st adhoc task for page module deletion.
        $removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$pagecm],
            'userid' => $user->id,
            'realuserid' => $user->id
        ];
        $removaltaskpage->set_custom_data($pagedata);
        \core\task\manager::queue_adhoc_task($removaltaskpage);

        // Setup 2nd adhoc task for a multi-module delete (both quiz and assign).
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

        // Execute tasks (first one should complete, second should fail).
        try { // This will fail due to the quiz record already being deleted.
            $removaltaskmulti->execute();
        } catch (Exception $e) {
            $this->assertCount(2, $DB->get_records('task_adhoc'));
        }
        // The assign module has deleted from the course.
        // ... quiz are still thought to be present.
        // ... page are stil thought to be present.
        $coursedmodules = get_course_mods($course->id);
        $this->assertCount(2, $coursedmodules);
        $this->assertEmpty($DB->get_records('page'));
        $this->assertEmpty($DB->get_records('assign'));
        $this->assertEmpty($DB->get_records('quiz'));

        // First create a delete_task_list object first.
        $deletetasklist = new delete_task_list(0);

        $deletetasks        = $deletetasklist->get_deletetasks();
        $deletepagetask     = current($deletetasks);
        $deletemultitask    = end($deletetasks);

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $this->assertCount(2, $dbtasks);

        // Test creating a diagnosis object.

        $diagnosispagetask  = new diagnosis($deletepagetask,
                                            array(''.$page->cmid => get_string(diagnosis::MODULE_MODULERECORDMISSING,
                                                                               'tool_fix_delete_modules')));
        $diagnosismultitask = new diagnosis($deletemultitask,
                                            array(get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules')
                                                  => get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules')));

        // Check page deletion task.
        $this->assertFalse($diagnosispagetask->is_multi_module_task());
        $this->assertEquals(get_string(diagnosis::MODULE_MODULERECORDMISSING, 'tool_fix_delete_modules'),
                            current($diagnosispagetask->get_symptoms()));

        // Check multi-module deletion task.
        $this->assertTrue($diagnosismultitask->is_multi_module_task());
        $this->assertEquals(get_string(diagnosis::TASK_MULTIMODULE, 'tool_fix_delete_modules'),
                            current($diagnosismultitask->get_symptoms()));
    }
}
