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
 * The mod_grouptool_agrp_created event.
 *
 * @package       mod_grouptool
 * @since         Moodle 2.7
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

class agrp_created extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grouptool_agrps';
    }

    public static function create_from_object(\stdClass $cm, $agrp) {
        $event = self::create(array(
            'objectid' => $agrp->id,
            'context' => \context_module::instance($cm->id),
            'other' => (array)$agrp
        ));
        return $event;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php",
                               array('id'  => $this->contextinstanceid,
                                     'tab' => 'overview'));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid,
                     $this->objecttable,
                     'create agrp with id \''.$this->data['other']['id'].'\'',
                     'view.php?id=' . $this->contextinstanceid.'&tab=overview',
                     $this->objectid,
                     $this->contextinstanceid);
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (!empty($this->data['other']['id'])) {
            return "The user with id '$this->userid' created an active groups entry with id '".
                   $this->data['other']['id']." for '{$this->objecttable}' with the course module id '".
                   $this->contextinstanceid."' for group with id '".$this->data['other']['groupid'].".";
        }
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventagrpcreated', 'grouptool');
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        // Make sure this class is never used without proper object details.
        if (empty($this->objectid) || empty($this->objecttable)) {
            throw new \coding_exception('The agrp_created event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if (empty($this->data['other']['groupid'])) {
            throw new \coding_exception('Group-ID must be specified.');
        }

        if (empty($this->data['other']['id'])) {
            throw new \coding_exception('Active-Group-ID must be specified.');
        }
    }
}