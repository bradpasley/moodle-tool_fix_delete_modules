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
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/blog/lib.php');
require_login();

// Retrieve parameters.
$action       = required_param('action', PARAM_ALPHANUMEXT);
$cmid         = required_param('cmid', PARAM_INT);
$modulename   = required_param('cmname', PARAM_ALPHAEXT);
$taskid       = required_param('taskid', PARAM_INT);

$prevurl = new moodle_url('/admin/tool/fix_delete_modules/index.php');

if ($action == 'delete_module') {

    $url = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
    $PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'). " - deleting module");
    $renderer = $PAGE->get_renderer('core');

    echo $OUTPUT->header();

    $cm = new stdClass();
    $cmsdata = get_all_cmdelete_adhoctasks_data(array($cmid));
    foreach ($cmsdata as $cmtaskid => $taskdata) {
        $taskdata = get_cms_infos($taskdata);
        foreach ($taskdata as $coursemoduleid => $cmdata) {
            if ($coursemoduleid == $cmid) {
                $cm = $cmdata;
                break;
            }
        }
    }
    $deleteoutput = force_delete_module_data($cm, $taskid, true);

    echo $deleteoutput;

    // Return to main page link.
    $mainurl   = new moodle_url(__DIR__.'index.php');
    $urlstring  = html_writer::link($mainurl, get_string('returntomainlinklabel', 'tool_fix_delete_modules'));
    echo get_string('deletemodule_returntomainsentence', 'tool_fix_delete_modules', $urlstring);

    echo '<p><a href="index.php">Return to Fix Delete Modules Report page</a> and check the status.</p>';

    echo $OUTPUT->footer();
} else {
    throw new moodle_exception('error:actionnotfound', 'block_teachercontact', $prevurl, $action);
}
