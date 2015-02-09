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

namespace enrol_connect;

/**
 * Connect enrol sync helpers
 */
class sync
{
    /**
     * Run a global sync.
     */
    public function run() {
        global $DB;

        $currentinfo = $this->get_current_info();
        $latestinfo = $this->get_latest_info();

        print_r($latestinfo);
    }

    /**
     * Grab the latest info.
     */
    private function get_latest_info() {
        $info = array();

        $latestroles = $this->get_latest_roles();
        foreach ($latestroles as $role) {
            if (!isset($info[$role->courseid])) {
                $info[$role->courseid] = array();
            }

            $infoblock = new \stdClass();
            if (isset($info[$role->courseid][$role->username])) {
                $infoblock = $info[$role->courseid][$role->username];
            }

            $infoblock->roles = explode(',', $role->roles);

            $info[$role->courseid][$role->username] = $infoblock;
        }
        unset($latestroles);

        return $info;
    }

    /**
     * Return a list of everyones roles in all courses.
     */
    private function get_latest_roles() {
        global $DB;

        $sql = <<<SQL
            SELECT ce.id, cu.login as username, e.courseid, GROUP_CONCAT(cr.name) as roles
            FROM {connect_enrolments} ce
            INNER JOIN {enrol} e
                ON e.customint1=ce.courseid
            INNER JOIN {connect_user} cu
                ON cu.id=ce.userid
            INNER JOIN {connect_role} cr
                ON cr.id=ce.roleid
            GROUP BY cu.login, e.courseid
SQL;
        
        return $DB->get_records_sql($sql);
    }

    /**
     * Merge enrols and roles lists.
     */
    private function get_current_info() {
        $info = array();

        $currentroles = $this->get_current_roles();
        foreach ($currentroles as $role) {
            if (!isset($info[$role->courseid])) {
                $info[$role->courseid] = array();
            }

            $infoblock = new \stdClass();
            if (isset($info[$role->courseid][$role->username])) {
                $infoblock = $info[$role->courseid][$role->username];
            }

            $infoblock->roles = explode(',', $role->roles);

            $info[$role->courseid][$role->username] = $infoblock;
        }
        unset($currentroles);

        $currentenrols = $this->get_current_enrols();
        foreach ($currentenrols as $enrol) {
            if (!isset($info[$enrol->courseid])) {
                $info[$enrol->courseid] = array();
            }

            $infoblock = new \stdClass();
            if (isset($info[$enrol->courseid][$enrol->username])) {
                $infoblock = $info[$enrol->courseid][$enrol->username];
            }

            $infoblock->enrols = explode(',', $enrol->enrols);

            $info[$enrol->courseid][$enrol->username] = $infoblock;
        }
        unset($currentenrols);

        return $info;
    }

    /**
     * Return a list of everyones roles in all courses.
     */
    private function get_current_roles() {
        global $DB;

        $sql = <<<SQL
            SELECT ra.id, u.username, ctx.instanceid as courseid, GROUP_CONCAT(r.shortname) as roles
            FROM {role_assignments} ra
            INNER JOIN {role} r
                ON r.id=ra.roleid
            INNER JOIN {user} u
                ON u.id=ra.userid
            INNER JOIN {context} ctx
                ON ctx.id=ra.contextid AND ctx.contextlevel=50
            GROUP BY u.username, ctx.instanceid
SQL;

        return $DB->get_records_sql($sql);
    }

    /**
     * Return a list of everyone in all courses.
     */
    private function get_current_enrols() {
        global $DB;

        $sql = <<<SQL
            SELECT ue.id, u.username, e.courseid, GROUP_CONCAT(e.enrol) as enrols
            FROM {user_enrolments} ue
            INNER JOIN {enrol} e
                ON e.id=ue.enrolid
            INNER JOIN {user} u
                ON u.id=ue.userid
            INNER JOIN {context} ctx
                ON ctx.instanceid=e.courseid AND ctx.contextlevel=50
            WHERE e.enrol='manual' OR e.enrol='connect'
            GROUP BY u.username, e.courseid
SQL;

        return $DB->get_records_sql($sql);
    }
}
