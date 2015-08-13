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
 * CLI update for connect enrolments.
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *
 * @package    enrol_connect
 * @copyright  2014 University of Kent
 * @author     Skylar Kelty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . "/clilib.php");

list($options, $unrecognized) = cli_get_params(array(
    'course' => 0,
    'dry' => 0,
    'verbose' => 1
));

$options['dry'] = $options['dry'] == 1 ? true : false;
$options['verbose'] = $options['verbose'] == 0 ? false : true;

if ($options['dry']) {
    mtrace("Running enrol sync in dry mode.");
}

$plugin = enrol_get_plugin('connect');

if (empty($options['course'])) {
    $plugin->global_sync($options['dry'], $options['verbose']);
} else {
    $plugin->sync($options['course'], $options['dry'], $options['verbose']);
}
