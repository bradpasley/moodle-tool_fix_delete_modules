<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package     tool_fix_delete_modules
 * @category    admin
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir.'/moodlelib.php');

admin_externalpage_setup('tool_fix_delete_modules');

$url = new moodle_url('/admin/tool/fix_delete_modules/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
$PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'));
$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();

// 1) Search current 'stuck' course_module_delete failure tasks

$adhoctable = new html_table();
$cms = null;
if ($adhocrecords = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'))) {
    $adhoctable->head = array_keys((array) current($adhocrecords));
    foreach ($adhocrecords as $record) {
        $row = array();
        foreach ($record as $key => $value) {
            $row[] = $value;
            if ($key == 'customdata') { // Grab data from customdata.
                $customdata = json_decode($value);
                $cms = current($customdata->cms);
            }
        }
        $adhoctable->data[] = $row;
    }
    echo $OUTPUT->heading(get_string('table_adhoctasks', 'tool_fix_delete_modules'));
    echo html_writer::table($adhoctable);

    // Display Course Module table.
    $cmtable = new html_table();
    if (!is_null($cms) && $cmrecords = $DB->get_records('course_modules',
                                                        array('course' => $cms->course),
                                                        '',
                                                        'id, course, module, instance, section, idnumber, deletioninprogress')) {
        $cmtable->head   = array_keys((array) current($cmrecords));
        foreach ($cmrecords as $cmrecord) {
            $row = array();
            $moduleofconcern = false;
            foreach ($cmrecord as $key => $value) {
                if ($moduleofconcern || ($key == 'id' && $value == $cms->id)) {
                    $moduleofconcern = true;
                    $row[] = '<b class="text-danger">'.$value.'</b>';
                } else {
                    $row[] = $value;
                }
            }
            $cmtable->data[] = $row;
        }
        echo $OUTPUT->heading(get_string('table_coursemodules', 'tool_fix_delete_modules'));
        echo html_writer::table($cmtable);
    }

    // Display table of specific.
    $moduletable = new html_table();
    if (!is_null($cms)) {
        $modulename  = current($DB->get_records('modules', array('id' => $cms->module), '', 'name'))->name;
        if ($modulerecords = $DB->get_records($modulename, array('course' => $cms->course))) {
            $moduletable->head   = array_keys((array) current($modulerecords));
            $moduleofconcernfound = false;
            foreach ($modulerecords as $record) {
                $row = array();
                $moduleofconcern = false;
                foreach ($record as $key => $value) {
                    if ($moduleofconcern || ($key == 'id' && $value == $cms->instance)) {
                        $moduleofconcern = true;
                        $moduleofconcernfound = true;
                        $row[] = '<b class="text-danger">'.$value.'</b>';
                    } else {
                        $row[] = $value;
                    }
                }
                $moduletable->data[] = $row;
            }
        }
        echo $OUTPUT->heading(get_string('table_modules', 'tool_fix_delete_modules')." ($modulename)");
        echo html_writer::table($moduletable);
        if (!$moduleofconcernfound) {
            echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in '.$modulename.' table</b>';
        }
    }
    // Display table of specific.
    $recyclebintable = new html_table();
    if (!is_null($cms)
        && $recyclebinrecords = $DB->get_records('tool_recyclebin_course', array('courseid' => $cms->course))) {
        $recyclebintable->head = array_keys((array) current($recyclebinrecords));
        $moduleofconcernfound  = false;
        foreach ($recyclebinrecords as $record) {
            $row = array();
            $moduleofconcern = false;
            foreach ($record as $key => $value) {
                if ($moduleofconcern || ($key == 'id' && $value == $cms->instance)) {
                    $moduleofconcern = true;
                    $moduleofconcernfound = true;
                    $row[] = '<b class="text-danger">'.$value.'</b>';
                } else {
                    $row[] = $value;
                }
            }
            $recyclebintable->data[] = $row;
        }
        echo $OUTPUT->heading(get_string('table_recyclebin', 'tool_fix_delete_modules'));
        echo html_writer::table($recyclebintable);
        if (!$moduleofconcernfound) {
            echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in tool_recyclebin_course table</b>';
        }
    }
} else { // No course_module_delete task in adhoc task queue... Show "Everything's fine".
    echo '<b class="text-success">No course_delete_module tasks in queue</b>';
}


// 2) Display table of these, including diagnosis

echo $OUTPUT->footer();

// 3) Buttons to dry run / run a fix

// Extra functions: * track course_delete_modules when they run & log stack trace
