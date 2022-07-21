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

require_once("$CFG->libdir/formslib.php");

class fix_delete_modules_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;

        $this->actionurl = new \moodle_url('/admin/tool/fix_delete_modules/delete_module.php', array(
            'sesskey'          => sesskey()
        ));

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('submit', 'submit',  get_string('button_delete_mod_without_backup', 'tool_fix_delete_modules'));
        $mform->addElement('hidden', 'action', 'delete_module');
        $mform->setType('action', PARAM_ALPHAEXT);
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'cminstanceid', $this->_customdata['cminstanceid']);
        $mform->setType('cminstanceid', PARAM_INT);
        $mform->addElement('hidden', 'cmname', $this->_customdata['cmname']);
        $mform->setType('cmname', PARAM_ALPHAEXT);

    }

}