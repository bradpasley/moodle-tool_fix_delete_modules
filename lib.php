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
 * @param stdClass $cms - course module data
 * @param bool $htmloutput - htmloutput true for gui output, false for cli output
 *
 * @return html_table|array|bool - records of course_delete_module adhoc tasks.
 */
function get_files_table(stdClass $cms, bool $htmloutput) {

    global $DB;

    $modcontextid = get_context_id($cms);

    if (!is_null($cms) && !is_null($modcontextid && $modcontextid)
        && $records = $DB->get_records('files', array('contextid' => $modcontextid))) {

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
 * get_all_cms_from_adhoctask()
 *
 * @return array|null - array of course module info from customdata field.
 */

function get_all_cms_from_adhoctask() {
    global $DB;

    $adhoccustomdatas = $DB->get_records('task_adhoc', array('classname' => '\core_course\task\course_delete_modules'), '', 'customdata');
    $customdatas = array();
    foreach ($adhoccustomdatas as $taskrecord) {
        $value = $taskrecord->customdata;
        $customdatas[] = json_decode($value)->cms;
    }

    return $customdatas;
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
 * get_all_affects_courseids()
 *
 * @param array $cmsarray
 * @return array|null - array of course module info from customdata field.
 */

function get_all_affects_courseids(array $cmsarray) {
    global $DB;

    if (is_null($cmsarray) || empty($cmsarray)) {
        return null;
    }

    $courseids = array();
    foreach ($cmsarray as $cms) {
        $courseids[] = (current($cms))->course;
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

    // Find adhoc task with courseid
    $adhocdeletetasks = get_all_cms_from_adhoctask();
    $cms = null;
    foreach ($adhocdeletetasks as $adhoctaskcms) {
        $adhoctaskcms = current($adhoctaskcms);
        if ($adhoctaskcms->course == $courseid) {
            $cms = $adhoctaskcms;
        }
    }

    return $cms;
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
 * course_module_delete_issues()
 *
 * @param  int $courseid - the course of which to check
 * @return array - strings explaining what type of issue exists
 */

function course_module_delete_issues(int $courseid = null) {

    // Find adhoc task with courseid.
    $adhocdeletetasks = get_all_cms_from_adhoctask();
    $cms = null;
    foreach ($adhocdeletetasks as $adhoctaskcms) {
        $adhoctaskcms = current($adhoctaskcms);
        if ($adhoctaskcms->course == $courseid) {
            $cms = $adhoctaskcms;
        }
    }

    if (is_null($cms)) {
        return ["Cannot find a course_module_delete adhoc task for courseid: $courseid"];
    }

    $results = array();
    if (!get_adhoctasks()) {
        $results[] = "adhoc task record table record doesn't exist".PHP_EOL;
    }
    if (!get_course_module_table($cms, false)) {
        $results[] = "course module table record for "
                   ."(cm id: ".$cms->id.", cm instance: "
                   .$cms->instance."table record doesn't exist)".PHP_EOL;
    }
    if (!get_module_table($cms, false)) {
        $modulename = get_module_name($cms);
        $results[] = "module table ($modulename) record for "
                   ."(cm id: ".$cms->id.", cm instance: "
                   .$cms->instance." doesn't exist)".PHP_EOL;
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
        return ["Cannot find a course_module_delete adhoc task for courseid: $courseid"];
    }

    // Get the course module.
    if (!$cm = $DB->get_record('course_modules', array('id' => $coursemoduleid))) {
        echo "Course Module instance (cmid $coursemoduleid) doesn't exist. Perhaps you already deleted it".PHP_EOL;
        echo "Run the adhoc task to clear it off the list.".PHP_EOL.PHP_EOL;
        echo "\$sudo -u www-data /usr/bin/php admin/tool/task/cli/adhoc_task.php --execute".PHP_EOL;
        die();
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
    $DB->delete_records('course_modules', array('id' => $cm->id));
    echo 'Deleted Course Module record for module cmid $cmid contextid '.$modcontext->id.'.'.PHP_EOL;;

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