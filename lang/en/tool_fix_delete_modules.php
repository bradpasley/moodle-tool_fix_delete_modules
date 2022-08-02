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

/**
 * Plugin strings are defined here.
 *
 * @package     tool_fix_delete_modules
 * @category    string
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['button_delete_mod_without_backup']  = 'Permanently Delete Module';
$string['button_separate_modules']           = 'Separate Into Individual Module Tasks';
$string['displaypage']                       = 'Check & Fix Delete Modules';
$string['error_dne_context']                 = 'Module ({$a}) record not found in the context table';
$string['error_dne_coursemodules']           = 'Module ({$a}) record not found in the course module table';
$string['error_dne_files']                   = 'Module ({$a}) records not found in the files table';
$string['error_dne_grades']                  = 'Module ({$a}) records not found in the grades tables';
$string['error_dne_moduletable']             = 'record not found in {$a} table';
$string['error_dne_moduleidinmoduletable']   = 'Module ({$a}) ';
$string['error_dne_recyclebin']              = 'Module ({$a}) records not found in the recyclebin table';
$string['heading_coursemodules']             = 'Course module(s)';
$string['pluginname']                        = 'Fix Delete Modules';
$string['table_adhoctasks']                  = 'Adhoc tasks table';
$string['table_context']                     = 'Context table';
$string['table_coursemodules']               = 'Course modules table';
$string['table_files']                       = 'Files table stats';
$string['table_grades']                      = 'Grades data';
$string['table_modules']                     = 'Modules table';
$string['table_modules_empty_explain']       = 'If there is no data record for the module here, it is not possible to backup the module to the recycle bin, but it is possible to wipe the remnant data of the module by clicking the button below. Then after the next adhoc task run, it should complete successfully.';
$string['table_recyclebin']                  = 'Course Recycle bin table';
$string['setting_manage_general_title']      = 'Fix Delete Modules settings';
$string['setting_manage_general_desc']       = 'Adjust which course_delete_module fix settings';
$string['setting_minimumfaildelay_title']    = 'Minimum faildelay';
$string['setting_minimumfaildelay_desc']     = 'Only show course_delete_module adhoc tasks with a minimum faildelay (in seconds)';
$string['success_none_found']                = 'No course_delete_module tasks in queue';
