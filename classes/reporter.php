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
 * controller class which liases between the user facing (GUI/CLI files) and the model classes (diagnoser/surgeon).
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fix_delete_modules;

defined('MOODLE_INTERNAL') || die();
require_once("delete_task_list.php");
require_once("delete_task.php");
require_once("delete_module.php");
require_once("diagnoser.php");
require_once("outcome.php");
require_once("surgeon.php");
require_once(__DIR__."/../form.php");

use html_table, html_writer, moodle_url, separate_delete_modules_form, fix_delete_modules_form;
/**
 * controller class which liases between the user facing (GUI/CLI files) and the model classes (diagnoser/surgeon).
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @author      Brad Pasley <brad.pasley@catalyst-au.net>
 * @copyright   2022 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reporter {
    /** @var bool $ishtmloutput true for html output, false for cli output. */
    private $ishtmloutput;
    /** @var int $minimumfaildelay to filter course_delete_module tasks with a faildelay below this value. */
    public $minimumfaildelay;
    /** @var int[] $querytaskids list of specific tasks to report/diagnose/fix (optional). */
    private $querytaskids;

    /**
     * Constructor sets attributes for outputting reports, diagnoses and fix results.
     *
     * @param bool $ishtmloutput true for html output, false for cli output.
     * @param int $minimumfaildelay The minimum value (seconds) for the faildelay field of the adhoc task.
     * @param int[] $querytaskids list of specific tasks to report/diagnose/fix (optional).
     */
    public function __construct(bool $ishtmloutput = true, int $minimumfaildelay = 60, array $querytaskids = array()) {
        $this->ishtmloutput = $ishtmloutput;
        $this->minimumfaildelay = $minimumfaildelay;
        $this->querytaskids = $querytaskids;
    }

    /**
     * Get a summary of the related tables for course_delete_module tasks in rendered text.
     *
     * @return string
     */
    public function get_tables_report() {
        global $OUTPUT;
        $output = '';

        $titleword = get_string('report_heading', 'tool_fix_delete_modules');
        $deletetaskslist = new delete_task_list($this->minimumfaildelay);
        $deletetasks = $deletetaskslist->get_deletetasks();
        foreach ($deletetasks as $taskid => $deletetask) {
            // Skip if not in filtered list of taskids.
            if (!empty($this->querytaskids) && !in_array($deletetask->taskid, $this->querytaskids)) {
                continue;
            }
            $taskreporttitle = $this->get_word_task_module_string($titleword, $deletetask);
            $reporttables = '';
            $reporttables .= $this->get_adhoctasktable($deletetask);
            $reporttables .= $this->get_coursemodulestable($deletetask);
            $reporttables .= $this->get_contexttable($deletetask);
            $reporttables .= $this->get_moduletable($deletetask);
            $reporttables .= $this->get_filetable($deletetask);
            $reporttables .= $this->get_gradestable($deletetask);
            $reporttables .= $this->get_recyclebintable($deletetask);

            $reportdata = ['title' => $taskreporttitle, 'reporttables' => $reporttables];
            $output .= $this->format_message($reportdata, 'task_report');
        }
        return $output;
    }

    /**
     * Get the diagnosis of all course_delete_module tasks in either HTML or plain text format.
     *
     * @return string
     */
    public function get_diagnosis() {
        global $OUTPUT;

        $output = '';
        $diagnoses = $this->get_diagnosis_data();

        // Each diagnosis is for a separate adhoc task.
        foreach ($diagnoses as $diagnosis) {
            $task = $diagnosis->get_task();
            // Skip if not in filtered list of taskids.
            if (!empty($this->querytaskids) && !in_array($task->taskid, $this->querytaskids)) {
                continue;
            }
            $titleword = get_string('diagnosis', 'tool_fix_delete_modules');
            $diagnosistitle = $this->get_word_task_module_string($titleword, $task);
            $symptoms = $diagnosis->get_symptoms();
            $diagnosisbutton = $this->get_fix_button($diagnosis);
            $diagnosisdata = ['title' => $diagnosistitle,
                              'symptoms' => $symptoms,
                              'button' => $diagnosisbutton];
            $output .= $this->format_message($diagnosisdata, 'task_diagnosis');
        }
        return $output;
    }

    /**
     * Helper function for get_diagnosis(); gets the diagnosis object returned from diagnoser.
     *
     * @param int[] $taskids - optional array of ints (task id(s) to be fixed); empty array means no filter.
     *
     * @return array of diagnosis
     */
    private function get_diagnosis_data(array $taskids = null) {
        $diagnoses = array();
        $deletetaskslist = new delete_task_list($this->minimumfaildelay);
        $deletetasks = $deletetaskslist->get_deletetasks();
        foreach ($deletetasks as $taskid => $deletetask) {
            if (empty($taskids) || in_array($taskid, $taskids)) { // If no filter or is in the filter.
                $diagnoser = new diagnoser($deletetask);
                $diagnoses[] = $diagnoser->get_diagnosis();
            }
        }
        return $diagnoses;
    }

    /**
     * Make fix(s) on course_delete_module task(s) and return rendered outcomes
     *
     * @param int[] $taskids - optional array of ints (task id(s) to be fixed); empty array means no filter.
     *
     * @return string
     */
    public function fix_tasks(array $taskids = array()) {
        global $OUTPUT;
        $output = '';
        $outcomes = $this->get_fix_results($taskids);
        // Each diagnosis is for a separate adhoc task.
        foreach ($outcomes as $outcome) {
            $task = $outcome->get_task();
            // Skip if not in filtered list of taskids.
            if (!empty($this->querytaskids) && !in_array($task->taskid, $this->querytaskids)) {
                continue;
            }
            $titleword = get_string('results', 'tool_fix_delete_modules');
            $outcometitle = $this->get_word_task_module_string($titleword, $task);
            $outcomedata = ['title' => $outcometitle, 'outcomemessages' => $outcome->get_messages()];
            $output .= $this->format_message($outcomedata, 'task_fix_results');
        }
        return $output;
    }

    /**
     * Helper function for fix_tasks(). Returns an array of outcome objects from the surgeon object.
     *
     * @param int[] $taskids - optional array of ints (task id(s) to be fixed); empty array means no filter.
     *
     * @return array
     */
    private function get_fix_results(array $taskids = array()) {
        $outcomes = array();
        $diagnoses = $this->get_diagnosis_data($taskids);
        foreach ($diagnoses as $diagnosis) {
            $surgeon = new surgeon($diagnosis);
            $outcomes[] = $surgeon->get_outcome();
        }
        return $outcomes;
    }

    /**
     * Helper function for get_tables_report; returns summary of task_adhoc table.
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_adhoctasktable(delete_task $deletetask) {
        global $DB, $OUTPUT;
        $output = '';
        if ($records = $DB->get_records('task_adhoc',
                                        array('id' => $deletetask->taskid), '',
                                        'id, nextruntime, faildelay, customdata')) {
            foreach ($records as $key => $record) {
                // Exclude adhoc tasks with faildelay below minimum config setting.
                if (intval($record->faildelay) < $this->minimumfaildelay) {
                    unset($records[$key]);
                    continue;
                }
            }

            $tabletitle = get_string('table_title_adhoctask', 'tool_fix_delete_modules');
            $data = ['title' => $tabletitle, 'records' => $records];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of course_modules table.
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_coursemodulestable(delete_task $deletetask) {
        global $DB, $OUTPUT;

        $output = '';

        // Prepare SQL query.
        $cmids = $deletetask->get_coursemoduleids();
        list($sqltail, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'id');
        $where = 'WHERE id '. $sqltail;
        $sqlhead = 'SELECT id, course, module, instance, section, idnumber, deletioninprogress FROM {course_modules} ';

        if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {
            $tableword = get_string('table_title_coursemodules', 'tool_fix_delete_modules');
            $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);
            $data = ['title' => $tabletitle, 'records' => $records];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of related module table.
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_moduletable(delete_task $deletetask) {
        global $DB, $OUTPUT;

        $output = '';

        // Prepare SQL query.
        $cmids = $deletetask->get_coursemoduleids();
        $modulenames = $deletetask->get_modulenames();
        // Display table for each module table.
        foreach ($modulenames as $modulename) {
            $thisnamecmids = $deletetask->get_modulenames(false, true, $modulename);
            list($sqltail, $params) = $DB->get_in_or_equal(array_keys($thisnamecmids), SQL_PARAMS_NAMED, 'instanceid');
            $where = 'WHERE id '. $sqltail;
            $sqlhead = 'SELECT * FROM {'.$modulename.'} ';

            if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {
                $tableword = get_string('table_title_module', 'tool_fix_delete_modules').': '.$modulename;
                $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);
                $data = ['title' => $tabletitle, 'records' => $records];
                $output .= $this->format_message($data, 'report_table');
            }
        }

        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of context table.
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_contexttable(delete_task $deletetask) {
        global $DB, $OUTPUT;

        $output = '';

        // Prepare SQL query.
        $cmids = $deletetask->get_coursemoduleids();
        list($sqltail, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'instanceid');
        $where = 'WHERE contextlevel = 70 AND instanceid '. $sqltail;
        $sqlhead = 'SELECT * FROM {context} ';

        if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {
            $tableword = get_string('table_title_context', 'tool_fix_delete_modules');
            $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);
            $data = ['title' => $tabletitle, 'records' => $records];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of file table (only for singular module tasks).
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_filetable(delete_task $deletetask) {

        // Skip for multimodule adhoc tasks (too much data to be meaningful!).
        if ($deletetask->is_multi_module_task()) {
            return '';
        }

        global $DB, $OUTPUT;

        $output = '';

        // Prepare SQL query.
        $contextids = $deletetask->get_contextids();
        list($sqltail, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');
        $where = 'WHERE contextid '. $sqltail;
        $sqlhead = 'SELECT * FROM {files} ';

        if ($records = $DB->get_records_sql($sqlhead.$where, $params)) {

            // Build stats on file records.
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

            $tableword = get_string('table_title_files', 'tool_fix_delete_modules');
            $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);
            $data = ['title' => $tabletitle,
                     'records' => $records,
                     'headings' => array("name", "count")];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of grades tables (only for singular module tasks).
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_gradestable(delete_task $deletetask) {

        // Skip for multimodule adhoc tasks (too much data to be meaningful!).
        if ($deletetask->is_multi_module_task()) {
            return '';
        }

        global $DB, $OUTPUT;

        $output = '';
        $deletemodule = current($deletetask->get_deletemodules()); // Only one module in the task.

        if ($records = $DB->get_records('grade_items',
                                        array('itemmodule' => $deletemodule->get_modulename(),
                                              'iteminstance' => $deletemodule->moduleinstanceid,
                                              'courseid' => $deletemodule->courseid))) {

            // Get count of grades for this grade item & add to record.
            foreach ($records as $rkey => $record) {
                $gradescount = $DB->count_records('grade_grades', array('itemid' => $rkey));
                $recordarray = (array) $record;
                $recordarray = array('grades_count' => "$gradescount") + $recordarray;
                $records[$rkey] = (object) $recordarray;
            }

            $tableword = get_string('table_title_grades', 'tool_fix_delete_modules');
            $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);;
            $data = ['title' => $tabletitle, 'records' => $records];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Helper function for get_tables_report; returns summary of tool_recyclebin_course table.
     *
     * @param delete_task $deletetask
     * @return string
     */
    private function get_recyclebintable(delete_task $deletetask) {

        // Skip for multimodule adhoc tasks (courseid might not be available).
        if ($deletetask->is_multi_module_task()) {
            return '';
        }

        global $DB, $OUTPUT;

        $output = '';
        $deletemodule = current($deletetask->get_deletemodules()); // Only one module in the task.

        if ($records = $DB->get_records('tool_recyclebin_course',
                                        array('courseid' => $deletemodule->courseid))) {
            $tableword = get_string('table_title_recyclebin', 'tool_fix_delete_modules');
            $tabletitle = $this->get_word_task_module_string($tableword, $deletetask);
            $data = ['title' => $tabletitle, 'records' => $records];
            $output .= $this->format_message($data, 'report_table');
        }
        return $output;
    }

    /**
     * Formulate a string which includes a word, taskid and module ids.
     *
     * @param string $titlehead
     * @param delete_task $task
     * @param bool $displaymoduleinfo - include coursemoduleids & moduleinstanceids.
     * @param bool $displaycourseid - defaulted to false.
     * @return string
     */
    private function get_word_task_module_string(string $titlehead,
                                                 delete_task $task,
                                                 bool $displaymoduleinfo = true,
                                                 bool $displaycourseid = true) {
        $taskid = $task->taskid;
        $outputstring = isset($taskid) ? $titlehead." taskid($taskid) " : $titlehead.' ';

        $coursestring = '';
        if ($displaycourseid) {
            // Prepare course info (if available).
            $courseids = $task->get_courseids(true, true);
            if (!empty($courseids)) { // Assume there is only one courseid.
                $coursestring = 'courseid:'.current($courseids).' ';
            }
        }

        if (!$displaymoduleinfo) {
            return $outputstring;
        }

        // Prepare module info.
        $modulesstring = '';
        $cmids = $task->get_coursemoduleids();
        $moduleinstanceids = $task->get_moduleinstanceids();

        // Pair up coursemodule ids and instance ids if possible.
        if (!empty($cmids) && !empty($moduleinstanceids) && count($cmids) == count($moduleinstanceids)) {
            $combined = array();
            $cmids = array_values($cmids);
            $moduleinstanceids = array_values($moduleinstanceids);
            if (count($cmids) > 4) { // Make an elipsis string.
                $modulesstring = '(cmid:'.current($cmids).'/instanceid:'.current($moduleinstanceids).')...';
                $modulesstring .= '(cmid:'.end($cmids).'/instanceid:'.end($moduleinstanceids).')';
            } else { // Otherwise, explicitly list each.
                for ($i = 0; $i < count($cmids); $i++) {
                    $combined[] = '(cmid:'.$cmids[$i].'/instanceid:'.$moduleinstanceids[$i].')';
                }
                $modulesstring = implode(', ', $combined);
            }
        } else { // There aren't both arrays or they don't have matching number of elements.
            if (!empty($cmids)) { // Add coursemodule ids if present.
                if (count($cmids) > 3) { // Make an elipsis string.
                    $modulesstring = '(cmids:'.current($cmids).'...';
                    $modulesstring .= end($cmids).')';
                } else {
                    $modulesstring .= "(cmids:".implode(', ', $cmids).')';
                }
            }
            if (!empty($cmids)) { // Add instanceids if present.
                if (count($cmids) > 3) { // Make an elipsis string.
                    $modulesstring = '(instanceids:'.current($moduleinstanceids).'...';
                    $modulesstring .= end($moduleinstanceids).')';
                } else {
                    $modulesstring .= "(instanceids:".implode(', ', $moduleinstanceids).')';
                }
            }
        }
        $outputstring .= $coursestring."modules: ".$modulesstring;
        return $outputstring;
    }

    /**
     * Returns a text table with a heading row, followed by data rows.
     *
     * @param array $arraytable
     * @param string[] $headings - array of table headings (optional) - empty array is ignored.
     * @return string
     */
    private function get_texttable(array $arraytable, array $headings = array()) {
        $outputtable = '';
        empty($headings) ? $titlerow = '' : $titlerow = implode('\t', $headings).PHP_EOL;
        foreach ($arraytable as $record) {
            $row = '';
            if (!is_object($record)) {
                if (!is_array($record)) {
                    $record = array($record);
                }
                foreach ($record as $value) {
                    $row .= $value.PHP_EOL; // Each element is a row.
                }
                $outputtable .= $row;
            } else { // Multi cell row; Each element in $record is a cell of a row.
                foreach ($record as $cell) {
                    $row .= $cell.'\t';
                }
                $outputtable .= $row.PHP_EOL;
            }
        }
        ($outputtable !== '') ? $outputtable = $titlerow.$outputtable.PHP_EOL : $outputtable = '';
        return $outputtable;
    }

    /**
     * Returns a table in cli text format, with vertical heading column/data columns.
     *
     * @param array $records
     * @return string
     */
    private function get_texttable_vertical(array $records) {
        $outputtable = '';
        $titlerow = implode('\t', array_keys((array) current($records))).PHP_EOL;
        foreach ($records as $row) {
            foreach ($row as $cell) {
                $outputtable .= $cell.'\t';
            }
            $outputtable .= PHP_EOL;
        }
        ($outputtable !== '') ? $outputtable = $titlerow.$outputtable : $outputtable = '';
        $outputtable .= PHP_EOL;
        return $outputtable;
    }

    /**
     * Returns html table from an array of records (first row is heading).
     *
     * @param array $records - records of table
     * @param string[] $headings - array of table headings (optional) - empty array is ignored.
     * @return string - an html_table as a string
     */
    private function get_htmltable(array $records, array $headings = array()) {
        global $OUTPUT;
        $table = new html_table();
        if (empty($headings)) {
            $table->head = array_keys((array) current($records));
        } else {
            $table->head = $headings;
        }
        foreach ($records as $record) {
            $row = array();
            if (!is_object($record)) {
                if (!is_array($record)) {
                    $record = array($record);
                }
                foreach ($record as $key => $value) {
                    $table->data[] = array($value); // Each element is a row.
                }
                $table->data[] = $row;
            } else {
                foreach ($record as $key => $cell) {
                    $row[] = $cell;
                }
                $table->data[] = $row;
            }

        }
        return html_writer::table($table);
    }

    /**
     * Returns a 2 column html table; first column heading, second column value.
     *
     * @param  array $records - records of table
     * @param  array $columntitles - column titles
     * @return string - html_table as string.
     */
    private function get_htmltable_vertical(array $records, array $columntitles) {
        global $OUTPUT;
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

        return html_writer::table($table);
    }

    /**
     * Returns an HTML rendered form/button if 'symptoms' can be acted on.
     *
     * @param diagnosis $diagnosis - The diagnosis object which contains details of what should be displayed on the fix button.
     * @return string
     */
    private function get_fix_button(diagnosis $diagnosis) {
        global $OUTPUT;

        $htmloutput = '';
        $task = $diagnosis->get_task();
        $deletemodules = $task->get_deletemodules();
        $taskid = $task->taskid;
        $symptoms = $diagnosis->get_symptoms();

        // Adhoc task missing - show explanation that the task might have now been cleared.
        if ($diagnosis->adhoctask_is_missing()) {
            $description = get_string('diagnosis_explain_adhoctask_missing', 'tool_fix_delete_modules', $taskid);
            $buttondata  = ['description' => $description, 'fixbutton' => '']; // No button required (just browser refresh).
            $htmloutput .= $OUTPUT->render_from_template('tool_fix_delete_modules/diagnosis_button', $buttondata);
        } else if ($diagnosis->is_multi_module_task() || count($deletemodules) > 1) {
            // Multimodule task - show separate task/modules button.
            $actionurl   = new moodle_url('/admin/tool/fix_delete_modules/separate_module.php');
            $params      = array('taskid' => $taskid, 'action' => 'separate_module');
            $buttonmform = new separate_delete_modules_form($actionurl, $params);

            $description = get_string('diagnosis_recommend_separate_tasks', 'tool_fix_delete_modules', $taskid);
            $buttondata  = ['description' => $description, 'fixbutton' => $buttonmform->render()];
            $htmloutput .= $OUTPUT->render_from_template('tool_fix_delete_modules/diagnosis_button', $buttondata);
        } else if ($diagnosis->module_has_missing_data() && !$task->is_multi_module_task()) {
            // Individual module has incomplete data - needs force delete.

            // Double check cmid in symptoms array vs task object.
            $cmids              = array_keys($symptoms);
            $symptomcmid        = 0;
            foreach ($cmids as $key) {
                if (is_int($key)) {
                    $symptomcmid = $key;
                    break; // Just grab first int key. Should only be one value (a cmid) in the array!
                }
            }
            $tcmid  = current($task->get_coursemoduleids());

            if ($symptomcmid == $tcmid) {
                $modulename = current($task->get_modulenames());

                $actionurl   = new moodle_url('/admin/tool/fix_delete_modules/fix_module.php');
                $params      = array('action'     => 'fix_module',
                                     'cmid'       => $symptomcmid,
                                     'cmname' => $modulename,
                                     'taskid'     => $taskid);
                $buttonmform = new fix_delete_modules_form($actionurl, $params);

                $description = get_string('diagnosis_recommend_clear_remnant_data', 'tool_fix_delete_modules', $task->taskid);
                $buttondata  = ['description' => $description, 'fixbutton' => $buttonmform->render()];
                $htmloutput .= $OUTPUT->render_from_template('tool_fix_delete_modules/diagnosis_button', $buttondata);

            }
        }
        return $htmloutput;

    }

    /**
     * Returns a string, either HTML or plain text depending on the $this->ishtmloutput attribute.
     *
     * @param array $data - an array, including 'title', table data, etc.
     * @param string $templatename - the name for the HTML template used (also used to distinguish types of messages).
     * @return string
     */
    private function format_message(array $data, string $templatename) {
        global $OUTPUT;

        switch($templatename) {
            case 'task_report':
                $data['body'] = $data['reporttables'];
                break;
            case 'task_diagnosis':
                $heading = array();
                if (empty($data['symptoms'])) {
                    $data['symptoms'] = array(get_string('symptom_good_no_issues', 'tool_fix_delete_modules'));
                } else {
                    $heading = [get_string('symptoms', 'tool_fix_delete_modules')];
                }
                $data['body'] = $this->get_texttable($data['symptoms'], $heading);
                break;
            case 'task_fix_results':
                $data['table'] = $this->get_htmltable($data['outcomemessages'],
                                                     array(get_string('result_messages', 'tool_fix_delete_modules')));
                $data['body'] = $this->get_texttable($data['outcomemessages'],
                                                     array(get_string('result_messages', 'tool_fix_delete_modules')));
                break;
            case 'report_table':
                if (!isset($data['headings']) || empty($data['headings'])) {
                    $data['table'] = $this->get_htmltable($data['records']);
                    $data['body'] = $this->get_texttable($data['records']);
                } else {
                    $data['table'] = $this->get_htmltable_vertical($data['records'], $data['headings']);
                    $data['body'] = $this->get_texttable_vertical($data['records'], $data['headings']);
                }
                break;
            default:
                break;
        }
        return $this->ishtmloutput ? $OUTPUT->render_from_template("tool_fix_delete_modules/$templatename", $data)
                                    : $data['title'].PHP_EOL.$data['body'].PHP_EOL;
    }
}
