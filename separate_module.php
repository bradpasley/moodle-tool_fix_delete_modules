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
 * Delete Modules but skip pre-delete functions of course/lib.php function
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot.'/lib/classes/task/logmanager.php');
require_login();

// Retrieve parameters.
$action       = required_param('action', PARAM_ALPHANUMEXT);
$taskid       = required_param('taskid', PARAM_INT);

if ($action == 'separate_module') {

    $url = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
    $prevurl = new moodle_url('/admin/tool/fix_delete_modules/index.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
    $PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'). " - separating module tasks");
    $renderer = $PAGE->get_renderer('core');

    echo $OUTPUT->header();

    // Create individual adhoc tasks & remove original task.
    if ($originaladhoctaskdata = get_original_cmdelete_adhoctask_data($taskid)) {
        echo separate_clustered_task_into_modules($originaladhoctaskdata, $taskid, true);
    } else {
        $errorstring = get_string('separatetask_error_dnfadhoctask', 'tool_fix_delete_modules', $taskid);
        echo html_writer::tag('p', $errorstring, array('class' => "text-danger"));

        $mainurl   = new moodle_url(__DIR__.'index.php');
        $urlstring  = html_writer::link($mainurl, get_string('returntomainlinklabel', 'tool_fix_delete_modules'));
        echo get_string('separatetask_returntomainpagesentence', 'tool_fix_delete_modules', $urlstring);
    }
    echo $OUTPUT->footer();
} else {
    throw new moodle_exception('error:actionnotfound', 'block_teachercontact', $prevurl, $action);
}
