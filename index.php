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
require_once(__DIR__.'/form.php');
require_once(__DIR__.'/lib.php');
require_login();

admin_externalpage_setup('tool_fix_delete_modules');

$url = new moodle_url('/admin/tool/fix_delete_modules/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
$PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'));

$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();

// Header.
echo $OUTPUT->heading(get_string('table_adhoctasks', 'tool_fix_delete_modules'));

// Display database state of course_delete_modules adhoc task related tables.
$cmtasks = get_all_cmdelete_adhoctasks();
if (!is_null($cmtasks) && !empty($cmtasks)) {
    // Display each adhoc task in its own section.
    foreach ($cmtasks as $taskid => $cmtask) {
        $cms = $cmtask;

        // Some multi-module delete adhoc tasks don't contain all data.
        $cms = get_cms_infos($cms);

        // Prepare Course Module string.
        if (count($cms) > 2) {
            $cminfostring = 'cmids: '.current($cms)->id.'...'.end($cms)->id
                           .' cminstanceids: '.current($cms)->instance.'...'.end($cms)->instance;
        } else if (count($cms) > 1) {
            $cminfostring = 'cmids: '.current($cms)->id.' & '.end($cms)->id
                           .' cminstanceids: '.current($cms)->instance.' & '.end($cms)->instance;
        } else {
            if (isset($cms[0]->instance)) {
                $cminfostring = 'cm id: '.current($cms)->id.' cm instance '.current($cms)->instance;
            } else {
                $cminfostring = 'cm id: '.current($cms)->id;
            }
        }

        // Display heading of this adhoc task.
        echo $OUTPUT->heading('Course module(s): '.$cminfostring, 4);

        $originaltask = get_original_cmdelete_adhoctask($taskid);
        $adhoctable = get_adhoctasks(true, $originaltask); // Display original adhoctask custom data.

        echo html_writer::table($adhoctable);

        // Display Course Module table.
        echo $OUTPUT->heading(get_string('table_coursemodules', 'tool_fix_delete_modules'), 5);
        if (!is_null($cms) && $cmtable = get_course_module_table($cms, true)) {
            echo html_writer::table($cmtable);
        } else {
            echo html_writer::tag('b', 'Module ('.$cminfostring.') not found in course module table',
                                  array('class' => "text-danger"));
        }

        // Display context table data for this module.
        $contexttable = new html_table();
        echo $OUTPUT->heading(get_string('table_context', 'tool_fix_delete_modules'), 5);
        $moduleofconcernfound  = false;
        $modcontextid = null;
        if (!is_null($cms)
            && $contexttable = get_context_table($cms, true)) {

            echo html_writer::table($contexttable);
        } else {
            echo html_writer::tag('b', 'Module ('.$cminfostring.') not found in the context table',
                                  array('class' => "text-danger"));
        }

        // Display table of specific.
        if ($moduletableshtml = get_module_table($cms, true)) {
            echo $moduletableshtml;
        } else {
            $urlparams  = array('action' => 'delete_module');
            $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
            $modulename = get_module_name($cms);
            $customdata = array('cmid'          => $cms->id,
                                'cminstanceid'  => $cms->instance,
                                'cmname'        => $modulename);

            $mform = new fix_delete_modules_form($actionurl, $customdata);
            echo $OUTPUT->heading(get_string('table_modules', 'tool_fix_delete_modules')." ($modulename)", 5);
            echo html_writer::tag('b',
                              'Module ('.$cminfostring.')'
                              .' not found in '.$modulename.' table',
                              array('class' => "text-danger"));
            echo html_writer::tag('p', get_string('table_modules_empty_explain', 'tool_fix_delete_modules'));
            $mform->display();
        }

        echo html_writer::start_tag('hr', array('class' => 'fix_delete_modules_first_divider'));
        echo html_writer::end_tag('hr');

        // Display file table data for this module.
        $filestable = new html_table();
        echo $OUTPUT->heading(get_string('table_files', 'tool_fix_delete_modules'), 5);
        if ($filestablehtml = get_files_table($cms, true)) {
            echo $filestablehtml;
        } else {
            echo html_writer::tag('b', 'No File table records related to Module ('.$cminfostring.')',
                                  array('class' => "text-danger"));
        }

        // Display grades tables data for this module.
        $gradestable = new html_table();
        echo $OUTPUT->heading(get_string('table_grades', 'tool_fix_delete_modules'), 5);
        if ($gradestablehtml = get_grades_table($cms, true)) {
            echo $gradestablehtml;
        } else {
            echo html_writer::tag('b', 'No Grades data related to Module ('.$cminfostring.')',
                                  array('class' => "text-danger"));
        }

        echo html_writer::start_tag('hr', array('class' => 'fix_delete_modules_first_divider'));
        echo html_writer::end_tag('hr');

        // Display tool_recyclebin_course table data for this course.
        $recyclebintable = new html_table();
        echo $OUTPUT->heading(get_string('table_recyclebin', 'tool_fix_delete_modules'), 5);
        if ($recyclebintable = get_recycle_table($cms, true)) {
            echo html_writer::table($recyclebintable);
        } else {
            echo html_writer::tag('b', 'Module ('.$cminfostring.') not found in tool_recyclebin_course table',
                                  array('class' => "text-danger"));

        }
        echo html_writer::start_tag('hr', array('class' => 'fix_delete_modules_divider'));
        echo html_writer::end_tag('hr');
    }
} else { // No course_module_delete task in adhoc task queue... Show "Everything's fine".
    echo html_writer::tag('b', 'No course_delete_module tasks in queue',
                              array('class' => "text-success"));
}


echo $OUTPUT->footer();



