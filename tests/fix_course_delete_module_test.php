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

use core\task\adhoc_task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../classes/diagnosis.php");
require_once(__DIR__ . "/../classes/delete_module.php");
require_once(__DIR__ . "/../classes/delete_task_list.php");

/**
 * The fix_course_delete_module_test base test class.
 *
 * Tests the setup of course/modules/tasks for other tests.
 *
 * @package     tool_fix_delete_modules
 * @category    test
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_course_delete_module_test extends \advanced_testcase {

    /** @var $user moodle user object*/
    public $user;
    /** @var $course moodle course object*/
    public $course;
    /** @var $page moodle module object*/
    public $page;
    /** @var $pagecm moodle course module object*/
    public $pagecm;
    /** @var int course module contextid*/
    public $pagecontextid;
    /** @var $url moodle module object*/
    public $url;
    /** @var $urlcm moodle course module object*/
    public $urlcm;
    /** @var int course module contextid*/
    public $urlcontextid;
    /** @var $book moodle module object*/
    public $book;
    /** @var $bookcm moodle course module object*/
    public $bookcm;
    /** @var int course module contextid*/
    public $bookcontextid;
    /** @var $assign moodle module object*/
    public $assign;
    /** @var $assigncm moodle course module object*/
    public $assigncm;
    /** @var int course module contextid*/
    public $assigncontextid;
    /** @var $quiz moodle module object*/
    public $quiz;
    /** @var quizcm moodle course module object*/
    public $quizcm;
    /** @var int course module contextid*/
    public $quizcontextid;
    /** @var $label moodle module object*/
    public $label;
    /** @var $labelcm moodle course module object*/
    public $labelcm;
    /** @var int course module contextid*/
    public $labelcontextid;
    /** @var adhoc_task object */
    public $removaltaskassign;
    /** @var adhoc_task object */
    public $removaltaskmulti;
    /** @var adhoc_task object */
    public $removaltaskpage;
    /** @var adhoc_task object */
    public $removaltaskurl;
    /** @var adhoc_task object */
    public $removaltaskbook;
    /** @var adhoc_task object */
    public $removaltasklabel;

    /**
     * Setup test.
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();

        // Ensure all adhoc tasks/cache are cleared.
        if (isset(\core\task\manager::$miniqueue)) {
            \core\task\manager::$miniqueue = [];
        } // Clear the cached queue.
        $DB->delete_records('task_adhoc');

        // Setup a course with a page, a url, a book, and an assignment and a quiz module.
        $this->user     = $this->getDataGenerator()->create_user();
        $this->course   = $this->getDataGenerator()->create_course();
        $this->page     = $this->getDataGenerator()->create_module('page', array('course' => $this->course->id));
        $this->pagecm   = get_coursemodule_from_id('page', $this->page->cmid);
        $this->url      = $this->getDataGenerator()->create_module('url', array('course' => $this->course->id));
        $this->urlcm    = get_coursemodule_from_id('url', $this->url->cmid);
        $this->book     = $this->getDataGenerator()->create_module('book', array('course' => $this->course->id));
        $this->bookcm   = get_coursemodule_from_id('book', $this->book->cmid);
        $this->assign   = $this->getDataGenerator()->create_module('assign', array('course' => $this->course->id));
        $this->assigncm = get_coursemodule_from_id('assign', $this->assign->cmid);
        $this->quiz     = $this->getDataGenerator()->create_module('quiz', array('course' => $this->course->id));
        $this->quizcm   = get_coursemodule_from_id('quiz', $this->quiz->cmid);
        $this->label    = $this->getDataGenerator()->create_module('label', array('course' => $this->course->id));
        $this->labelcm  = get_coursemodule_from_id('label', $this->label->cmid);
        $this->pagecontextid   = (\context_module::instance($this->page->cmid))->id;
        $this->urlcontextid    = (\context_module::instance($this->url->cmid))->id;
        $this->assigncontextid = (\context_module::instance($this->assign->cmid))->id;
        $this->quizcontextid   = (\context_module::instance($this->quiz->cmid))->id;
        $this->labelcontextid  = (\context_module::instance($this->label->cmid))->id;

        // Delete page & quiz table record to replicate failed course_module_delete adhoc tasks.
        $DB->delete_records('page');
        $DB->delete_records('quiz');

        // Delete the url mod's course_module record to replicate a failed course_module_delete adhoc task.
        $DB->delete_records('course_modules', array('id' => $this->url->cmid));

        // Remove cmid from sequence for label.
        $sql = "SELECT * FROM {course_sections} WHERE course = ? AND sequence LIKE ?";
        $section = $DB->get_record_sql($sql, [$this->course->id, '%' . $this->label->cmid . '%']);
        $sequences = explode(',', $section->sequence);
        $newsequence = [];
        foreach ($sequences as $sequence) {
            if ($sequence != $this->label->cmid) {
                $newsequence[] = $sequence;
            }
        }
        $section->sequence = implode(',', $newsequence);
        $DB->update_record('course_sections', $section);

        // Setup Adhoc tasks, but don't queue them.
        // Setup delete assign adhoc task.
        $this->removaltaskassign = new \core_course\task\course_delete_modules();
        $assigndata = [
            'cms' => [$this->assigncm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltaskassign->set_custom_data($assigndata);

        // Setup delete mutli-module adhoc task.
        $this->removaltaskmulti = new \core_course\task\course_delete_modules();
        $cmsarray = array((string) $this->assigncm->id => array('id' => $this->assigncm->id),
                          (string) $this->quizcm->id   => array('id' => $this->quizcm->id));
        $multidata = [
            'cms' => $cmsarray,
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltaskmulti->set_custom_data($multidata);

        // Setup delete mutli-module adhoc task.
        $this->removaltaskpage = new \core_course\task\course_delete_modules();
        $pagedata = [
            'cms' => [$this->pagecm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltaskpage->set_custom_data($pagedata);

        // Setup adhoc task for url module deletion.
        $this->removaltaskurl = new \core_course\task\course_delete_modules();
        $urldata = [
            'cms' => [$this->urlcm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltaskurl->set_custom_data($urldata);

        // Setup adhoc task for book module deletion.
        $this->removaltaskbook = new \core_course\task\course_delete_modules();
        $bookdata = [
            'cms' => [$this->bookcm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltaskbook->set_custom_data($bookdata);

        // Setup adhoc task for label module deletion.
        $this->removaltasklabel = new \core_course\task\course_delete_modules();
        $labeldata = [
            'cms' => [$this->labelcm],
            'userid' => $this->user->id,
            'realuserid' => $this->user->id
        ];
        $this->removaltasklabel->set_custom_data($labeldata);
    }

    /**
     * Test for setting up course, modules and course module adhoc tasks.
     *
     * @coversNothing
     */
    public function test_delete_task_setup() {
        global $DB;
        $this->resetAfterTest(true);

        // The assign & book module exists in the course modules table & other tables.
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->assign->cmid)));
        $this->assertTrue($DB->record_exists('assign', array('id' => $this->assigncm->instance)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->book->cmid)));
        $this->assertTrue($DB->record_exists('book', array('id' => $this->bookcm->instance)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->label->cmid)));
        $this->assertTrue($DB->record_exists('label', array('id' => $this->labelcm->instance)));

        // Check page & quiz table records deleted.
        $this->assertFalse($DB->record_exists('page', array('id' => $this->pagecm->instance)));
        $this->assertFalse($DB->record_exists('quiz', array('id' => $this->quizcm->instance)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->page->cmid)));
        $this->assertTrue($DB->record_exists('course_modules', array('id' => $this->quiz->cmid)));

        // Delete the url mod's course_module record to replicate a failed course_module_delete adhoc task.
        $this->assertFalse($DB->record_exists('course_modules', array('id' => $this->url->cmid)));
        $this->assertTrue($DB->record_exists('url', array('id' => $this->urlcm->instance)));
    }

    /**
     * Utility function to find the adhoc task's id from the database table.
     *
     * @param \core\task\adhoc_task $task the adhoc_task object from which to find the taskid.
     * @return int taskid.
     **/
    public function find_taskid(\core\task\adhoc_task $task) {
        global $DB;

        $dbtasks = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'));
        $taskid = 0;
        foreach ($dbtasks as $dbtaskid => $dbtask) {
            if ($dbtask->customdata === $task->get_custom_data_as_string()) {
                $taskid = $dbtaskid;
            }
        }
        return $taskid;
    }
}
