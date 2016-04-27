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
 * Adds new instance of enrol_connect to specified course.
 *
 * @package    enrol_connect
 * @copyright  2014 University of Kent
 * @author     Skylar Kelty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . "/enrol/connect/edit_form.php");

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array(
    'id' => $courseid
), '*', MUST_EXIST);

$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/connect:config', $context);

$PAGE->set_url('/enrol/connect/edit.php', array(
    'courseid' => $course->id,
    'id' => $instanceid
));

$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array(
    'id' => $course->id
));

if (!enrol_is_enabled('connect')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('connect');

if ($instanceid) {
    $instance = $DB->get_record('enrol', array(
        'courseid' => $course->id,
        'enrol' => 'connect',
        'id' => $instanceid
    ), '*', MUST_EXIST);
} else {
    // No instance yet, we have to add new instance.
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }

    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array(
        'id' => $course->id
    )));

    $instance = new stdClass();
    $instance->id         = null;
    $instance->courseid   = $course->id;
    $instance->enrol      = 'connect';
    $instance->customint1 = 0; // Course MID.
    $instance->customtext1 = get_config('enrol_connect', 'defaultstatuses');
}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}


$mform = new enrol_connect_edit_form(null, array($instance, $enrol, $course));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    if ($instance->id) {
        $instance->status       = $data->status;
        $instance->customint1   = $data->customint1;
        $instance->customtext1   = $data->customtext1;
        $instance->timemodified = time();
        $DB->update_record('enrol', $instance);
    } else {
        $enrol->add_instance($course, array(
            'status' => $data->status,
            'customint1' => $data->customint1,
            'customtext1' => $data->customtext1
        ));
    }

    redirect($returnurl);
} else {
    $mform->set_data($instance);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_connect'));

echo $OUTPUT->header();
$mform->display();

echo <<<HTML5
        <p>Available status codes:</p>
        <pre>
        ?      Not Known
        A      Not here, not registered
        C      Here but not yet registered
        D      Record for deletion
        H      Holding / Awaiting further information
        I      Intermission
        J      Resit candidate
        M      Modular student, not in attendance
        P      Provisional registration
        R      Registered
        S      Sabbatical Officer having completed POS
        T      Thesis submitted
        W      Writing up
        X      Record for archiving
        Y      Compulsory year abroad / placement
        Z      Deferred entry
        </pre>
HTML5;

echo $OUTPUT->footer();
