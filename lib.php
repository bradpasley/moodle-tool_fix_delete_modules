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
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/blog/lib.php');

/**
 * get_adhoctasks_table()
 *
 * Get info for displaying info about the adhoc task.
 *
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 * @param stdClass $customdata - if not null, only get specific adhoctask.
 * @param int $climinfaildelay - optional (for CLI only)
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_adhoctasks_table(bool $htmloutput = false, stdClass $customdatacms = null, int $climinfaildelay = 60) {
    global $DB;
    if ($adhocrecords = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'))) {

        if (!is_null($customdatacms)) { // Filtered down to one adhoc task. TO BE DONE: ELSE.
            // Get customdata.
            $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));

            // Override for CLI.
            if ($climinfaildelay != 60) {
                $minimumfaildelay = $climinfaildelay;
            }

            foreach ($adhocrecords as $adkey => $adhocrecord) {

                // Exclude adhoc tasks with faildelay below minimum config setting.
                if (intval($adhocrecord->faildelay) < $minimumfaildelay) {
                    unset($adhocrecords[$adkey]);
                    continue;
                }
                // Filter down to only param, if not null and matches.
                $recordcustomdata = json_decode($adhocrecord->customdata)->cms;
                $recordcmid = 0;
                if (is_array($recordcustomdata) && count($recordcustomdata) == 1) {
                    $recordcustomdata = current($recordcustomdata);
                    $recordcmid = $recordcustomdata->id;
                }

                if (isset($customdatacms) && $recordcmid == $customdatacms->id) {
                    $adhocrecords[] = $adhocrecord;
                }
            }
            if (count($adhocrecords) == 0) { // When there are no tasks with min faildelay.
                return false;
            }
        }
        if ($htmloutput) { // HTML GUI output.
            return get_htmltable($adhocrecords);
        } else {
            $adhoctable   = array();
            if (count($adhocrecords) > 0 ) {
                $adhoctable[] = array_keys((array) current($adhocrecords));
                foreach ($adhocrecords as $record) {
                    $row = (array) $record;
                    $adhoctable[] = $row;
                }
                return $adhoctable;
            } else {
                return false;
            }
        }
    }
    return false;
}

/**
 * get_course_module_table()
 *
 * @param array $cms - data of course modules
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_course_module_table(array $cms, bool $htmloutput) {

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

    list($sqltail, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sqltail;
    $sqlhead = 'SELECT id, course, module, instance, section, idnumber, deletioninprogress FROM {course_modules} ';

    // Retrieve Query.
    if ($cmrecords = $DB->get_records_sql($sqlhead.$where, $params)) {

        if ($htmloutput) { // HTML GUI output.
            if (!$cmrecords) {
                return false;
            } else {
                return get_htmltable($cmrecords);
            }
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
    }
}

/**
 * get_module_tables()
 *
 * Get the table related to the course module which is failing to be deleted.
 *
 * @param array $cms - course modules data
 * @param int $taskid - taskid assocaited with this module deletion.
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_module_tables(array $cms, int $taskid, bool $htmloutput) {

    global $DB, $OUTPUT;

    if (is_null($cms)) {
        return false;
    }

    $modulenames = array();
    foreach ($cms as $cm) {
        if (!isset($cm->modulename)) {
            continue;
        }
        // Push each module as [instance -> id] into array associated with its modulename.
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
            continue;
        }

        list($sqltail, $params) = $DB->get_in_or_equal(array_keys($modulenameids), SQL_PARAMS_NAMED, 'instanceid');
        $where = 'WHERE id '. $sqltail;
        $sqlhead = 'SELECT * FROM {'.$modulename.'} ';

        // Retrieve Query.
        if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {

            if ($htmloutput) { // HTML GUI output.
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
        }
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
 * @param int|bool contextid of module
 *
 * @return int
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
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
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
 * @param int $climinfaildelay - optional, GUI will get from config
 * @return array|null - array of course module info from customdata field.
 */

function get_all_cmdelete_adhoctasks_data(int $climinfaildelay = 60) {
    global $DB;

    $adhoccustomdatas = $DB->get_records('task_adhoc',
                                         array('classname' => '\core_course\task\course_delete_modules'),
                                               '',
                                               'id, customdata, faildelay');
    $customdatas = array();
    $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
    if ($climinfaildelay != 60) { // Override config setting - for CLI.
        $minimumfaildelay = $climinfaildelay;
    }
    foreach ($adhoccustomdatas as $taskrecord) {
        // Exclude adhoc tasks with faildelay below minimum config setting.
        if (intval($taskrecord->faildelay) < $minimumfaildelay) {
            continue;
        }
        $value = $taskrecord->customdata;
        $cms   = json_decode($value)->cms;
        if (is_array($cms) && count($cms) == 1) {
            $cms = current($cms);
            $cms = array(''.$cms->id => $cms);
        } else {
            $cms = (array) $cms;
        }
        $customdatas[''.$taskrecord->id] = $cms;
    }

    return $customdatas;
}

/**
 * get_original_cmdelete_adhoctask_data()
 *
 * @param int $taskid
 * @param int $climinfaildelay - optional, GUI will get from config
 * @return array|null - course module info from customdata field.
 */

function get_original_cmdelete_adhoctask_data(int $taskid, int $climinfaildelay = 0) {
    global $DB;

    $adhoccustomdata = $DB->get_record('task_adhoc',
                                        array('id' => $taskid,
                                              'classname' => '\core_course\task\course_delete_modules'),
                                              'id, customdata, faildelay',
                                            IGNORE_MISSING);
    $minimumfaildelay = intval(get_config('tool_fix_delete_modules', 'minimumfaildelay'));
    if ($climinfaildelay != 0) { // Override config setting - for CLI.
        $minimumfaildelay = $climinfaildelay;
    }

    if ($adhoccustomdata && !is_null($adhoccustomdata)) {
        // Skip filtered task.
        if (intval($adhoccustomdata->faildelay) < $minimumfaildelay) {
            return null;
        }
        $value = $adhoccustomdata->customdata;
        $cms   = json_decode($value)->cms;
        if (is_array($cms) && count($cms) == 1) {
            $cms = current($cms);
        }

        return $cms;
    } else {
        return null;
    }
}

/**
 * get_all_affects_courseids()
 *
 * used only in CLI
 *
 * @param array $taskcmsarray
 * @return array|null - array of course module info from customdata field.
 */

function get_all_affects_courseids(array $taskcmsarray) {
    global $DB;

    if (is_null($taskcmsarray) || empty($taskcmsarray)) {
        return null;
    }

    $courseids = array();
    foreach ($taskcmsarray as $cmstask) {
        foreach ($cmstask as $cm) {
            if (!in_array($cm->course, $courseids)) {
                $courseids[] = $cm->course;
            }
        }
    }

    if (empty($courseids)) {
        return false;
    }

    $param = '';
    list($sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;

    return $DB->get_fieldset_sql('SELECT id FROM {course} '. $where, $params);
}

/**
 * get_cms_of_course()
 *
 * @param int $courseid
 * @return stdClass|bool - cms data of a course, if delete task exists for module in course.
 */

function get_cms_of_course(int $courseid) {
    global $DB;

    // Find adhoc task with courseid.
    $adhocdeletetasksdata = get_all_cmdelete_adhoctasks_data();
    $cms = null;
    foreach ($adhocdeletetasksdata as $adhoctaskcms) {
        foreach ($adhoctaskcms as $cm) {
            if ($cm->course == $courseid) {
                return $cm;
            }
        }
    }

    return $cms;
}

/**
 * get_cm_info()
 *
 * get an array of cms data (split up clustered task customdata)
 *
 * @param array $cms - coursemodule data from adhoctask customdata field.
 * @return array|bool - array of cms' data or false if not available.
 */

function get_cms_infos(array $cms) {
    global $DB;

    // Get coursemoduleids; to be used to get the database data.
    $cmids = array();
    foreach ($cms as $cm) {
        $cmids[] = $cm->id;
    }

    // Get the course module data.
    list($sql, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'id');
    $where = 'WHERE id '. $sql;
    if (!$cmsrecord = $DB->get_records_sql('SELECT * FROM {course_modules} '. $where, $params)) {
        return $cms; // Return original.
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
 * @param  int $courseid - the course of which to check
 * @return array - strings explaining what type of issue exists
 */

function course_module_delete_issues(int $courseid = null, int $climinfaildelay = 60) {

    // Find adhoc task with courseid.
    $cmstasksdata = get_all_cmdelete_adhoctasks_data($climinfaildelay);
    $results = array();

    foreach ($cmstasksdata as $taskid => $adhoctaskcms) {
        foreach ($adhoctaskcms as $cm) {
            // If this $adhoctaskcms is associated with this courseid.
            if (!is_null($courseid) && $cm->course == $courseid) {

                $cms = get_cms_infos($adhoctaskcms);
                if (!$table = get_adhoctasks_table(false, $cm, $climinfaildelay)) {
                    $results[] = "adhoc task record table record doesn't exist".PHP_EOL;
                }
                if (!$table = get_course_module_table($cms, false)) {
                    $results[] = "course module table record for "
                               ."(cm id: ".$cm->id.", cm instance: "
                               .$cm->instance." table record doesn't exist)".PHP_EOL;
                }
                if (!$table = get_module_tables($cms, $taskid, false)) {
                    $modulename = get_module_name($cm);
                    $results[] = "taskid:$taskid: module table ($modulename) record for "
                               ."(cm id: ".$cm->id.", cm instance: "
                               .$cm->instance." doesn't exist)".PHP_EOL;
                }

            }

        }
    }
    return $results;
}

/**
 * force_delete_modules_of_course()
 *
 * @param int $courseid - the courseid in which module id to be resolved.
 * @return bool - result of deletion
 */
function force_delete_modules_of_course(int $courseid) {
    $cms = get_cms_of_course($courseid);
    return force_delete_module_data($cms->id, $cms);
}


/**
 * force_delete_module_data()
 *
 * @param int $coursemoduleid - the course module id to be resolved.
 * @param stdClass $cms - cm data
 * @return bool - result of deletion
 */

function force_delete_module_data(int $coursemoduleid, stdClass $cms) {
    global $DB;

    if (is_null($cms)) {
        return ["Fixing... ERROR: Cannot retrieve info about the course module (Course module id: $coursemoduleid)."];
    }

    echo "Fixing... Attempting to fix a deleted module (Course module id: $coursemoduleid).";
    // Get the course module.
    if (!$cm = $DB->get_record('course_modules', array('id' => $coursemoduleid))) {
        echo "Course Module instance (cmid $coursemoduleid) doesn't exist. Perhaps you already deleted it".PHP_EOL;
        echo "Run the adhoc task to clear it off the list.".PHP_EOL.PHP_EOL;
        echo "\$sudo -u www-data /usr/bin/php admin/tool/task/cli/adhoc_task.php --execute".PHP_EOL;
        return false;
    }
    // Get the module context.
    $modcontext = context_module::instance($coursemoduleid);

    // Get the module name.
    $modulename = get_module_name($cms);

    // Remove all module files in case modules forget to do that.
    $fs = get_file_storage();
    $fs->delete_area_files($modcontext->id);

    echo 'Files deleted for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;

    // Delete events from calendar.
    if ($events = $DB->get_records('event', array('instance' => $cm->instance, 'modulename' => $modulename))) {
        $coursecontext = context_course::instance($cm->course);
        foreach ($events as $event) {
            $event->context = $coursecontext;
            $calendarevent = calendar_event::load($event);
            $calendarevent->delete();
        }
        if (count($event) > 0) {
            echo count($events).' Calendar events for module cmid $cmid contextid '.$modcontext->id.'.';
        }
    }

    // Delete grade items, outcome items and grades attached to modules.
    if ($gradeitems = grade_item::fetch_all(array('itemtype' => 'mod', 'itemmodule' => $modulename,
                                                   'iteminstance' => $cm->instance, 'courseid' => $cm->course))) {
        foreach ($gradeitems as $gradeitem) {
            $gradeitem->delete('moddelete');
        }
        if (count($gradeitems) > 0) {
            echo count($gradeitems).' Grade items for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;
        }
    }

    // Delete associated blogs and blog tag instances.
    blog_remove_associations_for_module($modcontext->id);
    echo 'Deleted blogs for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;

    // Delete completion and availability data; it is better to do this even if the
    // features are not turned on, in case they were turned on previously (these will be
    // very quick on an empty table).
    $DB->delete_records('course_modules_completion', array('coursemoduleid' => $cm->id));
    echo 'Deleted Module Completion data for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;
    $DB->delete_records('course_completion_criteria', array('moduleinstance' => $cm->id,
                                                            'course' => $cm->course,
                                                            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY));
    echo 'Deleted Module Completion Criteria data for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;

    // Delete all tag instances associated with the instance of this module.
    \core_tag_tag::delete_instances('mod_' . $modulename, null, $modcontext->id);
    \core_tag_tag::remove_all_item_tags('core', 'course_modules', $cm->id);
    echo 'Deleted Tag data for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;

    // Notify the competency subsystem.
    \core_competency\api::hook_course_module_deleted($cm);

    // Delete the context.
    \context_helper::delete_instance(CONTEXT_MODULE, $cm->id);
    echo 'Context data for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;

    // Delete the module from the course_modules table.
    if ($DB->delete_records('course_modules', array('id' => $cm->id))) {
        echo 'Deleted Course Module record for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;
    } else {
        echo 'Deleted Course Module record: No record to delete for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;
    }

    // Delete module from that section.
    if (!delete_mod_from_section($cm->id, $cm->section)) {
        throw new moodle_exception('cannotdeletemodulefromsection', '', '', null,
            "Cannot delete the module $modulename (instance) from section.");
    }
    echo 'Deleted Module From Section for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;

    // Trigger event for course module delete action.
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
    rebuild_course_cache($cm->course, true);

    echo 'SUCCESSFUL Deletion of Module and related data (cmid $cmid contextid '.$modcontext->id.').'.PHP_EOL.PHP_EOL;

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
