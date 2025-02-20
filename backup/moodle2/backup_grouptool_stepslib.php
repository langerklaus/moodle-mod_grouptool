<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * backup/moodle2/backup_grouptool_stepslib.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete grouptool structure for backup, with file and id annotations
 */
class backup_grouptool_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // Are we including userinfo?
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated!
        $grouptool = new backup_nested_element('grouptool', array('id'), array(
                'name', 'intro', 'introformat', 'alwaysshowdescription',
                'timecreated', 'timemodified', 'timedue', 'timeavailable',
                'show_members', 'allow_reg', 'immediate_reg', 'allow_unreg',
                'grpsize', 'use_size', 'use_individual', 'use_queue',
                'queues_max', 'allow_multiple', 'choose_min', 'choose_max',
                'ifmemberadded', 'ifmemberremoved', 'ifgroupdeleted'));
        $agrps = new backup_nested_element('agrps');
        $agrp = new backup_nested_element('agrp', array('id'), array(
                'grouptoolid', 'groupid', 'sort_order', 'grpsize', 'active'));
        $registrations = new backup_nested_element('registrations');
        $registration = new backup_nested_element('registration', array('id'), array(
                'agrpid', 'userid', 'timestamp', 'modified_by'));
        $queues = new backup_nested_element('queues');
        $queue = new backup_nested_element('queue', array('id'), array(
                'agrpid', 'userid', 'timestamp'));

        // We begin building the tree.
        $grouptool->add_child($agrps);
        $agrps->add_child($agrp);
        $agrp->add_child($registrations);
        $registrations->add_child($registration);
        $agrp->add_child($queues);
        $queues->add_child($queue);

        // We define sources.
        $grouptool->set_source_table('grouptool', array('id' => backup::VAR_ACTIVITYID));
        $agrp->set_source_table('grouptool_agrps', array('grouptoolid' => backup::VAR_PARENTID));
        // All the rest of elements only happen if we are including user info!
        if ($userinfo) {
            $registration->set_source_table('grouptool_registered',
                                            array('agrpid' => backup::VAR_PARENTID));
            $queue->set_source_table('grouptool_queued', array('agrpid' => backup::VAR_PARENTID));
        }

        // We define id annotations.
        $agrp->annotate_ids('group', 'groupid');

        $registration->annotate_ids('user', 'userid');
        $registration->annotate_ids('user', 'modified_by');

        $queue->annotate_ids('user', 'userid');

        // We define file annotations.
        $grouptool->annotate_files('mod_grouptool', 'intro', null); // This file area has no itemid!

        // We return the root element (grouptool), wrapped into standard activity structure.
        return $this->prepare_activity_structure($grouptool);
    }
}
