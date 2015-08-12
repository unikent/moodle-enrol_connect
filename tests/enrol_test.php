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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests new Kent enrolment code
 */
class enrol_tests extends \local_connect\tests\connect_testcase
{
    /**
     * Basic test - Create an enrol container and make sure
     * the user's are enrolled.
     */
    public function test_basic() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->enable_enrol_plugin();
        $this->push_roles();

        // First, create a course.
        $course = $this->generate_course();
        $courseobj = \local_connect\course::get($course);
        $courseobj->create_in_moodle();

        // Test the global count.
        $enrolments = \local_connect\enrolment::get_all();
        $this->assertEquals(0, count($enrolments));

        // Next insert a couple of enrolments on this course.
        $this->generate_enrolments(30, $course, 'sds_student');

        // Make sure there is no-one on the course.
        $context = \context_course::instance($courseobj->mid);
        $users = get_enrolled_users($context);
        $this->assertEquals(0, count($users));

        // Sync the users.
        $courseobj->sync_enrolments();

        // Re-count the users.
        $users = get_enrolled_users($context);
        $this->assertEquals(30, count($users));

        // Re-sync and count, to make sure we don't do anything.
        $courseobj->sync_enrolments();

        // Re-count.
        $users = get_enrolled_users($context);
        $this->assertEquals(30, count($users));

        // Add more enrolments.
        $this->generate_enrolments(2, $course, 'sds_convenor');
        $teacher = $this->generate_enrolment($course, 'sds_teacher');

        // Sync the users.
        $courseobj->sync_enrolments();

        // Re-count the users.
        $users = get_enrolled_users($context);
        $this->assertEquals(33, count($users));

        // Remove some enrolments.
        $DB->delete_records('connect_enrolments', array(
            'id' => $teacher
        ));

        // Sync the users.
        $courseobj->sync_enrolments();

        // Re-count the users.
        $users = get_enrolled_users($context);
        $this->assertEquals(32, count($users));
    }
}
