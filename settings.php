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
 * Plugin administration pages are defined here.
 *
 * @package     tool_fix_delete_modules
 * @category    admin
 * @copyright   2022 Brad Pasley <brad.pasley@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
                    'tool_fix_delete_modules_settings',
                    get_string('generalsettings', 'admin'));

    $settings->add(new admin_setting_heading(
        'tool_fix_delete_modules_general_settings',
        get_string('setting_manage_general_title', 'tool_fix_delete_modules'),
        get_string('setting_manage_general_desc', 'tool_fix_delete_modules')
    ));

    $settings->add(new admin_setting_configtext_with_maxlength(
        'tool_fix_delete_modules/minimumfaildelay',
        get_string('setting_minimumfaildelay_title', 'tool_fix_delete_modules'),
        get_string('setting_minimumfaildelay_desc', 'tool_fix_delete_modules'),
        60,
        PARAM_INT,
        $fieldsize = 10,
        $maxlength = 8
    ));

    //$ADMIN->add(
    //    'development',
    //    new admin_externalpage(
    //        'tool_fix_delete_modules', get_string('pluginname', 'tool_fix_delete_modules'),
    //        new moodle_url('/admin/tool/fix_delete_modules/index.php')
    //    )
    //);

    // Prepare settings / report section.
    $section = 'toolfixdeletemodules';
    $ADMIN->add('tools', new admin_category('toolfixdeletemodules', get_string('pluginname', 'tool_fix_delete_modules')));
    $ADMIN->add('toolfixdeletemodules', $settings);
    $ADMIN->add(
        $section,
        new admin_externalpage(
            'tool_fix_delete_modules',
            get_string('displaypage', 'tool_fix_delete_modules'),
            new moodle_url('/admin/tool/fix_delete_modules/index.php')
        )
    );
}

