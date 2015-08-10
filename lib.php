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
     * Returns a list of all enrol instances by ID.
     */
    private function get_enrol_instances($courseid = 0) {
        global $DB;

        $where = 'enrol IN (:enrol1, :enrol2)';
        $params = array(
            'enrol1' => 'connect',
            'enrol2' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED
        );

        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        return $DB->get_records_sql('SELECT * FROM {enrol} WHERE ' . $where, $params);
    }

    /**
     * Run a sync against a given courseid, or the whole site.
     */
    public function sync($courseid = 0, $enrolinstances = null) {
        if (!empty($enrolinstances)) {
            debugging("enrol_connect now expects the \$enrolinstances parameter to be blank when single-syncing.");
        }

        // Grab a list of all connect instances.
        $enrolinstances = $this->get_enrol_instances($courseid);
        if (empty($enrolinstances)) {
            return;
        }

        // Get the current and latest info to compare.
        $currentinfo = $this->get_current_info($courseid);
        $latestinfo = $this->get_latest_info($courseid);

        $manualplugin = enrol_get_plugin('manual');

        // New enrols.
        foreach ($latestinfo as $course => $users) {
            foreach ($users as $username => $user) {
                // Make sure the user exists.
                if (empty($user->userid)) {
                    $obj = \local_connect\user::get($user->connectuserid);
                    if (!$obj->create_in_moodle()) {
                        continue;
                    }

                    $user->userid = $obj->mid;
                }

                // See if we exist already.
                if (isset($currentinfo[$course]) && isset($currentinfo[$course][$username])) {
                    if (isset($currentinfo[$course][$username]->enrols[$user->enrolid])) {
                        continue;
                    }
                }

                echo "   Adding user '{$username}' to course '{$course}' with enrol id '{$user->enrolid}'..\n";
                $instance = $enrolinstances[$user->enrolid];
                $this->enrol_user($instance, $user->userid, $user->role, 0, 0);
            }
        }

        // Check old enrols.
        foreach ($currentinfo as $course => $users) {
            if (!isset($latestinfo[$course])) {
                $latestinfo[$course] = array();
            }

            $context = \context_course::instance($course);

            foreach ($users as $username => $user) {
                // Remove old roles.
                if (!isset($latestinfo[$course][$username])) {
                    foreach ($user->enrols as $enrolid => $enrolname) {
                        echo "   Removing user '{$username}' from course '{$course}' ('{$enrolname}' plugin)..\n";
                        $instance = $enrolinstances[$enrolid];
                        $plugin = $enrolname == 'manual' ? $manualplugin : $this;
                        $plugin->unenrol_user($instance, $user->userid);
                    }
                    continue;
                }

                $latest = $latestinfo[$course][$username];

                // Add new roles.
                if (!isset($user->roles[$latest->role])) {
                    echo "   Adding role '{$latest->role}' to user '{$username}' in course '{$course}'..\n";
                    $instance = $enrolinstances[$latest->enrolid];
                    role_assign($latest->role, $user->userid, $context->id, 'enrol_connect', $instance->id);
                }

                // Remove old roles
                foreach ($user->roles as $roleid => $name) {
                    if ($roleid != $latest->role) {
                        echo "   Removing role '{$name}' from user '{$username}' in course '{$course}'..\n";
                        $instance = $enrolinstances[$latest->enrolid];
                        role_unassign($roleid, $user->userid, $context->id, 'enrol_connect', $instance->id);
                    }
                }
            }
        }
    }

    /**
     * Run a global sync.
     */
    public function global_sync() {
        return $this->sync(0);
    }

    /**
     * Grab the latest info.
     */
    private function get_latest_info($courseid = 0) {
        $info = array();

        $latestroles = $this->get_latest_roles($courseid);
        foreach ($latestroles as $role) {
            if (!isset($info[$role->courseid])) {
                $info[$role->courseid] = array();
            }

            $infoblock = new \stdClass();

            if (isset($info[$role->courseid][$role->username])) {
                $infoblock = $info[$role->courseid][$role->username];
            }

            $infoblock->connectuserid = $role->cuserid;
            $infoblock->userid = $role->userid;
            $infoblock->enrolid = $role->enrolid;
            $infoblock->role = $role->rolemid;

            $info[$role->courseid][$role->username] = $infoblock;
        }
        unset($latestroles);

        return $info;
    }

    /**
     * Return a list of everyones roles in all courses.
     */
    private function get_latest_roles($courseid = 0) {
        global $DB;

        $where = '';
        $params = array();
        if ($courseid > 0) {
            $where = 'AND e.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = <<<SQL
            SELECT ce.id, cu.id as cuserid, cu.login as username, cu.mid as userid, e.id as enrolid, e.courseid, cr.name as role, cr.mid as rolemid
            FROM {connect_enrolments} ce
            INNER JOIN {enrol} e
                ON e.customint1=ce.courseid AND e.enrol='connect' {$where}
            INNER JOIN {connect_user} cu
                ON cu.id=ce.userid
            INNER JOIN {connect_role} cr
                ON cr.id=ce.roleid
SQL;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Merge enrols and roles lists.
     */
    private function get_current_info($courseid = 0) {
        $info = array();

        $currentroles = $this->get_current_roles($courseid);
        foreach ($currentroles as $role) {
            if (!isset($info[$role->courseid])) {
                $info[$role->courseid] = array();
            }

            $infoblock = new \stdClass();
            $infoblock->roles = array();

            if (isset($info[$role->courseid][$role->username])) {
                $infoblock = $info[$role->courseid][$role->username];
            }

            $infoblock->userid = $role->userid;
            $infoblock->roles[$role->roleid] = $role->role;

            $info[$role->courseid][$role->username] = $infoblock;
        }
        unset($currentroles);

        $currentenrols = $this->get_current_enrols($courseid);
        foreach ($currentenrols as $enrol) {
            if (!isset($info[$enrol->courseid])) {
                $info[$enrol->courseid] = array();
            }

            $infoblock = new \stdClass();
            $infoblock->enrols = array();

            if (isset($info[$enrol->courseid][$enrol->username])) {
                $infoblock = $info[$enrol->courseid][$enrol->username];
            }

            $infoblock->userid = $enrol->userid;
            $infoblock->enrols[$enrol->enrolid] = $enrol->enrol;

            $info[$enrol->courseid][$enrol->username] = $infoblock;
        }
        unset($currentenrols);

        return $info;
    }

    /**
     * Return a list of everyones roles in all courses.
     */
    private function get_current_roles($courseid = 0) {
        global $DB;

        $where = '';
        $params = array();
        if ($courseid > 0) {
            $where = 'AND ctx.instanceid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = <<<SQL
            SELECT ra.id, u.id as userid, u.username, ctx.instanceid as courseid, r.id as roleid, r.shortname as role
            FROM {role_assignments} ra
            INNER JOIN {role} r
                ON r.id=ra.roleid
            INNER JOIN {user} u
                ON u.id=ra.userid
            INNER JOIN {context} ctx
                ON ctx.id=ra.contextid AND ctx.contextlevel=50 {$where}
SQL;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return a list of everyone in all courses.
     */
    private function get_current_enrols($courseid = 0) {
        global $DB;

        $where = '';
        $params = array();
        if ($courseid > 0) {
            $where = 'AND e.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = <<<SQL
            SELECT ue.id, u.id as userid, u.username, e.courseid, e.id as enrolid, e.enrol as enrol
            FROM {user_enrolments} ue
            INNER JOIN {enrol} e
                ON e.id=ue.enrolid {$where}
            INNER JOIN {user} u
                ON u.id=ue.userid
            INNER JOIN {context} ctx
                ON ctx.instanceid=e.courseid AND ctx.contextlevel=50
            WHERE e.enrol='manual' OR e.enrol='connect'
SQL;

        return $DB->get_records_sql($sql, $params);
    }
}
