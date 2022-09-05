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
 * Main GUI page; displays reports and fix recommendations.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir.'/moodlelib.php');
require_once(__DIR__.'/form.php');

use tool_fix_delete_modules\reporter as reporter;
require_login();

admin_externalpage_setup('tool_fix_delete_modules');

$url = new moodle_url('/admin/tool/fix_delete_modules/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'tool_fix_delete_modules'));
$PAGE->set_heading(get_string('pluginname', 'tool_fix_delete_modules'));

$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();

// Header.
echo $OUTPUT->heading(get_string('displaypage', 'tool_fix_delete_modules'));

// Display content of report/diagnosis/fix content.
$minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
$reporter = new reporter(true, $minimumfaildelay);

$pagesubtitle = get_string('displaypage-subtitle', 'tool_fix_delete_modules');
$reports      = $reporter->get_tables_report();
$diagnoses    = $reporter->get_diagnosis();
if ($reports == '') { // No report means no adhoc tasks in queue.
    $diagnoses = html_writer::tag('p',
                                  get_string('success_none_found', 'tool_fix_delete_modules'),
                                  array("class" => "text-success"));
}
if ($diagnoses == '') { // No diagnoses means no issues with queued adhoc tasks.
    $diagnoses = html_writer::tag('p',
                                  get_string('success_no_issues', 'tool_fix_delete_modules'),
                                  array("class" => "text-success"));
}
$maindata  = ['pagesubtitle' => $pagesubtitle,
              'reports' => $reports,
              'diagnoses' => $diagnoses];
$output = $OUTPUT->render_from_template('tool_fix_delete_modules/main_elements', $maindata);

if ($output == '') {
    $output = html_writer::tag('p',
                              get_string('success_none_found', 'tool_fix_delete_modules'),
                              array("class" => "text-success"));
}

echo $output;

echo $OUTPUT->footer();



