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
 * Adds new instance of enrol_connect to specified course
 * or edits current instance.
 *
 * @package    enrol_connect
 * @copyright  2014 University of Kent
 * @author     Skylar Kelty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class enrol_connect_edit_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_connect'));

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', 'Enabled', $options);
        $mform->setDefault('status', $plugin->get_config('status'));

        $mform->addElement('text', 'customint1', 'Connect Course ID');
        $mform->setDefault('customint1', $plugin->get_config('customint1'));
        $mform->setType('customint1', PARAM_INT);

        $mform->addElement('text', 'customtext1', 'Allowed status codes');
        $status = $plugin->get_config('customtext1');
        if (empty($status)) {
            $status = get_config('enrol_connect', 'defaultstatuses');
        }
        $mform->setDefault('customtext1', $status);
        $mform->setType('customtext1', PARAM_TAGLIST);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, ($instance->id ? null : 'Add Instance'));

        $this->set_data($instance);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $exists = $DB->record_exists('connect_course', array(
            'id' => $data['customint1']
        ));

        if (!$exists) {
            $errors['customint1'] = 'Invalid Connect course ID.';
        }

        return $errors;
    }
}
