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

/**
 * get_adhoctasks()
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_adhoctasks(bool $htmloutput = false) {
    global $DB;
    if ($adhocrecords = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'))) {

        if ($htmloutput) { // HTML GUI output.
            return get_htmltable($adhocrecords);
        } else {
            $adhoctable   = array();
            $adhoctable[] = array_keys((array) current($adhocrecords));
            foreach ($adhocrecords as $record) {
                $row = (array) $record;
                $adhoctable[] = $row;
            }
            return $adhoctable;
        }
    }
    return false;
}

/**
 * get_course_module_table()
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_course_module_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    if (!is_null($cms) && $cmrecords = $DB->get_records('course_modules',
        array('course' => $cms->course, 'id' => $cms->id),
              '',
              'id, course, module, instance, section, idnumber, deletioninprogress')) {

        if ($htmloutput) { // HTML GUI output.
            if (!$cmrecords) {
                return false;
            } else {
                return get_htmltable($cmrecords);
            }
        } else { // CLI output.
            $cmtable   = array();
            $cmtable[] = array_keys((array) current($cmrecords));
            foreach ($cmrecords as $record) {
                $row = (array) $record;
                $cmtable[] = $row;
            }
            return $cmtable;
        }
    }
}

/**
 * get_module_table()
 *
 * Get the table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_module_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    $modulename  = get_module_name($cms);

    if (!is_null($cms) && $records = $DB->get_records($modulename,
            array('course' => $cms->course, 'id' => $cms->instance))) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            $table[] = array_keys((array) current($records));
            foreach ($records as $record) {
                $row = (array) $record;
                $table[] = $row;
            }
            return $table;
        }
    }
}

/**
 * get_context_table()
 *
 * Get the context table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_context_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    if (!is_null($cms) && $records = $DB->get_records('context', array('contextlevel' => '70', 'instanceid' => $cms->id))) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            $table[] = array_keys((array) current($records));
            foreach ($records as $record) {
                $row = (array) $record;
                $table[] = $row;
            }
            return $table;
        }
    }
}

/**
 * get_files_table()
 *
 * Get the file table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_files_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    $modcontextid = get_context_id($cms);

    if (!is_null($cms) && !is_null($modcontextid)
        && $records = $DB->get_records('files', array('contextid' => $modcontextid))) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            $table[] = array_keys((array) current($records));
            foreach ($records as $record) {
                $row = (array) $record;
                $table[] = $row;
            }
            return $table;
        }
    }
}

/**
 * get_recycle_table()
 *
 * Get the recycle table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_recycle_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    $modcontextid = get_context_id($cms);

    if (!is_null($cms) && !is_null($modcontextid)
        && $records = $DB->get_records('tool_recyclebin_course',
                                       array('courseid' => $cms->course,
                                             'section'  => $cms->section,
                                             'module'   => $cms->module))) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            $table[] = array_keys((array) current($records));
            foreach ($records as $record) {
                $row = (array) $record;
                $table[] = $row;
            }
            return $table;
        }
    }
}


/**
 * get_context_id()
 *
 * Get the contextid of the module in question.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return int
 */
function get_context_id(stdClass $cms) {
    global $DB;
    return current($DB->get_records('context', array('contextlevel' => '70', 'instanceid' => $cms->id), '', 'id'))->id;
}

/**
 * get_module_name()
 *
 * Get the name of the table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return string
 */
function get_module_name(stdClass $cms) {
    global $DB;
    return current($DB->get_records('modules', array('id' => $cms->module), '', 'name'))->name;
}


/**
 * get_cms_from_adhoctask()
 *
 * @return stdClass|null - course module info from customdata field.
 */

function get_cms_from_adhoctask() {
    global $DB;

    $adhoccustomdata = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'), '', 'customdata');
    $value = current($adhoccustomdata)->customdata;
    $customdata = json_decode($value);
    return current($customdata->cms);
}

/**
 * get_htmltable()
 *
 * @param  array - records of table
 * @return html_table
 */

function get_htmltable(array $records) {
    $table = new html_table();
    $table->head = array_keys((array) current($records));
    foreach ($records as $record) {
        $row = array();
        foreach ($record as $key => $value) {
            $row[] = $value;
        }
        $table->data[] = $row;
    }
    return $table;
}

