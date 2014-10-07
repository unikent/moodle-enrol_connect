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

namespace enrol_connect\task;

global $CFG;
require_once($CFG->libdir . "/enrollib.php");

/**
 * Connect enrol Sync
 */
class helpers
{
    /**
     * Returns a list of all enrol instances by course ID.
     */
    public static function get_enrol_instances() {
        global $DB;

        $instances = array();

        $rs = $DB->get_recordset('enrol', array(
            'enrol' => 'connect',
            'status' => ENROL_INSTANCE_ENABLED
        ));

        foreach ($rs as $record) {
            if (!isset($instances[$record->courseid])) {
                $instances[$record->courseid] = array();
            }

            $instances[$record->courseid][] = $record;
        }

        $rs->close();
        unset($rs);

        return $instances;
    }

    /**
     * Get a list of all enrolments by course ID.
     */
    public static function get_enrolments($instances, $courseid = '*') {
        global $DB;

        $enrolments = array();

        $rs = null;
        if ($courseid == '*') {
	        $rs = $DB->get_recordset_sql("
	            SELECT ue.id, ue.userid, e.courseid, ue.enrolid
	                FROM {user_enrolments} ue
	            INNER JOIN {enrol} e
	                ON e.id = ue.enrolid
	            WHERE e.enrol=:enrol AND e.status=:status
	        ", array(
	            'enrol' => 'connect',
	            'status' => ENROL_INSTANCE_ENABLED
	        ));
	    } else {
	        $rs = $DB->get_recordset_sql("
	            SELECT ue.id, ue.userid, e.courseid, ue.enrolid
	                FROM {user_enrolments} ue
	            INNER JOIN {enrol} e
	                ON e.id = ue.enrolid
	            WHERE e.enrol=:enrol AND e.status=:status AND e.courseid=:courseid
	        ", array(
	            'enrol' => 'connect',
	            'status' => ENROL_INSTANCE_ENABLED,
	            'courseid' => $courseid
	        ));
	    }

        foreach ($rs as $record) {
            if (!isset($enrolments[$record->courseid])) {
                $enrolments[$record->courseid] = array();
            }

            if (!isset($enrolments[$record->courseid][$record->userid])) {
                $enrolments[$record->courseid][$record->userid] = array();
            }

            if (isset($instances[$record->courseid])) {
                foreach ($instances[$record->courseid] as $k => $v) {
                    $enrolments[$record->courseid][$record->userid][$v->id] = $v;
                }
            }
        }

        $rs->close();
        unset($rs);

        return $enrolments;
    }

    /**
     * Returns a list of roles.
     */
    public static function get_roles() {
        global $DB;

        $roles = array();

        $rs = $DB->get_recordset_sql("
            SELECT ra.id, ra.contextid, ra.userid, ra.roleid
                FROM {role_assignments} ra
            INNER JOIN {context} ctx
                ON ctx.id=ra.contextid
            INNER JOIN {role} r
                ON r.id=ra.roleid
            WHERE
                ctx.contextlevel=:level
                AND r.shortname IN (:ss, :st, :sc)
        ", array(
            "level" => \CONTEXT_COURSE,
            "ss" => "sds_student",
            "st" => "sds_teacher",
            "sc" => "sds_convenor"
        ));

        foreach ($rs as $record) {
            if (!isset($roles[$record->contextid])) {
                $roles[$record->contextid] = array();
            }

            if (!isset($roles[$record->contextid][$record->userid])) {
                $roles[$record->contextid][$record->userid] = array();
            }

            $roles[$record->contextid][$record->userid][] = $record->roleid;
        }

        $rs->close();
        unset($rs);

        return $roles;
    }
}