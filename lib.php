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
 * Library file; functions used throughout the plugin.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   Catalyst IT, 2022
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\session\exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/datalib.php');
require_once($CFG->dirroot.'/blog/lib.php');

/**
 * get_adhoctasks_table()
 *
 * Get info for displaying info about the adhoc task.
 *
 * @param int $taskid - optional - only get one taskid
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 * @param int $climinfaildelay - optional (for CLI only)
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_adhoctasks_table(int $taskid = null, bool $htmloutput = false, int $climinfaildelay = 0) {
    global $DB;

    // Setup SQL query.
    $conditions = array('classname' => '\core_course\task\course_delete_modules');
    if (isset($taskid)) { // Add query condition if taskid param is set.
        $conditions['id'] = "$taskid";
    }

    if ($adhocrecords = $DB->get_records('task_adhoc', $conditions)) {

        foreach ($adhocrecords as $adkey => $adhocrecord) {
            $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
            // Override for CLI.
            if ($climinfaildelay != 0) {
                $minimumfaildelay = $climinfaildelay;
            }
            // Exclude adhoc tasks with faildelay below minimum config setting.
            if (intval($adhocrecord->faildelay) < $minimumfaildelay) {
                unset($adhocrecords[$adkey]);
                continue;
            }
            // Filter down to only param, if not null and matches.
            if (isset($customdatacms) && json_decode($adhocrecord->customdata)->cms === $customdatacms) {
                $adhocrecords = array($adhocrecord);
            }
        }
        if (count($adhocrecords) == 0) { // When there are no tasks with min faildelay.
            return false;
        }
    }

    // Prepare output.
    if ($htmloutput) { // HTML GUI output.
        return get_htmltable($adhocrecords);
    } else { // CLI Output.
        $adhoctable   = array();
        if (count($adhocrecords) > 0 ) {
            $adhoctable[] = array_keys((array) current($adhocrecords));
            foreach ($adhocrecords as $record) {
                $row = (array) $record;
                $adhoctable[] = $row;
            }
            return get_texttable($adhoctable);
        } else {
            return false;
        }
    }
    return false;
}

/**
 * get_course_module_table()
 *
 * @param array $cms - data of course modules
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 * @param bool $returnfailedcmid - instead of returning a table, return array of failed cmids.
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks (or cmids).
 */
function get_course_module_table(array $cms, bool $htmloutput, bool $returnfailedcmid = false) {

    global $DB;

    $failedcmids = array(); // Used for when $returnfailedcmid is true.

    if (is_null($cms)) {
        return false;
    }

    // Prepare SQL Query for multi cmids.
    $cmids = array();
    foreach ($cms as $cm) {
        $cmids[] = $cm->id;
    }
    if (empty($cmids)) {
        return false;
    }

    list($sqltail, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sqltail;
    $sqlhead = 'SELECT id, course, module, instance, section, idnumber, deletioninprogress FROM {course_modules} ';

    // Retrieve Query.
    if ($cmrecords = $DB->get_records_sql($sqlhead.$where, $params)) {

        if ($returnfailedcmid) {
            foreach ($cmids as $cmid) {
                if (!in_array($cmid, array_keys($cmrecords))) {
                    $failedcmids[] = $cmid;
                }
            }
        } else if ($htmloutput) { // HTML GUI output.
            return get_htmltable($cmrecords);
        } else { // CLI output.
            $cmtable   = array();
            if (count($cmrecords) > 0 ) {
                $cmtable[] = array_keys((array) current($cmrecords));
                foreach ($cmrecords as $record) {
                    $row = (array) $record;
                    $cmtable[] = $row;
                }
                return $cmtable;
            } else {
                return false;
            }
        }
    } else { // No $cmrecords.
        if ($returnfailedcmid) {
                $failedcmids = $cmids;
                return $failedcmids;
        } else {
                return false;
        }
    }
}

/**
 * get_module_tables()
 *
 * Get the table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course modules data
 * @param int $taskid - taskid associated with this module deletion. (only used for GUI)
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 * @param bool $returnfailedcmids - instead of returning a table, return array of failed cmids.
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_module_tables(array $cms, int $taskid = 0, bool $htmloutput = false, bool $returnfailedcmids = false) {

    global $DB, $OUTPUT;

    $failedcmids = array(); // Used if $returnfailedcmids is true.

    if (is_null($cms)) {
        return false;
    }

    $modulenames = array();
    foreach ($cms as $cm) {
        if (!isset($cm->modulename)) {
            if ($returnfailedcmids) {
                $failedcmids[] = $cm->id;
            }
            continue;
        }

        // For $modulenames, push each module as [instance -> id], associated with its modulename.
        $cmarray = array(''.$cm->instance => $cm->id);
        if (isset($modulenames[$cm->modulename])) {
            $modulenames[$cm->modulename] += $cmarray;
        } else {
            $modulenames[$cm->modulename] = $cmarray;
        }
    }

    // Prepare SQL Query for each type of module table.
    $outputtables = '';
    foreach ($modulenames as $modulename => $modulenameids) {

        if (empty(array_keys($modulenameids))) {
            if ($returnfailedcmids) {
                foreach (array_values($modulenameids) as $cmid) {
                    $failedcmids[] = $cmid;
                }
            }
            continue;
        }

        list($sqltail, $params) = $DB->get_in_or_equal(array_keys($modulenameids), SQL_PARAMS_NAMED, 'instanceid');
        $where = 'WHERE id '. $sqltail;
        $sqlhead = 'SELECT * FROM {'.$modulename.'} ';

        // Retrieve Query.
        if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {

            if ($returnfailedcmids) {
                foreach ($modulenames[$modulename] as $cminstanceid => $cmid) {
                    if (!in_array($cminstanceid, array_keys($records))) {
                        $failedcmids[] = $cmid;
                    }
                }
            } else if ($htmloutput) { // HTML GUI output.
                // Display any lost modules and the button to process it.
                $heading = $OUTPUT->heading(get_string('table_modules', 'tool_fix_delete_modules')." ($modulename)", 5);
                $buttons = '';
                if ($lostcmids = get_any_lost_modules($modulenameids, $records)) {
                    foreach ($lostcmids as $cmid) {
                        $buttons .= get_html_fix_button_for_lost_modules($cmid, $modulename, $taskid);
                    }
                }
                if (!$records) {
                    continue;
                } else {
                    $outputtables .= $heading.$buttons;
                    $thistable     = get_htmltable($records);
                    $outputtables .= html_writer::table($thistable);
                }
            } else { // CLI output.
                $table   = array();
                if (count($records) > 0 ) {
                    $table[] = array_keys((array) current($records));
                    foreach ($records as $record) {
                        $row = (array) $record;
                        $table[] = $row;
                    }
                    $outputtables .= get_texttable($table);
                } else {
                    return false;
                }
            }
        } else {
            foreach (array_values($modulenameids) as $cmid) {
                $failedcmids[] = $cmid;
            }
        }
    }
    if ($returnfailedcmids) {
        return $failedcmids;
    }
    return $outputtables;

}

/**
 * get_context_table()
 *
 * Get the context table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course modules data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_context_table(array $cms, bool $htmloutput) {

    global $DB;

    if (is_null($cms)) {
        return false;
    }

    // Prepare SQL Query for multi cmids.
    $cmids = array();
    foreach ($cms as $cm) {
        $cmids[] = $cm->id;
    }

    if (empty($cmids)) {
        return false;
    }

    list($sqltail, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'instanceid');
    $where = 'WHERE contextlevel = 70 AND instanceid '. $sqltail;
    $sqlhead = 'SELECT * FROM {context} ';

    // Retrieve Query.
    if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            if (count($records) > 0 ) {
                $table[] = array_keys((array) current($records));
                foreach ($records as $record) {
                    $row = (array) $record;
                    $table[] = $row;
                }
                return $table;
            } else {
                return false;
            }
        }
    }
}

/**
 * get_files_table()
 *
 * Get the file table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_files_table(array $cms, bool $htmloutput) {

    global $DB;

    if (is_null($cms)) {
        return false;
    }

    // Prepare SQL Query for multi cmids.
    $cmcontextids = array();
    foreach ($cms as $cm) {
        if (isset($cm->modulecontextid)) {
            $cmcontextids[] = $cm->modulecontextid;
        }
    }

    if (empty($cmcontextids)) {
        return false;
    }

    list($sqltail, $params) = $DB->get_in_or_equal($cmcontextids, SQL_PARAMS_NAMED, 'contextid');
    $where = 'WHERE contextid '. $sqltail;
    $sqlhead = 'SELECT * FROM {files} ';

    // Retrieve Query.
    if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {

        // Get count of grades for this grade item & add to record.
        $filecount = 0;
        $componentfileareacounts = array();
        $mimetypecounts = array();
        foreach ($records as $rkey => $record) {
            if ($record->filename != ".") { // Only count files.
                $filecount++;
                // Count component/filearea.
                if (isset($componentfileareacounts[$record->component][$record->filearea])) {
                    $componentfileareacounts[$record->component][$record->filearea] += 1;
                } else {
                    $componentfileareacounts[$record->component][$record->filearea] = 1;
                }
                // Count mimetype.
                if (isset($mimetypecounts[$record->mimetype])) {
                    $mimetypecounts[$record->mimetype] += 1;
                } else {
                    $mimetypecounts[$record->mimetype] = 1;
                }
            }
        }

        // Flatten into one table.
        $records = array();
        $records[] = (object) array('filecount' => "$filecount");
        foreach ($componentfileareacounts as $componentkey => $componentcounts) {
            foreach ($componentcounts as $fileareakey => $count) {
                $records[] = (object) array("component/filearea: $componentkey/$fileareakey" => "$count");
            }
        }
        foreach ($mimetypecounts as $mimetypekey => $count) {
            $records[] = (object) array("mimetype: $mimetypekey" => "$count");
        }

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                $verttable = get_htmltable_vertical($records, array("name", "count"));
                return html_writer::table($verttable);
            }
        } else { // CLI output.
            $table   = array();
            if (count($records) > 0 ) {
                $table[] = array_keys((array) current($records));
                foreach ($records as $record) {
                    $row = (array) $record;
                    $table[] = $row;
                }
                return $table;
            } else {
                return false;
            }
        }
    }
}

/**
 * get_grades_table()
 *
 * Get the file table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_grades_table(array $cms, bool $htmloutput) {

    global $DB;

    if (count($cms) > 1) {
        return html_writer::tag('b', 'Since this course_module_delete Adhoc Task'
                                     .' contains more than one module, Grades data'
                                     .' will not be displayed',
                                array('class' => "text-danger"));
    }

    $cm = current($cms);
    $modname = get_module_name($cm);

    if (!is_null($cm) && $records = $DB->get_records('grade_items',
                                                      array('itemmodule' => $modname,
                                                            'iteminstance' => $cm->instance,
                                                            'courseid' => $cm->course))) {

        // Get count of grades for this grade item & add to record.
        foreach ($records as $rkey => $record) {
            $gradescount = get_grades_count($record->id);
            $recordarray = (array) $record;
            $recordarray = array('grades_count' => "$gradescount") + $recordarray;
            $records[$rkey] = (object) $recordarray;
        }

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return html_writer::table(get_htmltable($records));
            }
        } else { // CLI output.
            $table   = array();
            if (count($records) > 0 ) {
                $table[] = array_keys((array) current($records));
                foreach ($records as $record) {
                    $row = (array) $record;
                    $table[] = $row;
                }
                return $table;
            } else {
                return false;
            }
        }
    }
}

/**
 * get_grades_count()
 *
 * Get the count of grades related to the course module's gradeitem.
 *
 * @param int $gradeitemid - id of the gradeitem
 *
 * @return int - number of grades for the grade item
 */
function get_grades_count(int $gradeitemid) {
    global $DB;
    return $DB->count_records('grade_grades', array('itemid' => $gradeitemid));
}

/**
 * get_recycle_table()
 *
 * Get the recycle table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_recycle_table(array $cms, bool $htmloutput) {

    global $DB;

    $cm = current($cms);

    $modcontextid = get_context_id($cm);

    if (!is_null($cm) && !is_null($modcontextid)
        && $records = $DB->get_records('tool_recyclebin_course',
                                       array('courseid' => $cm->course,
                                             'module'   => $cm->module))) {

        if ($htmloutput) { // HTML GUI output.
            if (!$records) {
                return false;
            } else {
                return get_htmltable($records);
            }
        } else { // CLI output.
            $table   = array();
            if (count($records) > 0 ) {
                $table[] = array_keys((array) current($records));
                foreach ($records as $record) {
                    $row = (array) $record;
                    $table[] = $row;
                }
                return $table;
            } else {
                return false;
            }
        }
    }
}


/**
 * get_context_id()
 *
 * Get the contextid of the module in question.
 *
 * @param stdClass $cms - course module data
 *
 * @return int|bool contextid of module
 */
function get_context_id(stdClass $cms) {
    global $DB;
    if (is_null($cms) || is_null($cms->id)) {
        return false;
    }
    $result = $DB->get_records('context', array('contextlevel' => '70', 'instanceid' => $cms->id));
    if ($result && count($result) > 0) {
        return current($result)->id;
    } else {
        return false;
    }

}

/**
 * get_module_name()
 *
 * Get the name of the table related to the course module which is failing to be deleted.
 *
 * @param stdClass $cm - course module data
 *
 * @return string
 */
function get_module_name(stdClass $cm) {
    global $DB;
    return current($DB->get_records('modules', array('id' => $cm->module), '', 'name'))->name;
}


/**
 * get_all_cmdelete_adhoctasks_data()
 *
 * @param array $coursemoduleids - CLI optional, limit by coursemodule ids.
 * @param int $climinfaildelay - CLI optional, GUI will get from config
 * @return array|null - array of course module info from customdata field.
 */
function get_all_cmdelete_adhoctasks_data(array $coursemoduleids = array(), int $climinfaildelay = 60) {
    global $DB;

    $adhoccustomdatas = $DB->get_records('task_adhoc',
                                         array('classname' => '\core_course\task\course_delete_modules'),
                                               '',
                                               'id, customdata, faildelay');
    $customdatas = array();

    // Work out mininumfaildelay filter.
    $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
    if ($climinfaildelay != 60) { // Override config setting - for CLI.
        $minimumfaildelay = $climinfaildelay;
    }

    foreach ($adhoccustomdatas as $taskid => $taskrecord) {

        // Exclude adhoc tasks with faildelay below minimum config setting.
        if (intval($taskrecord->faildelay) < $minimumfaildelay) {
            continue;
        }

        $value = $taskrecord->customdata;
        $cms   = json_decode($value)->cms;
        if (is_array($cms) && count($cms) == 1) {
            $cm = current($cms);
            // Filter by coursemoduleid (if not empty array).
            if (!empty($coursemoduleids) && in_array($cm->id, $coursemoduleids)) {
                $cms = array(''.$cm->id => $cm);
            } else { // Empty array means all coursemoduleids can be included.
                $cms = array(''.$cm->id => $cm);
            }
        } else {
            // Filter by coursemoduleid (if not empty array).
            if (!empty($coursemoduleids)) {
                foreach ($cms as $cm) {
                    if (in_array($cm->id, $coursemoduleids)) {
                        $cms[''.$cm->id] = $cm;
                    }
                }
            }
            $cms = (array) $cms;
        }
        $customdatas[''.$taskid] = $cms;
    }

    return $customdatas;
}

/**
 * get_original_cmdelete_adhoctask_data()
 *
 * @param int $taskid
 * @param int $climinfaildelay - optional, GUI will get from config
 * @return array|bool - course module info from customdata field.
 */
function get_original_cmdelete_adhoctask_data(int $taskid, int $climinfaildelay = 60) {
    global $DB;

    $adhoccustomdata = $DB->get_record('task_adhoc',
                                        array('id' => $taskid,
                                              'classname' => '\core_course\task\course_delete_modules'),
                                              'id, customdata, faildelay',
                                            IGNORE_MISSING);
    $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
    if ($climinfaildelay != 60) { // Override config setting - for CLI.
        $minimumfaildelay = $climinfaildelay;
    }

    if ($adhoccustomdata && !is_null($adhoccustomdata)) {
        // Skip filtered task.
        if (intval($adhoccustomdata->faildelay) < $minimumfaildelay) {
            return false;
        }
        $value = $adhoccustomdata->customdata;
        $cms   = json_decode($value)->cms;
        if (is_array($cms) && count($cms) == 1) {
            $cms = current($cms);
        }

        return $cms;
    } else {
        return false;
    }
}

/**
 * get_cm_info()
 *
 * get an array of cms data (split up clustered task customdata)
 *
 * @param array $adhoctask - coursemodule data from adhoctask customdata field.
 * @return array|bool - array of cms' data or false if not available.
 */
function get_cms_infos(array $adhoctask) {
    global $DB;

    // Get coursemoduleids; to be used to get the database data.
    $cmids = array();
    $cms = $adhoctask;
    foreach ($cms as $cm) {
        $cmids[] = $cm->id;
    }

    // Get the course module data.
    list($sql, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;
    if (!$cmsrecord = $DB->get_records_sql('SELECT * FROM {course_modules} '. $where, $params)) {
        return $adhoctask; // Return original.
    }

    // Process each coursemodule id which exists in the adhoc task's customdata.
    foreach ($cmids as $id) {
        if (array_key_exists($id, $cmsrecord)) { // Don't process ids not in db.

            // Add coursemodule db data if not already in $cms data.
            foreach ($cmsrecord[$id] as $key => $value) {
                if (!isset($cms[$id]->$key)) {
                    $cms[$id]->$key = $value;
                }
            }

            // Get the module context & add to cms data.
            $modulecontext = context_module::instance($id);
            $cms[$id]->modulecontextid = ''.$modulecontext->id;

            // Get the course module name & add to cms data.
            if ($modulename = $DB->get_field('modules', 'name', array('id' => $cms[$id]->module), MUST_EXIST)) {
                $cms[$id]->modulename = ''.$modulename;
            }
        }
    }

    return $cms;
}

/**
 * get_texttable()
 *
 * @param array $arraytable
 * @return string
 */
function get_texttable(array $arraytable) {
    $outputtables = '';
    foreach ($arraytable as $row) {
        foreach ($row as $cell) {
            $outputtables .= $cell.'\t';
        }
        $outputtables .= PHP_EOL;
    }
    $outputtables .= PHP_EOL;
    return $outputtables;
}


/**
 * get_htmltable()
 *
 * @param array $records - records of table
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

/**
 * get_htmltable()
 *
 * @param  array $records - records of table
 * @param  array $columntitles - column titles
 * @return html_table
 */
function get_htmltable_vertical(array $records, array $columntitles) {
    $table = new html_table();
    foreach ($columntitles as $title) {
        $table->head[] = $title;
    }
    foreach ($records as $record) {
        $row = array();
        foreach ($record as $key => $value) {
            $row[] = $key;
            $row[] = $value;
        }
        $table->data[] = $row;
    }

    return $table;
}

/**
 * course_module_delete_issues()
 *
 * used on CLI only.
 *
 * @param array $adhoctask - cmsdata of one adhoctask
 * @param int $taskid - the taskid of this adhoctask
 * @param int $minimumfaildelay - minimum faildelay of tasks.
 * @return array - array of [coursemoduleid => explaination string]
 */
function course_module_delete_issues(array $adhoctask, int $taskid, int $minimumfaildelay) {

    // Some multi-module delete adhoc tasks don't contain all data.
    $cms = get_cms_infos($adhoctask);

    if (is_null($cms) || empty(($cms))) {
        return ["Cannot find a course_module_delete adhoc task for taskid: $taskid".PHP_EOL];
    }

    // Process this adhoc tasks's course module(s).
    $results = array();

        if (!$table = get_adhoctasks_table()) {
            $results[] = "adhoc task record table record doesn't exist".PHP_EOL;
        }
        if (!$table = get_course_module_table($cms, false)) {
            $results[] = "course module table record ($stringtaskcms)".PHP_EOL;
        }
        if (!$table = get_module_tables($cms, $taskid[''.$cm->id], false)) {
            $modulename = $cm->modulename;
            $results[] = "$modulename table record for ($stringtaskcms) doesn't exist".PHP_EOL;
        }

    // Prepare Task/Course Module string.
    $stringtaskcms = get_coursemoduletask_string($cms, $taskid);

    // Get results for each table query.
    if (!get_adhoctasks_table($taskid, false, $minimumfaildelay)) {
        $results["adhoctasktable"] = "adhoc task record table record doesn't exist".PHP_EOL;
    }
    if ($failedcmids = get_course_module_table($cms, false, true)) {
        foreach ($failedcmids as $cmid) {
            $results["$cmid"] = "No course_modules table record for (cmid: $cmid) task: ($stringtaskcms)".PHP_EOL;
        }
    }
    if ($failedcmids = get_module_tables($cms, $taskid, false, true)) {
        foreach ($failedcmids as $cmid) {
            $moduleerrormessage = "No record exists in associated module table record for (cmid: $cmid) task: ($stringtaskcms)";
            if (isset($results["$cmid"])) {
                $results["$cmid"] = $results["$cmid"].$moduleerrormessage;
            } else {
                $results["$cmid"] = $moduleerrormessage;
            }
        }
    }

    return $results;
}


/**
 * force_delete_module_data()
 *
 * @param stdClass $coursemodule - cm data
 * @param int $taskid
 * @param bool $ishtmloutput - true if GUI, false if CLI
 * @return string|bool - stringoutput or false if failed.
 */
function force_delete_module_data(stdClass $coursemodule, int $taskid, bool $ishtmloutput = false) {
    global $DB;

    $coursemoduleid = $coursemodule->id;

    $outputstring = '';
    if (is_null($coursemodule)) {
        $outputstring = get_string('deletemodule_error_nullcoursemodule', 'tool_fix_delete_modules', $coursemoduleid);
        $htmlstring   = html_writer::tag('p', $outputstring, array('class' => "text-danger"));
        $outputstring = $ishtmloutput ? $htmlstring : array($outputstring.PHP_EOL);
        return $outputstring;
    }

    $nextstring = get_string('deletemodule_attemptfix', 'tool_fix_delete_modules', $coursemoduleid);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-dark"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    // Get the course module.
    if (!$cm = $DB->get_record('course_modules', array('id' => $coursemoduleid))) {
        $nextstring = get_string('deletemodule_error_dnecoursemodule', 'tool_fix_delete_modules', $coursemoduleid);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

        $cm = $coursemodule; // Attempt with param data.
    }
    // Get the module context.
    try {
        $modcontext = context_module::instance($coursemoduleid);
    } catch (dml_missing_record_exception $e) {
        $nextstring = get_string('deletemodule_error_dnemodcontext', 'tool_fix_delete_modules', $coursemoduleid);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
        $modcontext = false;
    }

    // Get the module name.
    $modulename = get_module_name($cm);

    // Remove all module files in case modules forget to do that.
    if ($modcontext) {
        $fs = get_file_storage();
        $fs->delete_area_files($modcontext->id);
        $nextstring = get_string('deletemodule_filesdeleted', 'tool_fix_delete_modules', $modcontext->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Delete events from calendar.
    if ($modulename && $events = $DB->get_records('event', array('instance' => $cm->instance, 'modulename' => $modulename))) {
        $coursecontext = context_course::instance($cm->course);
        foreach ($events as $event) {
            $event->context = $coursecontext;
            $calendarevent = calendar_event::load($event);
            $calendarevent->delete();
        }
        if (count($event) > 0) {
            $nextstring = "($modulename:instanceid:".$cm->instance."): "
                          .get_string('deletemodule_calendareventsdeleted', 'tool_fix_delete_modules', count($events));
            $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
            $textstring = array($nextstring.PHP_EOL);
            $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
        }
    }

    // Delete grade items, outcome items and grades attached to modules.
    if ($modulename && $gradeitems = grade_item::fetch_all(array('itemtype' => 'mod', 'itemmodule' => $modulename,
                                                   'iteminstance' => $cm->instance, 'courseid' => $cm->course))) {
        foreach ($gradeitems as $gradeitem) {
            $gradeitem->delete('moddelete');
        }
        if (count($gradeitems) > 0) {
            $nextstring = "(modcontextid: ".$modcontext->id." cmid:".$coursemoduleid."): "
                          .get_string('deletemodule_gradeitemsdeleted', 'tool_fix_delete_modules', count($gradeitems));
            $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
            $textstring = array($nextstring.PHP_EOL);
            $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
        }
    }

    // Delete associated blogs and blog tag instances.
    if ($modcontext) {
        blog_remove_associations_for_module($modcontext->id);
        $nextstring = get_string('deletemodule_blogsdeleted', 'tool_fix_delete_modules', $modcontext->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Delete completion and availability data; it is better to do this even if the
    // features are not turned on, in case they were turned on previously (these will be
    // very quick on an empty table).
    $DB->delete_records('course_modules_completion', array('coursemoduleid' => $cm->id));
    $nextstring = get_string('deletemodule_completionsdeleted', 'tool_fix_delete_modules', $modcontext->id);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

    $DB->delete_records('course_completion_criteria', array('moduleinstance' => $cm->id,
                                                            'course' => $cm->course,
                                                            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY));
    $nextstring = get_string('deletemodule_completioncriteriadeleted', 'tool_fix_delete_modules', $cm->course);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

    // Delete all tag instances associated with the instance of this module.
    if ($modcontext) {
        \core_tag_tag::delete_instances('mod_' . $modulename, null, $modcontext->id);
        \core_tag_tag::remove_all_item_tags('core', 'course_modules', $cm->id);

        $nextstring = get_string('deletemodule_tagsdeleted', 'tool_fix_delete_modules', $modcontext->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Notify the competency subsystem.
    \core_competency\api::hook_course_module_deleted($cm);

    // Delete the context.
    if ($modcontext) {
        \context_helper::delete_instance(CONTEXT_MODULE, $cm->id);

        $nextstring = get_string('deletemodule_contextdeleted', 'tool_fix_delete_modules', $modcontext->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Delete the module from the course_modules table.
    if ($DB->delete_records('course_modules', array('id' => $cm->id))) {
        $nextstring = get_string('deletemodule_coursemoduledeleted', 'tool_fix_delete_modules', $cm->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    } else {
        $nextstring = get_string('deletemodule_error_failcmoduledelete', 'tool_fix_delete_modules', $cm->id);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Delete module from that section.
    if (!delete_mod_from_section($cm->id, $cm->section)) {
        $nextstring = get_string('deletemodule_error_failmodsectiondelete', 'tool_fix_delete_modules', $cm->section);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    } else {
        $nextstring = get_string('deletemodule_modsectiondelete', 'tool_fix_delete_modules', $cm->section);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }

    // Trigger event for course module delete action.
    if ($modcontext) {
        $event = \core\event\course_module_deleted::create(array(
            'courseid' => $cm->course,
            'context'  => $modcontext,
            'objectid' => $cm->id,
            'other'    => array(
                'modulename' => $modulename,
                'instanceid'   => $cm->instance,
            )
        ));
        $event->add_record_snapshot('course_modules', $cm);
        $event->trigger();
    }
    rebuild_course_cache($cm->course, true);

    $datafields = '(cmid '.$coursemoduleid.' cminstance '.$cm->instance.' courseid '.$cm->course.')';
    $nextstring = get_string('deletemodule_success', 'tool_fix_delete_modules', $datafields);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

    return $outputstring;
}
/**
 * separate_clustered_task_into_modules()
 *
 * @param array $clusteredadhoctask - a course_delete_module adhoc task
 *                                    containing multiple course modules.
 * @param int   $taskid - original task id.
 * @param bool  $ishtmloutput - true = HTML output, false = plaintext output
 *
 * @return string - either HTML (GUI) or plain text (CLI)
 */
function separate_clustered_task_into_modules(array $clusteredadhoctask, int $taskid, bool $ishtmloutput = false) {

    global $DB, $USER;

    $taskcount = 0;
    $outputstring = '';
    $nextstring = get_string('separatetask_attemptfix', 'tool_fix_delete_modules', $taskid);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-dark"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

    // Create individual adhoc task for each module.
    foreach ($clusteredadhoctask as $cmid => $cmvalue) {
        // Get the course module.
        if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
            continue; // Skip it; it might have been deleted already.
        }

        // Update record, if not already updated.
        $cm->deletioninprogress = '1';
        $DB->update_record('course_modules', $cm);

        // Create an adhoc task for the deletion of the course module. The task takes an array of course modules for removal.
        $newdeletetask = new \core_course\task\course_delete_modules();
        $mainadminid = get_admin()->id;
        $newdeletetask->set_custom_data(array(
            'cms' => array($cm),
            'userid' => $mainadminid,    // Set user to main admin.
            'realuserid' => $mainadminid // Set user to main admin.
        ));

        // Queue the task for the next run.
        \core\task\manager::queue_adhoc_task($newdeletetask);
        $taskcount++;
    }
    $nextstring = get_string('separatetask_taskscreatedcount', 'tool_fix_delete_modules', $taskcount);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
    $textstring = array($nextstring.PHP_EOL);
    $outputstring .= $ishtmloutput ? $htmlstring : $textstring;

    // Remove old task.
    if ($DB->delete_records('task_adhoc', array('id' => $taskid))) {
        $nextstring = get_string('separatetask_originaltaskdeleted', 'tool_fix_delete_modules', $taskid);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-success"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    } else {
        $nextstring = get_string('separatetask_error_failedtaskdelete', 'tool_fix_delete_modules', $taskid);
        $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));
        $textstring = array($nextstring.PHP_EOL);
        $outputstring .= $ishtmloutput ? $htmlstring : $textstring;
    }
    $nextstring = get_string('separatetask_error_failedtaskdelete', 'tool_fix_delete_modules', $taskid);
    $htmlstring = html_writer::tag('p', $nextstring, array('class' => "text-danger"));

    $mainurl    = new moodle_url(__DIR__.'index.php');
    $urlstring  = html_writer::link($mainurl, get_string('returntomainlinklabel', 'tool_fix_delete_modules'));
    $htmlstring = get_string('separatetask_returntomainpagesentence', 'tool_fix_delete_modules', $urlstring);
    $clistring  = PHP_EOL.'Process these new adhoc tasks by running the adhoctask CLI command:'.PHP_EOL
                 .'\$sudo -u www-data /usr/bin/php admin/tool/task/cli/adhoc_task.php --execute'.PHP_EOL.PHP_EOL
                 .'Then re-run this script to check if any modules remain incomplete.'.PHP_EOL
                 .'\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php'.PHP_EOL;

    $outputstring .= $ishtmloutput ? $htmlstring : $clistring.PHP_EOL;

    return $outputstring;
}

/**
 * separate_clustered_task_into_modules()
 *
 * @param array $clusteredadhoctask - a course_delete_module adhoc task
 *                                    containing multiple course modules.
 * @param int   $taskid - original task id.
 * @param bool  $ishtmloutput - true = HTML output, false = plaintext output
 *
 * @return string - either HTML (GUI) or plain text (CLI)
 */

function separate_clustered_task_into_modules(array $clusteredadhoctask, int $taskid, bool $ishtmloutput = false) {

    global $DB, $USER;

    $taskcount = 0;
    $outputstring = '';
    // Create individual adhoc task for each module.
    foreach ($clusteredadhoctask as $cmid => $cmvalue) {
        // Get the course module.
        if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
            continue; // Skip it; it might have been deleted already.
        }

        // Update record, if not already updated.
        $cm->deletioninprogress = '1';
        $DB->update_record('course_modules', $cm);

        // Create an adhoc task for the deletion of the course module. The task takes an array of course modules for removal.
        $newdeletetask = new \core_course\task\course_delete_modules();
        $mainadminid = get_admin()->id;
        $newdeletetask->set_custom_data(array(
            'cms' => array($cm),
            'userid' => $mainadminid,    // Set user to main admin.
            'realuserid' => $mainadminid // Set user to main admin.
        ));

        // Queue the task for the next run.
        \core\task\manager::queue_adhoc_task($newdeletetask);
        $taskcount++;
    }
    $nextstring = $taskcount.' new individual course_delete_module Tasks have been created';
    $outputstring .= $ishtmloutput ? '<p><b class="text-success">'.$nextstring.'</b></p>'
                                     : $nextstring.PHP_EOL;

    // Remove old task.
    if ($DB->delete_records('task_adhoc', array('id' => $taskid))) {
        $nextstring = 'Original course_delete_module task (id '.$taskid.') deleted';
        $outputstring .= $ishtmloutput ? '<p><b class="text-success">'.$nextstring.'</b></p>'
                                         : $nextstring.PHP_EOL;
    } else {
        $nextstring = 'Original course_delete_module Adhoc task (id '.$taskid.') could not be found.';
        $outputstring .= $ishtmloutput ? '<p><b class="text-danger">'.$nextstring.'</b></p>'
                                         : $nextstring.PHP_EOL;
    }
    $htmlstring = '<p>Refresh <a href="index.php">Fix Delete Modules Report page</a> and check the status.</p>';
    $clistring  = PHP_EOL.'Process these new adhoc tasks by running the adhoctask CLI command:'.PHP_EOL
                 .'\$sudo -u www-data /usr/bin/php admin/cli/adhoc_task.php --execute'.PHP_EOL.PHP_EOL
                 .'Then re-run this script to check if any modules remain incomplete.'.PHP_EOL
                 .'\$sudo -u www-data /usr/bin/php admin/tool/fix_delete_modules/cli/fix_course_delete_modules.php'.PHP_EOL;

    $outputstring .= $ishtmloutput ? $htmlstring : $clistring.PHP_EOL;

    return $outputstring;
}

/**
 * get_adhoctask_from_taskid()
 *
 * @param int $taskid - the taskid of a course_delete_modules adhoc task.
 * @return \core\task\adhoc_task|bool - false if not found.
 */
function get_adhoctask_from_taskid(int $taskid) {
    $thisadhoctask = null;
    $cdmadhoctasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
    foreach ($cdmadhoctasks as $adhoctask) {
        if ($adhoctask->get_id() == $taskid) {
            $thisadhoctask = $adhoctask;
            break;
        }
    }
    if (isset($thisadhoctask)) {
        return $thisadhoctask;
    } else {
        return false;
    }
}


/**
 * get_any_lost_modules()
 *
 * A utility function to find any modules which do not have a
 * record in their corresponding module table.
 * Used within get_module_tables()
 *
 * returns
 *
 * @param array $moduleinstanceids
 * @param array $moduletablerecords
 * @return array|bool - course module ids, or false if no missing modules.
 */
function get_any_lost_modules(array $moduleinstanceids, array $moduletablerecords) {

    $lostmoduleids = array();
    foreach ($moduleinstanceids as $moduleid => $cmid) {
        $moduleidfound = false;
        foreach ($moduletablerecords as $record) {
            if ($record->id == intval($moduleid)) {
                $moduleidfound = true;
            }
        }
        if (!$moduleidfound) {
            array_push($lostmoduleids, $cmid);
        }
    }

    if (empty($lostmoduleids)) {
        return false;
    } else {
        return $lostmoduleids;
    }
}

/**
 * get_html_fix_button_for_lost_modules()
 *
 * Outputs HTML buttons for a module that is missing.
 *
 * @param int $coursemoduleid
 * @param string $modulename
 * @param int $taskid
 * @return string - HTML output
 */
function get_html_fix_button_for_lost_modules(int $coursemoduleid, string $modulename, int $taskid) {

    global $OUTPUT;

    $htmloutput = '';

    $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
    $customdata = array('cmid'          => $coursemoduleid,
                        'cmname'        => $modulename,
                        'taskid'        => $taskid);

    $mform = new fix_delete_modules_form($actionurl, $customdata);
    $htmloutput .= html_writer::tag('b',
                                    'Module (cmid: '.$coursemoduleid.')'
                                    .' not found in '.$modulename.' table',
                                    array('class' => "text-danger"));
    $htmloutput .= html_writer::tag('p', get_string('table_modules_empty_explain', 'tool_fix_delete_modules'));
    $button      = $mform->render();
    $htmloutput .= $button;

    return $htmloutput;
}

/**
 * get_html_separate_button_for_clustered_tasks()
 *
 * Outputs HTML buttons for a clustered task.
 *
 * @param int $taskid
 * @return string - HTML output
 */
function get_html_separate_button_for_clustered_tasks(int $taskid) {

    global $OUTPUT;

    $htmloutput = '';

    $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
    $params     = array('taskid' => $taskid);

    $mform = new separate_delete_modules_form($actionurl, $params);
    $htmloutput .= html_writer::tag('b',
                                    'This Adhoc task (id: '.$taskid.') contains multiple course modules.'
                                    .' Press the button below to separate these into multiple adhoc tasks.'
                                    .' This will assist in reducing the complexity of the failed'
                                    .' course_delete_modules task.',
                                    array('class' => "text-danger"));
    $htmloutput .= html_writer::tag('p', get_string('table_modules_empty_explain', 'tool_fix_delete_modules'));
    $button      = $mform->render();
    $htmloutput .= $button;

    return $htmloutput;
}

/**
 * get_coursemoduletask_string()
 *
 * returns human readible string about one course_delete_module task.
 *
 * @param array $cmsdata - one or more coursemodule data in an array.
 * @param int $taskid - the taskid of this task
 * @return string
 */
function get_coursemoduletask_string(array $cmsdata, int $taskid) {

    if ($cmsdata && count($cmsdata) > 2) {
        $stringtaskcms = 'taskid: '.$taskid
                        .' cmids: '.current($cmsdata)->id.'...'.end($cmsdata)->id
                        .' cminstanceids: '.current($cmsdata)->instance.'...'.end($cmsdata)->instance;
    } else if ($cmsdata && count($cmsdata) > 1) {
        $stringtaskcms = 'taskid: '.$taskid
                        .' cmids: '.current($cmsdata)->id.' & '.end($cmsdata)->id
                        .' cminstanceids: '.current($cmsdata)->instance.' & '.end($cmsdata)->instance;
    } else {
        if ($cmsdata && isset(current($cmsdata)->instance)) {
            $stringtaskcms = 'taskid: '.$taskid
                            .' cm id: '.current($cmsdata)->id.' cminstanceid: '.current($cmsdata)->instance;
        } else {
            $stringtaskcms = 'taskid: '.$taskid
                            .' cm id: '.current($cmsdata)->id;
        }
    }

    return $stringtaskcms;

}

/**
 * get_adhoctask_from_taskid()
 *
 * @param int $taskid - the taskid of a course_delete_modules adhoc task.
 * @return \core\task\adhoc_task|bool - false if not found.
 */
function get_adhoctask_from_taskid(int $taskid) {
    $thisadhoctask = null;
    $cdmadhoctasks = \core\task\manager::get_adhoc_tasks('\core_course\task\course_delete_modules');
    foreach ($cdmadhoctasks as $adhoctask) {
        if ($adhoctask->get_id() == $taskid) {
            $thisadhoctask = $adhoctask;
            break;
        }
    }
    if (isset($thisadhoctask)) {
        return $thisadhoctask;
    } else {
        return false;
    }
}


/**
 * get_any_lost_modules()
 *
 * A utility function to find any modules which do not have a
 * record in their corresponding module table.
 * Used within get_module_tables()
 *
 * returns
 *
 * @param array $moduleinstanceids
 * @param array $moduletablerecords
 * @return array|bool - course module ids, or false if no missing modules.
 */
function get_any_lost_modules(array $moduleinstanceids, array $moduletablerecords) {

    $lostmoduleids = array();
    foreach ($moduleinstanceids as $moduleid => $cmid) {
        $moduleidfound = false;
        foreach ($moduletablerecords as $record) {
            if ($record->id == intval($moduleid)) {
                $moduleidfound = true;
            }
        }
        if (!$moduleidfound) {
            array_push($lostmoduleids, $cmid);
        }
    }

    if (empty($lostmoduleids)) {
        return false;
    } else {
        return $lostmoduleids;
    }
}

/**
 * get_html_fix_button_for_lost_modules()
 *
 * Outputs HTML buttons for a module that is missing.
 *
 * @param int $coursemoduleid
 * @param string $modulename
 * @param int $taskid
 * @return string - HTML output
 */
function get_html_fix_button_for_lost_modules(int $coursemoduleid, string $modulename, int $taskid) {

    global $OUTPUT;

    $htmloutput = '';

    $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/delete_module.php');
    $customdata = array('cmid'          => $coursemoduleid,
                        'cmname'        => $modulename,
                        'taskid'        => $taskid);

    $mform = new fix_delete_modules_form($actionurl, $customdata);
    $htmloutput .= html_writer::tag('b',
                                    'Module (cmid: '.$coursemoduleid.')'
                                    .' not found in '.$modulename.' table',
                                    array('class' => "text-danger"));
    $htmloutput .= html_writer::tag('p', get_string('table_modules_empty_explain', 'tool_fix_delete_modules'));
    $button      = $mform->render();
    $htmloutput .= $button;

    return $htmloutput;
}

/**
 * get_html_separate_button_for_clustered_tasks()
 *
 * Outputs HTML buttons for a clustered task.
 *
 * @param int $taskid
 * @return string - HTML output
 */
function get_html_separate_button_for_clustered_tasks(int $taskid) {

    global $OUTPUT;

    $htmloutput = '';

    $actionurl  = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
    $params     = array('taskid' => $taskid);

    $mform = new separate_delete_modules_form($actionurl, $params);
    $htmloutput .= html_writer::tag('b',
                                    'This Adhoc task (id: '.$taskid.') contains multiple course modules.'
                                    .' Press the button below to separate these into multiple adhoc tasks.'
                                    .' This will assist in reducing the complexity of the failed'
                                    .' course_delete_modules task.',
                                    array('class' => "text-danger"));
    $htmloutput .= html_writer::tag('p', get_string('table_modules_empty_explain', 'tool_fix_delete_modules'));
    $button      = $mform->render();
    $htmloutput .= $button;

    return $htmloutput;
}

/**
 * get_coursemoduletask_string()
 *
 * returns human readible string about one course_delete_module task.
 *
 * @param array $cmsdata - one or more coursemodule data in an array.
 * @param int $taskid - the taskid of this task
 * @return string
 */
function get_coursemoduletask_string(array $cmsdata, int $taskid) {

    if ($cmsdata && count($cmsdata) > 2) {
        $stringtaskcms = 'taskid: '.$taskid
                        .' cmids: '.current($cmsdata)->id.'...'.end($cmsdata)->id
                        .' cminstanceids: '.current($cmsdata)->instance.'...'.end($cmsdata)->instance;
    } else if ($cmsdata && count($cmsdata) > 1) {
        $stringtaskcms = 'taskid: '.$taskid
                        .' cmids: '.current($cmsdata)->id.' & '.end($cmsdata)->id
                        .' cminstanceids: '.current($cmsdata)->instance.' & '.end($cmsdata)->instance;
    } else {
        if ($cmsdata && isset(current($cmsdata)->instance)) {
            $stringtaskcms = 'taskid: '.$taskid
                            .' cm id: '.current($cmsdata)->id.' cminstanceid: '.current($cmsdata)->instance;
        } else {
            $stringtaskcms = 'taskid: '.$taskid
                            .' cm id: '.current($cmsdata)->id;
        }
    }

    return $stringtaskcms;

}
