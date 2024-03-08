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
require_once(__DIR__ . "/../classes/delete_module.php");
require_once(__DIR__ . "/../classes/delete_task_list.php");
require_once("fix_course_delete_module_test.php");

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
class diagnoser_test extends fix_course_delete_module_test {

    /**
     * Test for get/set modulename & get/set contextid.
     *
     * @covers \tool_fix_course_delete_module\diagnoser
     */
    public function test_diagnoser_class() {
        global $DB;

        [$deletemultitask, $deletepagetask, $deleteurltask, $deletebooktask, $deletelabeltask, $exceptionthrown]
            = $this->setup_test();

        // Test creating a diagnosis object.
        $diagnoserpagetask  = new diagnoser($deletepagetask);
        $diagnoserurltask   = new diagnoser($deleteurltask);
        $diagnosermultitask = new diagnoser($deletemultitask);
        $diagnoserbooktask  = new diagnoser($deletebooktask);
        $diagnoserlabeltask  = new diagnoser($deletelabeltask);

        $expectedsymptomspage  = [(string) $this->page->cmid =>
                                  [get_string('symptom_module_table_record_missing', 'tool_fix_delete_modules')]];
        $expectedsymptomsurl   = [(string) $this->url->cmid =>
                                  [get_string('symptom_module_table_record_missing', 'tool_fix_delete_modules'),
                                   get_string('symptom_course_module_table_record_missing', 'tool_fix_delete_modules')
                                  ]
        ];
        $expectedsymptomsmulti = [get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules') =>
                                  [get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules')]];
        $expectedsymptomsbook  = [get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules') =>
                                  [get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules')]];
        $expectedsymptomslabel = [(string) $this->label->cmid =>
                                [get_string('symptom_course_section_table_record_missing', 'tool_fix_delete_modules')]];

        $expecteddiagnosispagetask  = new diagnosis($deletepagetask, $expectedsymptomspage);
        $expecteddiagnosisurltask   = new diagnosis($deleteurltask, $expectedsymptomsurl);
        $expecteddiagnosismultitask = new diagnosis($deletemultitask, $expectedsymptomsmulti);
        $expecteddiagnosisbooktask  = new diagnosis($deletebooktask, $expectedsymptomsbook);
        $expecteddiagnosislabeltask  = new diagnosis($deletelabeltask, $expectedsymptomslabel);

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
        $expectedsymptomsmulti = [get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules') =>
                                  [get_string('symptom_multiple_modules_in_task', 'tool_fix_delete_modules')],
                                  get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules') =>
                                  [get_string('symptom_adhoc_task_record_missing', 'tool_fix_delete_modules')]
        ];
        $expecteddiagnosismultitask = new diagnosis($deletemultitask, $expectedsymptomsmulti);
        $this->assertTrue($deletemultitask->is_multi_module_task());
        $this->assertFalse($deletemultitask->task_record_exists());
        $this->assertEquals($expecteddiagnosismultitask, $diagnosermultitask->get_diagnosis());

        // Check diagnoser for book deletion task (non-existant task).
        $this->assertFalse($deletebooktask->is_multi_module_task());
        $this->assertFalse($deletebooktask->task_record_exists());
        $this->assertEquals($expecteddiagnosisbooktask, $diagnoserbooktask->get_diagnosis());

        // Check diagnoser for label deletion task.
        $this->assertFalse($deletelabeltask->is_multi_module_task());
        $this->assertTrue($deletelabeltask->task_record_exists());
        $this->assertEquals($expecteddiagnosislabeltask, $diagnoserlabeltask->get_diagnosis());

        unset($diagnosermultitask, $diagnoserpagetask, $diagnoserurltask, $diagnoserbooktask);

        if ($exceptionthrown) {
            $this->expectException('moodle_exception');
            throw $exceptionthrown;
        } else {
            $this->assertTrue($exceptionthrown, "Expected Exception wasn't thrown for line 151");
        }

    }
}
