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
 * grpcreationform.class.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->dirroot.'/mod/grouptool/lib.php');

/**
 * class representing the moodleform used in the administration tab
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_grp_creation_form extends moodleform {

    /**
     * Variable containing reference to our sortlist, so we can alter current active entries afterwards
     */
    private $_sortlist = null;

    public function update_cur_active($curactive = null) {
        if (!empty($curactive) && is_array($curactive)) {
            $this->_sortlist->_options['curactive'] = $curactive;
        }
    }

    /**
     * Definition of group creation form
     *
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     */
    protected function definition() {

        global $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $coursecontext = context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_creation');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:create_groups', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed!
             */
            $mform->addElement('header', 'group_creation', get_string('groupcreation',
                                                                      'grouptool'));

            $options = array(0 => get_string('all'));
            $options += $this->_customdata['roles'];
            $mform->addElement('select', 'roleid', get_string('selectfromrole', 'group'), $options);
            $student = get_archetype_roles('student');
            $student = reset($student);

            if ($student and array_key_exists($student->id, $options)) {
                $mform->setDefault('roleid', $student->id);
            }

            // Since 2.8 the capability gets checked by cohort_get_available_cohorts()!
            $cohorts = cohort_get_available_cohorts($coursecontext, true, 0, 0);
            if (count($options) != 0) {
                $options = array(0 => get_string('anycohort', 'cohort'));
                foreach ($cohorts as $cohort) {
                     $options[$cohort->id] = $cohort->name;
                }
                $mform->addElement('select', 'cohortid', get_string('selectfromcohort',
                                                                    'grouptool'), $options);
                $mform->setDefault('cohortid', '0');
            } else {
                $mform->addElement('hidden', 'cohortid');
                $mform->setType('cohortid', PARAM_INT);
                $mform->setConstant('cohortid', '0');
            }

            $mform->addElement('hidden', 'seed');
            $mform->setType('seed', PARAM_INT);

            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('define_amount_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_GROUPS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('define_amount_members',
                                                                       'grouptool'),
                                                            GROUPTOOL_MEMBERS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('create_1_person_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_1_PERSON_GROUPS);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('create_fromto_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_FROMTO_GROUPS);
            $mform->addGroup($radioarray, 'modearray',
                             get_string('groupcreationmode', 'grouptool'),
                             html_writer::empty_tag('br'), false);
            $mform->setDefault('mode', GROUPTOOL_GROUPS_AMOUNT);
            $mform->addHelpButton('modearray', 'groupcreationmode', 'grouptool');

            $mform->addElement('text', 'amount', get_string('group_or_member_count', 'grouptool'),
                               array('size' => '4'));
            $mform->disabledif ('amount', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->disabledif ('amount', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('amount', PARAM_RAW);
            $mform->setDefault('amount', 2);

            $fromto = array();
            $fromto[] = $mform->createElement('text', 'from', get_string('from'));
            $mform->setDefault('from', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('from', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'to', get_string('to'));
            $mform->setDefault('to', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('to', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'digits', get_string('digits', 'grouptool'));
            $mform->setDefault('digits', 2);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('digits', PARAM_RAW);
            $mform->addGroup($fromto, 'fromto', get_string('groupfromtodigits', 'grouptool'),
                             array(' - ', ' '.get_string('digits', 'grouptool').' '), false);
            $mform->disabledif ('from', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->disabledif ('to', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->disabledif ('digits', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->setAdvanced('fromto');

            $mform->addElement('checkbox', 'nosmallgroups', get_string('nosmallgroups', 'group'));
            $mform->addHelpButton('nosmallgroups', 'nosmallgroups', 'grouptool');
            $mform->disabledif ('nosmallgroups', 'mode', 'noteq', GROUPTOOL_MEMBERS_AMOUNT);
            $mform->disabledif ('nosmallgroups', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            $mform->setAdvanced('nosmallgroups');

            $options = array('no'        => get_string('noallocation', 'group'),
                    'random'    => get_string('random', 'group'),
                    'firstname' => get_string('byfirstname', 'group'),
                    'lastname'  => get_string('bylastname', 'group'),
                    'idnumber'  => get_string('byidnumber', 'group'));
            $mform->addElement('select', 'allocateby', get_string('allocateby', 'group'), $options);
            if ($grouptool->allow_reg) {
                $mform->setDefault('allocateby', 'no');
            } else {
                $mform->setDefault('allocateby', 'random');
            }
            $mform->disabledif ('allocateby', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->disabledif ('allocateby', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);

            $naminggrp = array();
            $naminggrp[] =& $mform->createElement('text', 'namingscheme', '', array('size' => '64'));
            $naminggrp[] =& $mform->createElement('static', 'tags', '',
                                                  get_string('name_scheme_tags', 'grouptool'));
            $namingstd = get_config('mod_grouptool', 'name_scheme');
            $namingstd = (!empty($namingstd) ? $namingstd : get_string('group', 'group').' #');
            $mform->setDefault('namingscheme', $namingstd);
            $mform->setType('namingscheme', PARAM_RAW);
            $mform->addGroup($naminggrp, 'naminggrp', get_string('namingscheme', 'grouptool'), ' ', false);
            $mform->addHelpButton('naminggrp', 'namingscheme', 'grouptool');
            // Init JS!
            $PAGE->requires->string_for_js('showmore', 'form');
            $PAGE->requires->string_for_js('showless', 'form');
            $PAGE->requires->yui_module('moodle-mod_grouptool-groupcreation',
                    'M.mod_grouptool.init_groupcreation',
                    array(array('fromto_mode' => GROUPTOOL_FROMTO_GROUPS)));

            $selectgroups = $mform->createElement('selectgroups', 'grouping', get_string('createingrouping', 'group'));

            $options = array('0' => get_string('no'));
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $options['-1'] = get_string('onenewgrouping', 'grouptool');

            }
            $selectgroups->addOptGroup("", $options);
            if ($groupings = groups_get_all_groupings($course->id)) {
                $options = array();
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = strip_tags(format_string($grouping->name));
                }
                $selectgroups->addOptGroup("————————————————————————", $options);
            }
            $mform->addElement($selectgroups);
            if ($groupings) {
                $mform->setDefault('grouping', '0');
            }
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $mform->addElement('text', 'groupingname', get_string('groupingname', 'group'));
                $mform->setType('groupingname', PARAM_MULTILANG);
                $mform->disabledif ('groupingname', 'grouping', 'noteq', '-1');
            }

            $mform->addElement('submit', 'createGroups', get_string('createGroups', 'grouptool'));
        }
    }

    /**
     * Validation for administration-form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        global $DB;
        $parenterrors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['createGroups']) && $data['grouping'] == "-1"
                 && (empty($data['groupingname']) || $data['groupingname'] == "")) {
            $errors['groupingname'] = get_string('must_specify_groupingname', 'grouptool');
        }
        if (!empty($data['createGroups'])
            && ((clean_param($data['amount'], PARAM_INT) <= 0) || !ctype_digit($data['amount']))
            && (($data['mode'] == GROUPTOOL_GROUPS_AMOUNT) || ($data['mode'] == GROUPTOOL_MEMBERS_AMOUNT))) {
            $errors['amount'] = get_string('mustbeposint', 'grouptool');
        }
        if (!empty($data['createGroups'])
            && ($data['mode'] == GROUPTOOL_FROMTO_GROUPS)) {
            if ($data['from'] > $data['to']) {
                $errors['fromto'] = get_string('fromgttoerror', 'grouptool');
            }
            if ((clean_param($data['from'], PARAM_INT) < 0) || !ctype_digit($data['from'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('from').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('from').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['to'], PARAM_INT) < 0) || !ctype_digit($data['to'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('to').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('to').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['digits'], PARAM_INT) < 0) || !ctype_digit($data['digits'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('digits', 'grouptool').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('digits', 'grouptool').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
        }

        return array_merge($parenterrors, $errors);
    }
}