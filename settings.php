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
 * Connect enrolment plugin settings and presets.
 *
 * @package    enrol_connect
 * @copyright  2015 University of Kent
 * @author     Skylar Kelty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('enrol_connect_settings', '', get_string('pluginname_desc', 'enrol_connect')));
    $settings->add(new admin_setting_configmultiselect(
        'enrol_connect/defaultstatuses',
        'Status codes to sync',
        'Which status codes should be pulled into Moodle?',
        array('A', 'J', 'P', 'R', 'T', 'W', 'Y', 'H', '?'),
        array(
            '?' => 'Not Known',
            'A' => 'Not here, not registered',
            'C' => 'Here but not yet registered',
            // 'D' => 'Record for deletion', // Never allow this.
            'H' => 'Holding / Awaiting further information',
            'I' => 'Intermission',
            'J' => 'Resit candidate',
            'M' => 'Modular student, not in attendance',
            'P' => 'Provisional registration',
            'R' => 'Registered',
            'S' => 'Sabbatical Officer having completed POS',
            'T' => 'Thesis submitted',
            'W' => 'Writing up',
            'X' => 'Record for archiving',
            'Y' => 'Compulsory year abroad / placement',
            'Z' => 'Deferred entry'
        )
    ));
}
