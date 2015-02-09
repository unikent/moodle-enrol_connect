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

        $info = $this->get_current_info();

        print_r($info);
    }

    /**
     * Munge enrols and roles lists.
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
