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

// 1) Search current 'stuck' course_module_delete failure tasks

$cms = get_cms_from_adhoctask();
if ($adhoctable = get_adhoctasks(true)) {
    echo $OUTPUT->heading(get_string('table_adhoctasks', 'tool_fix_delete_modules'));
    echo html_writer::table($adhoctable);

    // Display Course Module table.
    echo $OUTPUT->heading(get_string('table_coursemodules', 'tool_fix_delete_modules'));
    if (!is_null($cms) && $cmtable = get_course_module_table($cms, true)) {
        echo html_writer::table($cmtable);
    } else {
        echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in course module table</b>';
    }

    // Display table of specific.
    if ($moduletable = get_module_table($cms, true)) {
        echo $OUTPUT->heading(get_string('table_modules', 'tool_fix_delete_modules')." ($modulename)");
        echo html_writer::table($moduletable);
    } else {
        $urlparams  = array('action' => 'delete_module');
        $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
        $modulename = get_module_name($cms);
        $customdata = array('cmid'          => $cms->id,
                            'cminstanceid'  => $cms->instance,
                            'cmname'        => $modulename);

        $mform = new fix_delete_modules_form($actionurl, $customdata);
        echo $OUTPUT->heading(get_string('table_modules', 'tool_fix_delete_modules')." ($modulename)");
        echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in '.$modulename.' table</b>';
        $mform->display();
    }

    // Display context table data for this module.
    $contexttable = new html_table();
    echo $OUTPUT->heading(get_string('table_context', 'tool_fix_delete_modules'));
    $moduleofconcernfound  = false;
    $modcontextid = null;
    if (!is_null($cms)
        && $contexttable = get_context_table($cms, true)) {

        echo html_writer::table($contexttable);
    } else {
        echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in context table</b>';
    }

    // Display file table data for this module.
    $filestable = new html_table();
    echo $OUTPUT->heading(get_string('table_files', 'tool_fix_delete_modules'));
    if ($filestable = get_files_table($cms, true)) {
        echo html_writer::table($filestable);
    } else {
        echo '<b>No File table records related to Module (cm id: '.$cms->id.' cm instance '.$cms->instance.').</b>';
    }

    // Display tool_recyclebin_course table data for this course.
    $recyclebintable = new html_table();
    echo $OUTPUT->heading(get_string('table_recyclebin', 'tool_fix_delete_modules'));
    if ($recyclebintable = get_recycle_table($cms, true)) {
        echo html_writer::table($recyclebintable);
    } else {
        echo '<b class="text-danger">Module (cm id: '.$cms->id.' cm instance '.$cms->instance.') not found in tool_recyclebin_course table</b>';
    }
} else { // No course_module_delete task in adhoc task queue... Show "Everything's fine".
    echo '<b class="text-success">No course_delete_module tasks in queue</b>';
}


// 2) Display table of these, including diagnosis

echo $OUTPUT->footer();

// 3) Buttons to dry run / run a fix

// Extra functions: * track course_delete_modules when they run & log stack trace
