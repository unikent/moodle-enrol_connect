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
 * Connect enrolment plugin main library file.
 *
 * @package    enrol_connect
 * @copyright  2014 University of Kent
 * @author     Skylar Kelty
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_connect_plugin extends enrol_plugin
{

    /**
     * Returns localised name of enrol instance
     *
     * @param object $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        return "SDS (Connect ID: " . s($instance->customint1) . ")";
    }

    /**
     * Does this plugin assign protected roles are can they be manually removed?
     * @return bool - false means anybody may tweak roles, it does not use itemid and component when assigning roles
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Does this plugin allow manual enrolments?
     *
     * @param stdClass $instance course enrol instance
     * All plugins allowing this must implement 'enrol/xxx:enrol' capability
     *
     * @return bool - false means nobody may add more enrolments manually
     */
    public function allow_enrol(stdClass $instance) {
        return false;
    }

    /**
     * Does this plugin allow manual unenrolment of all users?
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - false means nobody may touch user_enrolments
     */
    public function allow_unenrol(stdClass $instance) {
        return false;
    }

    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * All plugins allowing this must implement 'enrol/xxx:manage' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means it is possible to change enrol period and status in user_enrolments table
     */
    public function allow_manage(stdClass $instance) {
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/connect:config', $context);
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('enrol/connect:config', $context)) {
            return null;
        }

        return new moodle_url('/enrol/connect/edit.php', array(
            'courseid' => $courseid
        ));
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'connect') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/connect:config', $context)) {
            $editlink = new moodle_url("/enrol/connect/edit.php", array(
                'id' => $instance->id,
                'courseid' => $instance->courseid
            ));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Add new instance of enrol plugin.
     * @param stdClass $course
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        $exists = $DB->record_exists('enrol', array(
            'enrol' => 'connect',
            'courseid' => $course->id,
            'customint1' => $fields['customint1']
        ));

        if ($exists) {
            return null;
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Sync all meta course links.
     *
     * @return int -1 means error, otherwise returns a count of changes
     */
    public function sync($courseid, $instances) {
        $context = \context_course::instance($courseid, MUST_EXIST);
        $enrolments = \enrol_connect\task\helpers::get_enrolments($instances, $courseid);
        if (isset($enrolments[$courseid])) {
            $enrolments = $enrolments[$courseid];
        } else {
            $enrolments = array();
        }

        $this->sync_bulk($context, $courseid, $instances, $enrolments, array());
    }

    /**
     * Sync all meta course links.
     *
     * @return int -1 means error, otherwise returns a count of changes
     */
    public function sync_bulk($context, $courseid, $instances, $map = array(), $roles = array()) {
        global $DB;

        if (!enrol_is_enabled('connect')) {
            return 0;
        }

        // Count changes.
        $changes = 0;

        // Now, we start the enrolments.
        foreach ($instances as $instance) {
            // Get all enrolments for this instance.
            $enrolments = array();
            if ($instance->customint1 > 0) {
                $enrolments = \local_connect\enrolment::get_by("courseid", $instance->customint1, true);
            } else {
                // This is a default instance.
                // Get all enrolments for everything that has this course for a mid.
                $courses = \local_connect\course::get_by('mid', $courseid, true);
                foreach ($courses as $course) {
                    $ce = \local_connect\enrolment::get_by("courseid", $course->id, true);
                    $enrolments = array_merge($enrolments, $ce);
                }
            }

            // Go through and add everything that needs adding.
            foreach ($enrolments as $enrolment) {
                $user = $enrolment->user;
                $role = $enrolment->role;

                // Try to create the user if it does not exist.
                if (!$user->is_in_moodle()) {
                    $user->create_in_moodle();
                }

                // Check these things are in Moodle.
                if (empty($user->mid) || empty($role->mid)) {
                    continue;
                }

                // Are we already enrolled?
                $enrolled = false;
                if (isset($map[$user->mid])) {
                    if ($map[$user->mid] === true) {
                        continue;
                    }

                    if (isset($map[$user->mid][$instance->id])) {
                        $enrolled = true;
                    }
                }

                // If we are not enrolled, enrol us.
                if (!$enrolled) {
                    $this->enrol_user($instance, $user->mid, $role->mid, 0, 0);
                    $changes++;
                } else {
                    // Unset it.
                    $map[$user->mid] = true;

                    // Check the role is okay.
                    if (isset($roles[$user->mid])) {
                        $assign = true;
                        foreach ($roles[$user->mid] as $k => $roleid) {
                            if ($roleid == $role->mid) {
                                $assign = false;
                            } else {
                                role_unassign($roleid, $user->mid, $context->id, 'enrol_connect', $instance->id);
                                $changes++;
                            }
                        }

                        if ($assign) {
                            role_assign($role->mid, $user->mid, $context->id, 'enrol_connect', $instance->id);
                            $changes++;
                        }
                    }
                }
            }
        }

        // Right! The leftovers are to be removed.
        if (!empty($map)) {
            foreach ($map as $userid => $uinstances) {
                if (is_array($uinstances)) {
                    foreach ($uinstances as $enrolid => $instance) {
                        $this->unenrol_user($instance, $userid);
                        $changes++;
                    }
                }
            }
        }

        return $changes;
    }
}
