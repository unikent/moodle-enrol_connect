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
class sync extends \core\task\scheduled_task
{
    public function get_name() {
        return "Connect Enrol Sync";
    }

    public function execute() {
        global $DB;

        $enrol = enrol_get_plugin('connect');

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

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

        foreach ($instances as $course => $set) {
            $enrol->sync($course, $set);
        }
    }
}