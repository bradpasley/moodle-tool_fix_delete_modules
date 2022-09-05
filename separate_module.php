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
 * Separate into individual Module delete adhoc tasks
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

use tool_fix_delete_modules\reporter;

// Retrieve parameters.
$action       = required_param('action', PARAM_ALPHANUMEXT);
$taskid       = required_param('taskid', PARAM_INT);

if ($action == 'separate_module') {

    require_sesskey();

    $url = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
    $prevurl = new moodle_url('/admin/tool/fix_delete_modules/index.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
    $PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'). " - separating module tasks");
    $renderer = $PAGE->get_renderer('core');

    echo $OUTPUT->header();

    $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
    $reporter = new reporter(true, $minimumfaildelay);

    // Output template rendered results of fixing the course module.
    echo $reporter->fix_tasks(array($taskid));

    // Return to main page link.
    $mainurl    = new moodle_url('index.php');
    $urlstring  = html_writer::link($mainurl, get_string('returntomainlinklabel', 'tool_fix_delete_modules'));
    echo get_string('separatetask_returntomainpagesentence', 'tool_fix_delete_modules', $urlstring);

    echo $OUTPUT->footer();
} else {
    throw new moodle_exception('error:actionnotfound', 'block_teachercontact', $prevurl, $action);
}
