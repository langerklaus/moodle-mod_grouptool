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
 * locallib.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->dirroot.'/mod/grouptool/adminform.class.php');
require_once($CFG->dirroot.'/mod/grouptool/grpcreationform.class.php');
require_once($CFG->dirroot.'/mod/grouptool/renderer.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/lib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_grade.php');
require_once($CFG->libdir.'/pdflib.php');

/**
 * class representing the moodleform used in the import-tab
 *
 * @package       mod
 * @subpackage    grouptool
 * @copyright     2012 onwards Philipp Hager {@link e0803285@gmail.com}
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_view_import_form extends moodleform {
    /**
     * Definition of import form
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

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'import');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:register_students', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed
             */
            $mform->addElement('header', 'groupuser_import', get_string('groupuser_import',
                                                                        'grouptool'));

            $active = new sortlist($course->id, $cm, mod_grouptool::FILTER_ACTIVE);
            $inactive = new sortlist($course->id, $cm, mod_grouptool::FILTER_INACTIVE);

            $groups = $mform->createElement('selectgroups', 'group', get_string('choose_targetgroup', 'grouptool'), null,
                                            array('size' => 15));

            if (!empty($active->groups)) {
                $options = array();
                foreach ($active->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('activegroups', 'grouptool'), $options);
            }

            if (!empty($inactive->groups)) {
                $options = array();
                foreach ($inactive->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('inactivegroups', 'grouptool'), $options);
            }
            $groups->setMultiple(true);
            $mform->addElement($groups);
            $mform->setType('group', PARAM_INT);
            $mform->addRule('group', null, 'required', null, 'client');

            $mform->addElement('textarea', 'data', get_string('userlist', 'grouptool'),
                    array('wrap' => 'virtual',
                          'rows' => '20',
                          'cols' => '50'));
            $mform->addHelpButton('data', 'userlist', 'grouptool');
            $mform->addRule('data', null, 'required', null, 'client');
            $mform->addRule('data', null, 'required', null, 'server');

            $mform->addElement('advcheckbox', 'forceregistration', '', '&nbsp;'.get_string('forceregistration', 'grouptool'));
            $mform->addHelpButton('forceregistration', 'forceregistration', 'grouptool');
            if ($forceimportreg = get_config('mod_grouptool', 'force_importreg')) {
                $mform->setDefault('forceregistration', $forceimportreg);
            }

            $mform->addElement('advcheckbox', 'includedeleted', '', get_string('includedeleted', 'grouptool'));
            $mform->addHelpButton('includedeleted', 'includedeleted', 'grouptool');
            $mform->setAdvanced('includedeleted');
            $mform->setDefault('includedeleted', 0);

            $mform->addElement('submit', 'submitbutton', get_string('importbutton', 'grouptool'));
        }
    }

    /**
     * Validation for import form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['group']) || ($data['group'] == 'none')) {
            $errors['group'] = get_string('choose_group', 'grouptool');
        }
        return $errors;
    }
}

class mod_grouptool_group_rename_form extends moodleform {
    /**
     * Definition of rename form
     *
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     */
    protected function definition() {

        global $CFG, $PAGE, $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'instance');
        $mform->setDefault('instance', $this->_customdata['instance']);
        $mform->setType('instance', PARAM_INT);

        $context = context_module::instance($this->_customdata['instance']);
        $cm = get_coursemodule_from_instance('grouptool', $this->_customdata['instance']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $this->course = $course;
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $coursecontext = context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'rename');
        $mform->setType('rename', PARAM_INT);
        $mform->setDefault('rename', $this->_customdata['rename']);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $grp = array();
        $grp[] = $mform->createElement('submit', 'submit', get_string('savechanges'));
        $grp[] = $mform->createElement('cancel');
        $mform->addGroup($grp, 'actionbuttons', '', array(' '), false);
        $mform->setType('actionbuttons', PARAM_RAW);

    }

    /**
     * Validation for rename form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = parent::validation($data, $files);
        if (empty($data['name'])) {
            $errors['name'] = get_string('choose_group', 'grouptool');
        } else {
            $group = groups_get_group_by_name($this->course->id, $data['name']);
            $group = $DB->get_record('groups', array('id' => $group));
            if (!empty($group) && ($group->id != $data['rename'])) {
                $errors['name'] = get_string('groupnameexists', 'group', $data['name']);
            }
        }

        return $errors;
    }
}

class mod_grouptool_group_resize_form extends moodleform {
    /**
     * Definition of resize form
     *
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     */
    protected function definition() {

        global $CFG, $PAGE, $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'instance');
        $mform->setDefault('instance', $this->_customdata['instance']);
        $mform->setType('instance', PARAM_INT);

        $context = context_module::instance($this->_customdata['instance']);
        $cm = get_coursemodule_from_instance('grouptool', $this->_customdata['instance']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $this->course = $course;
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $group = $DB->get_record('groups', array('id' => $this->_customdata['resize']));
        $coursecontext = context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'resize');
        $mform->setType('resize', PARAM_INT);
        $mform->setDefault('resize', $this->_customdata['resize']);

        $mform->addElement('hidden', 'name');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $group->name);

        $mform->addElement('text', 'size', get_string('size'));
        $mform->setType('size', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $grp = array();
        $grp[] = $mform->createElement('submit', 'submit', get_string('savechanges'));
        $grp[] = $mform->createElement('cancel');
        $mform->addGroup($grp, 'actionbuttons', '', array(' '), false);
        $mform->setType('actionbuttons', PARAM_RAW);

    }

    /**
     * Validation for resize form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = parent::validation($data, $files);
        $sql = '
   SELECT COUNT(reg.id) regcnt
     FROM {grouptool_agrps} agrps
LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrps.id AND reg.modified_by >= 0
    WHERE agrps.grouptoolid = :grouptoolid AND agrps.groupid = :groupid';
        $params = array('grouptoolid' => $data['instance'], 'groupid' => $data['resize']);
        $regs = $DB->count_records_sql($sql, $params);
        if ((clean_param($data['size'], PARAM_INT) <= 0)) {
                $errors['size'] = get_string('grpsizezeroerror', 'grouptool');
        } else if (!empty($regs) && $data['size'] < $regs) {
            $errors['size'] = get_string('toomanyregs', 'grouptool');
        } else {
            $DB->set_field('grouptool_agrps', 'grpsize', $data['size'],
                           array('groupid' => $data['resize'], 'grouptoolid' => $data['instance']));
            if ($data['size'] != $DB->get_field('grouptool_agrps', 'grpsize', array('groupid'     => $data['resize'],
                                                                                    'grouptoolid' => $data['instance']))) {
                // Error happened...
                $errors['size'] = get_string('couldnt_resize_group', 'grouptool', $data['size']);
            }
        }

        return $errors;
    }
}

/**
 * class containing most of the logic used in grouptool-module
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool {
    /** @var object */
    private $cm;
    /** @var object */
    private $course;
    /** @var object */
    private $grouptool;
    /** @var string */
    private $strgrouptool;
    /** @var string */
    private $strgrouptools;
    /** @var string */
    private $strlastmodified;
    /** @var string */
    private $pagetitle;
    /** @var bool */
    private $usehtmleditor;
    /** @var not really used, stores return from editors_get_preferred_format() */
    private $defaultformat;
    /** @var object instance's context record */
    private $context;

    const FILTER_ALL = 0;
    const FILTER_ACTIVE = 1;
    const FILTER_INACTIVE = 2;

    /**
     * Constructor for the grouptool class
     *
     * If cmid is set create the cm, course, checkmark objects.
     *
     * @global object $DB
     * @param int $cmid the current course module id - not set for new grouptools
     * @param object $grouptool usually null, but if we have it we pass it to save db access
     * @param object $cm usually null, but if we have it we pass it to save db access
     * @param object $course usually null, but if we have it we pass it to save db access
     */
    public function __construct($cmid, $grouptool=null, $cm=null, $course=null) {
        global $DB;

        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }

        global $CFG;

        if (!empty($cm)) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('grouptool', $cmid)) {
            print_error('invalidcoursemodule');
        }
        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if (! $this->course = $DB->get_record('course', array('id' => $this->cm->course))) {
            print_error('invalidid', 'grouptool');
        }

        if ($grouptool) {
            $this->grouptool = $grouptool;
        } else if (! $this->grouptool = $DB->get_record('grouptool',
                                                        array('id' => $this->cm->instance))) {
            print_error('invalidid', 'grouptool');
        }

        $this->grouptool->cmidnumber = $this->cm->idnumber;
        $this->grouptool->course   = $this->course->id;

        $this->strgrouptool = get_string('modulename', 'grouptool');
        $this->strgrouptools = get_string('modulenameplural', 'grouptool');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strgrouptool.': '.
                                      format_string($this->grouptool->name, true));

        /*
         * visibility handled by require_login() with $cm parameter
         * get current group only when really needed
         */

        // Set up things for a HTML editor if it's needed!
        $this->defaultformat = editors_get_preferred_format();
    }

    public function get_name() {
        return $this->grouptool->name;
    }

    /**
     * Print a message along with button choices for Continue/Cancel
     *
     * If a string or moodle_url is given instead of a single_button, method defaults to post.
     * If cancel=null only continue button is displayed!
     *
     * @global object $OUTPUT
     * @param string $message The question to ask the user
     * @param single_button|moodle_url|string $continue The single_button component representing the
     *                                                  Continue answer. Can also be a moodle_url
     *                                                  or string URL
     * @param single_button|moodle_url|string $cancel   The single_button component representing the
     *                                                  Cancel answer. Can also be a moodle_url or
     *                                                  string URL
     * @return string HTML fragment
     */
    public function confirm($message, $continue, $cancel = null) {
        global $OUTPUT;
        if (!($continue instanceof single_button)) {
            if (is_string($continue)) {
                $url = new moodle_url($continue);
                $continue = new single_button($url, get_string('continue'), 'post');
            } else if ($continue instanceof moodle_url) {
                $continue = new single_button($continue, get_string('continue'), 'post');
            } else {
                throw new coding_exception('The continue param to grouptool::confirm() must be either a'.
                                           ' URL (string/moodle_url) or a single_button instance.');
            }
        }

        if (!($cancel instanceof single_button)) {
            if (is_string($cancel)) {
                $cancel = new single_button(new moodle_url($cancel), get_string('cancel'), 'get');
            } else if ($cancel instanceof moodle_url) {
                $cancel = new single_button($cancel, get_string('cancel'), 'get');
            } else if ($cancel == null) {
                $cancel = null;
            } else {
                throw new coding_exception('The cancel param to grouptool::confirm() must be either a'.
                                           ' URL (string/moodle_url), single_button instance or null.');
            }
        }

        $output = $OUTPUT->box_start('generalbox', 'notice');
        $output .= html_writer::tag('p', $message);
        $cancel = ($cancel != null) ? $OUTPUT->render($cancel) : "";
        $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel,
                                    array('class' => 'buttons'));
        $output .= $OUTPUT->box_end();
        return $output;
    }

    /**
     * Parse a group name for characters to replace
     *
     * @param string $namescheme The scheme used for building group names
     * @param int $groupnumber The number of the group to be used in the parsed format string
     * @param object|array $members optional object or array of objects containing data of members
     *                              for the tags to be replaced with
     * @return string the parsed format string
     */
    private function groups_parse_name($namescheme, $groupnumber, $members = null, $digits = 0) {

        $tags = array('firstname', 'lastname', 'idnumber', 'username');
        $pregsearch = "#\[(".implode("|", $tags).")\]#";
        if (preg_match($pregsearch, $namescheme) > 0) {
            if ($members != null) {
                $data = array();
                if (is_array($members)) {
                    foreach ($tags as $key => $tag) {
                        foreach ($members as $member) {
                            if (!empty($member->$tag)) {
                                if (isset($data[$key]) && $data[$key] != "") {
                                    $data[$key] .= "-";
                                } else if (!isset($data[$key])) {
                                    $data[$key] = "";
                                }

                                $data[$key] .= substr($member->$tag, 0, 3);
                            }
                        }
                        if (empty($data[$key])) {
                            $data[$key] = "no".$tag."#";
                        }
                    }
                } else {
                    foreach ($tags as $key => $tag) {

                        if (!empty($members->$tag)) {
                            $data[$key] = $members->$tag;
                        } else {
                            $data[$key] = "no".$tag."#";
                        }
                    }
                }
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[".$tag."]";
                }
                $namescheme = str_replace($tags, $data, $namescheme);
            } else {
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[".$tag."]";
                }
                $namescheme = str_replace($tags, "", $namescheme);
            }
        }

        if (strstr($namescheme, '@') !== false) { // Convert $groupnumber to a character series!
            if ($groupnumber > GROUPTOOL_BEP) {
                $nexttempnumber = $groupnumber;
                $string = "";
                $orda = ord('A');
                $ordz = ord('Z');
                $return = "";
                do {
                    $tempnumber = $nexttempnumber;
                    $mod = ($tempnumber) % ($ordz - $orda + 1);
                    $letter = chr($orda + $mod);
                    $string .= $letter;
                    $nexttempnumber = floor(($tempnumber) / ($ordz - $orda + 1)) - 1;
                } while ($tempnumber >= ($ordz - $orda + 1));

                $namescheme = str_replace('@', strrev($string), $namescheme);
            } else {
                $letter = 'A';
                for ($i = 0; $i < $groupnumber; $i++) {
                    $letter++;
                }
                $namescheme = str_replace('@', $letter, $namescheme);
            }

        }

        if (strstr($namescheme, '#') !== false) {
            if ($digits != 0) {
                $format = '%0'.$digits.'d';
            } else {
                $format = '%d';
            }
            $namescheme = str_replace('#', sprintf($format, $groupnumber + 1), $namescheme);
        }
        return $namescheme;
    }

    /**
     * Update active group settings for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @param object $grouplist List of groups as returned by sortlist-Element
     * @param int $grouptoolid optinoal ID of the instance to update for
     * @return true if successfull
     */
    private function update_active_groups($grouplist, $grouptoolid = null) {
        global $DB, $PAGE;

        require_capability('mod/grouptool:create_groups', $this->context);
        if ($grouptoolid == null) {
            $grouptoolid = $this->grouptool->id;
        }

        if (!empty($grouplist) && is_array($grouplist)) {
            $agrpids = $DB->get_records('grouptool_agrps', array('grouptoolid' => $grouptoolid),
                                        '', 'groupid, id');
            // Update grouptools additional group-data!
            foreach ($grouplist as $groupid => $groupdata) {
                $dataobj = new stdClass();
                $dataobj->grouptoolid = $grouptoolid;
                $dataobj->groupid = $groupid;
                $dataobj->sort_order = $groupdata['sort_order'];
                if (isset($groupdata['grpsize'])) {
                    $dataobj->grpsize = $groupdata['grpsize'];
                }
                $dataobj->active = $groupdata['active'];
                if (key_exists($groupid, $agrpids)) {
                    $dataobj->id = $agrpids[$groupid]->id;
                    $DB->update_record('grouptool_agrps', $dataobj);
                } else {
                    $dataobj->id = $DB->insert_record('grouptool_agrps', $dataobj, true);
                }
            }

            grouptool_update_queues($this->grouptool);

            /* Trigger event */
            \mod_grouptool\event\agrps_updated::create_convenient($this->cm, $this->grouptool)->trigger();
        }
        return true;
    }

    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param object $data data from administration-form with all settings for group creation
     * @param array $users which users to registrate in the created groups
     * @param int $userpergrp how many users should be registrated per group
     * @param int $numgrps how many groups should be created
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_groups($data, $users, $userpergrp, $numgrps, $previewonly = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        $namestouse = array();

        // Allocate members from the selected role to groups!
        $usercnt = count($users);
        switch ($data->allocateby) {
            case 'no':
            case 'random':
            case 'lastname':
                $orderby = 'lastname, firstname';
                break;
            case 'firstname':
                $orderby = 'firstname, lastname';
                break;
            case 'idnumber':
                $orderby = 'idnumber';
                break;
            default:
                print_error('unknoworder');
        }

        if ($data->allocateby == 'random') {
            srand($data->seed);
            shuffle($users);
        }

        $groups = array();

        // Allocate the users - all groups equal count first!
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i] = array();
            $groups[$i]['members'] = array();
            if ($data->allocateby == 'no') {
                continue; // Do not allocate users!
            }
            for ($j = 0; $j < $userpergrp; $j++) {
                if (empty($users)) {
                    break 2;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Now distribute the rest!
        if ($data->allocateby != 'no') {
            for ($i = 0; $i < $numgrps; $i++) {
                if (empty($users)) {
                    break 1;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Every member is there, so we can parse the name!
        $digits = ceil(log10($numgrps));
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i]['name']    = $this->groups_parse_name(trim($data->namingscheme), $i,
                                                              $groups[$i]['members'], $digits);
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            if ($data->allocateby == 'no') {
                $table->head  = array(get_string('groupscount', 'group', $numgrps));
                $table->size  = array('100%');
                $table->align = array('left');
                $table->width = '40%';
            } else {
                $table->head  = array(get_string('groupscount', 'group', $numgrps),
                                      get_string('groupmembers', 'group'),
                                      get_string('usercounttotal', 'group', $usercnt));
                $table->size  = array('20%', '70%', '10%');
                $table->align = array('left', 'left', 'center');
                $table->width = '90%';
            }
            $table->data  = array();

            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $namestouse)) {
                    $error = true;
                    if (in_array($group['name'], $namestouse)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
                    }
                } else {
                    $line[] = $group['name'];
                    $namestouse[] = $group['name'];
                }
                if ($data->allocateby != 'no') {
                    $unames = array();
                    foreach ($group['members'] as $user) {
                        $unames[] = fullname($user);
                    }
                    $line[] = implode(', ', $unames);
                    $line[] = count($group['members']);
                }
                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = array();
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name     = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    \mod_grouptool\event\group_creation_started::create_groupamount($this->cm, $data->namingscheme,
                                                                                    $data->amount, $groupingid)->trigger();
                break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    \mod_grouptool\event\group_creation_started::create_memberamount($this->cm, $data->namingscheme,
                                                                                     $data->amount, $groupingid)->trigger();
                break;
            }

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (@groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    continue;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group['name'];
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $newagrp = new stdClass();
                $newagrp->groupid = $groupid;
                $newagrp->grouptoolid = $this->grouptool->id;
                $newagrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $newagrp->active = 1;
                } else {
                    $newagrp->active = 0;
                }
                $attr = array('grouptoolid' => $this->grouptool->id,
                              'groupid'     => $groupid);
                if (!$DB->record_exists('grouptool_agrps', $attr)) {
                    $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $newagrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id' => $newagrp->id));
                    }
                }
                $createdgroups[] = $groupid;
                foreach ($group['members'] as $user) {
                    groups_add_member($groupid, $user->id);
                    $usrreg = new stdClass();
                    $usrreg->userid = $user->id;
                    $usrreg->agrpid = $newagrp->id;
                    $usrreg->timestamp = time();
                    $usrreg->modified_by = $USER->id;
                    $attr = array('userid' => $user->id,
                                  'agrpid' => $newagrp->id);
                    if (!$DB->record_exists('grouptool_registered', $attr)) {
                        $DB->insert_record('grouptool_registered', $usrreg);
                    } else {
                        $DB->set_field('grouptool_registered', 'modified_by', $USER->id, $attr);
                    }
                }
                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                $event = \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme, $numgrps,
                                                                                  $groupingid)->trigger();
            }
        }
        if (empty($failed)) {
            $preview = get_string('groups_created', 'grouptool');
        } else if (empty($preview)) {
            if (!empty($error)) {
                $preview = $error;
            } else {
                $preview = get_string('group_creation_failed', 'grouptool');
            }
        }

        return array($failed, $preview);
    }

    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param object $data data from administration-form with all settings for group creation
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_fromto_groups($data, $previewonly = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        $groups = array();

        // Every member is there, so we can parse the name!
        for ($i = clean_param($data->from, PARAM_INT); $i <= clean_param($data->to, PARAM_INT); $i++) {
            $groups[] = $this->groups_parse_name(trim($data->namingscheme), $i - 1, null,
                                                 clean_param($data->digits, PARAM_INT));
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head  = array(get_string('groupscount', 'group',
                                  (clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1)));
            $table->size  = array('100%');
            $table->align = array('left');
            $table->width = '40%';

            $table->data  = array();
            $createdgroups = array();
            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group) || in_array($group, $createdgroups)) {
                    $error = true;
                    if (in_array($group, $createdgroups)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group).'</span>';
                    }
                } else {
                    $line[] = $group;
                    $createdgroups[] = $group;
                }

                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = array();
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name     = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group creation started event.
            $groupingid = !empty($grouping->id) ? $grouping->id : 0;
            \mod_grouptool\event\group_creation_started::create_fromto($this->cm, $data->namingscheme, $data->from,
                                                                       $data->to, $groupingid)->trigger();

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (groups_get_group_by_name($this->course->id, $group)) {
                    $error = get_string('groupnameexists', 'group', $group);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group;
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $newagrp = new stdClass();
                $newagrp->groupid = $groupid;
                $newagrp->grouptoolid = $this->grouptool->id;
                $newagrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $newagrp->active = 1;
                } else {
                    $newagrp->active = 0;
                }
                $attr = array('grouptoolid' => $this->grouptool->id,
                              'groupid'     => $groupid);
                if (!$DB->record_exists('grouptool_agrps', $attr)) {
                    $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $newagrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id' => $newagrp->id));
                    }
                }
                $createdgroups[] = $groupid;
                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
                return array(0 => $failed,
                             1 => get_string('group_creation_failed', 'grouptool').html_writer::empty_tag('br').$error);
            } else {
                $numgrps = clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1;
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme,
                                                                         $numgrps, $groupingid)->trigger();
                return array(0 => $failed, 1 => get_string('groups_created', 'grouptool'));
            }
        }
    }

    /**
     * Create a moodle group for each of the users in $users
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param array $users array of users-objects for which to create the groups
     * @param array $namescheme scheme determining how to name the created groups
     * @param int $grouping -1 => create new grouping,
     *                       0 => no grouping,
     *                      >0 => assign groups to grouping with that id
     * @param string $groupingname optional name for created grouping
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_one_person_groups($users, $namescheme = "[idnumber]", $grouping = 0,
                                              $groupingname = null, $previewonly = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        // Allocate members from the selected role to groups!
        $usercnt = count($users);

        // Prepare group data!
        $groups = array();
        $i = 0;
        $digits = ceil(log10(count($users)));
        foreach ($users as $user) {
            $groups[$i] = array();
            $groups[$i]['name']   = $this->groups_parse_name(trim($namescheme), $i, $user,
                                                             $digits);
            $groups[$i]['member'] = $user;
            $i++;
        }

        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head  = array(get_string('groupscount', 'group', $usercnt),
                                  get_string('groupmembers', 'group'));
            $table->size  = array('30%', '70%');
            $table->align = array('left', 'left');
            $table->width = '90%';

            $table->data  = array();
            $groupnames = array();
            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group['name'])
                     || in_array($group['name'], $groupnames)) {
                    $error = true;
                    if (in_array($group['name'], $groupnames)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
                    }
                } else {
                    $groupnames[] = $group['name'];
                    $line[] = $group['name'];
                }
                $line[] = fullname($group['member']);

                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $newgrouping = null;
            $createdgrouping = null;
            $createdgroups = array();
            $failed = false;

            // Prepare grouping!
            if (!empty($grouping)) {
                if ($grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name     = trim($groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            \mod_grouptool\event\group_creation_started::create_person($this->cm, $namescheme, $groupingid)->trigger();

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group['name'];
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $newagrp = new stdClass();
                $newagrp->groupid = $groupid;
                $newagrp->grouptoolid = $this->grouptool->id;
                $newagrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $newagrp->active = 1;
                } else {
                    $newagrp->active = 0;
                }
                if (!$DB->record_exists('grouptool_agrps',
                                        array('grouptoolid' => $this->grouptool->id,
                                              'groupid'     => $groupid))) {
                    $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $newagrp->id = $DB->get_field('grouptool_agrps', 'id',
                                                   array('grouptoolid' => $this->grouptool->id,
                                                         'groupid'     => $groupid));
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id' => $newagrp->id));
                    }
                }
                $createdgroups[] = $groupid;
                groups_add_member($groupid, $group['member']->id);
                $usrreg = new stdClass();
                $usrreg->userid = $group['member']->id;
                $usrreg->agrpid = $newagrp->id;
                $usrreg->timestamp = time();
                $usrreg->modified_by = $USER->id;
                $attr = array('userid' => $group['member']->id,
                              'agrpid' => $newagrp->id);
                if (!$DB->record_exists('grouptool_registered', $attr)) {
                    $DB->insert_record('grouptool_registered', $usrreg);
                } else {
                    $DB->set_field('grouptool_registered', 'modified_by', $USER->id, $attr);
                }

                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
                return array(0 => $failed,
                             1 => get_string('group_creation_failed', 'grouptool').html_writer::empty_tag('br').$error);
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $namescheme,
                                                                         count($groups), $groupingid)->trigger();
                return array(0 => $failed, 1 => get_string('groups_created', 'grouptool'));
            }
        }
    }

    /**
     * Create a grouping for each selected groupmoodle-groups
     *
     * Uses $SESSION->grouptool->view_administration->use_all to determin if groupings for all
     * or just selected groups should be created and also uses
     * $SESSION->grouptool->view_administration->grouplist[$group->id]['active']
     * to determin which groups have been selected
     *
     * @global object $SESSION
     * @global object $PAGE
     * @global object $OUTPUT
     * @param int $courseid optional id of course to create for
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_group_groupings($courseid = null, $previewonly = false) {
        global $SESSION, $PAGE, $OUTPUT;

        require_capability('mod/grouptool:create_groupings', $this->context);

        // Create groupings!
        $created = array();
        $error = false;
        $return = "";

        $table = new html_table();
        $table->attributes['class'] = 'centeredblock';
        $table->head = array(new html_table_cell(get_string('grouping', 'group')),
                                   new html_table_cell(get_string('info').'/'.
                                                       get_string('groups')));

        // Get all course-groups!
        if ($courseid == null) {
            if (isset($this->course->id)) {
                $courseid = $this->course->id;
            } else {
                print_error('coursemisconf');
            }
        }
        $groups = groups_get_all_groups($courseid);
        $ids = array();
        foreach ($groups as $group) {
            $row = array(new html_table_cell($group->name));
            $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
            if (empty($SESSION->grouptool->view_administration->use_all)
                     && !$active) {
                continue;
            }
            $groupid = $group->id;
            if (groups_get_grouping_by_name($courseid, $group->name)) {
                // Creation of grouping failed!
                if ($previewonly) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                $row[] = $cell;
                $error = true;
            } else {
                $ids[] = $group->id;
                $groupingid = groups_create_grouping($group);
                if ($groupingid) {
                    if (!groups_assign_grouping($groupingid, $groupid)) {
                        if ($previewonly) {
                            $text = get_string('group_assign_error_prev', 'grouptool');
                        } else {
                            $text = get_string('group_assign_error', 'grouptool');
                        }
                        $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                        $row[] = $cell;
                        $error = true;
                    } else {
                        if ($previewonly) {
                            $content = $group->name;
                        } else {
                            $content = $OUTPUT->notification(get_string('grouping_creation_success',
                                                                        'grouptool', $group->name),
                                                             'notifysuccess');
                        }
                        $cell = new html_table_cell($content);
                        $row[] = $cell;
                        $created[] = $groupingid;
                    }
                } else {
                    if ($previewonly) {
                        $text = get_string('grouping_creation_error_prev', 'grouptool');
                    } else {
                        $text = get_string('grouping_creation_error', 'grouptool');
                    }
                    $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                    $row[] = $cell;
                    $error = true;
                }
            }
            $table->data[] = new html_table_row($row);
            $return = html_writer::table($table);
        }
        if ($previewonly || ($error && !$previewonly)) { // Undo everything!
            foreach ($created as $groupingid) {
                $groupingsgroups = groups_get_all_groups($courseid, 0, $groupingid);
                foreach ($groupingsgroups as $group) {
                    groups_unassign_grouping($groupingid, $group->id);
                }
                groups_delete_grouping($groupingid);
            }
        } else if (!$previewonly) {
            // Trigger the event!
            \mod_grouptool\event\groupings_created::create_from_object($this->cm, $ids)->trigger();
        }
        return array(0 => $error, 1 => $return);
    }

    /**
     * Create a grouping for all selected moodle-groups
     *
     * Uses $SESSION->grouptool->view_administration->use_all to determin if groupings for all
     * or just selected groups should be created and also uses
     * $SESSION->grouptool->view_administration->grouplist[$group->id]['active']
     * to determin which groups have been selected
     *
     * @global object $SESSION
     * @global object $PAGE
     * @global object $OUTPUT
     * @param int $target -1 for new grouping or groupingid
     * @param string $name name for new grouping if $target = -1
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function update_grouping($target, $name = null, $previewonly = false) {
        global $SESSION, $PAGE, $OUTPUT;
        $error = false;
        $return = "";

        require_capability('mod/grouptool:create_groupings', $this->context);

        if (isset($this->course->id)) {
            $courseid = $this->course->id;
        } else {
            print_error('coursemisconf');
        }

        if ($target == -1) {
            if (groups_get_grouping_by_name($courseid, $name)) {
                // Creation of grouping failed!
                if ($previewonly) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                return array(0 => true, 1 => $OUTPUT->notification($text, 'notifyproblem'));
            } else {
                if (empty($previewonly)) {
                    // Create grouping and set as target.
                    $grouping = new stdClass();
                    $grouping->name = $name;
                    $grouping->courseid = $courseid;
                    $target = groups_create_grouping($grouping);
                    $return = $OUTPUT->notification(get_string('grouping_creation_only_success', 'grouptool'), 'notifysuccess');
                } else {
                    $return = $OUTPUT->notification(get_string('grouping_creation_only_success_prev', 'grouptool'),
                                                    'notifymessage');
                }
            }
        }

        if (!empty($target)) {
            $groups = groups_get_all_groups($courseid);
            $ids = array();
            $success = array();
            $failure = array();
            foreach ($groups as $group) {
                $row = array(new html_table_cell($group->name));
                $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
                if (empty($SESSION->grouptool->view_administration->use_all)
                         && !$active) {
                    continue;
                }
                $groupid = $group->id;

                if (!groups_assign_grouping($target, $groupid)) {
                    $failure[] = $group->name;
                    $error = true;
                } else {
                    $success[] = $group->name;
                }
            }
            if ($previewonly) {
                if (!empty($success)) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_success_prev', 'grouptool').
                                                     html_writer::empty_tag('br').
                                                     implode(', ', $success), 'notifymessage');
                }
                if ($error) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_error_prev', 'grouptool').
                                                     html_writer::empty_tag('br').
                                                     implode(', ', $failure), 'notifyproblem');
                }
            } else {
                $return .= $OUTPUT->notification(get_string('grouping_assign_success', 'grouptool').
                                                 html_writer::empty_tag('br').
                                                 implode(', ', $success), 'notifysuccess');
                if ($error) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_error', 'grouptool').
                                                     html_writer::empty_tag('br').
                                                     implode(', ', $failure), 'notifyproblem');
                }
            }
        }
        if (!$previewonly) {
            // Trigger the event!
            \mod_grouptool\event\groupings_created::create_from_object($this->cm, $ids)->trigger();
        }
        return array(0 => $error, 1 => $return);
    }

    /**
     * Outputs the content of the administration tab and manages actions taken in this tab
     *
     * @global object $SESSION
     * @global object $OUTPUT
     * @global object $PAGE
     */
    public function view_administration() {
        global $SESSION, $OUTPUT, $PAGE, $DB, $USER, $CFG;

        $output = $PAGE->get_renderer('mod_grouptool');

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = array();
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }

        $filter = optional_param('filter', null, PARAM_INT);
        if ($filter !== null) {
            set_user_preference('mod_grouptool_group_filter', $filter, $USER->id);
        } else {
            $filter = get_user_preferences('mod_grouptool_group_filter', self::FILTER_ACTIVE, $USER->id);
        }

        $inactivetabs = array();

        $filtertabs['active'] = new tabobject(self::FILTER_ACTIVE,
                                              $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                              '&amp;tab=group_admin&filter='.self::FILTER_ACTIVE,
                                              get_string('active', 'grouptool'),
                                              '',
                                              false);
        $filtertabs['inactive'] = new tabobject(self::FILTER_INACTIVE,
                                                $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                                '&amp;tab=group_admin&filter='.self::FILTER_INACTIVE,
                                                get_string('inactive'),
                                                '',
                                                false);
        $filtertabs['all'] = new tabobject(self::FILTER_ALL,
                                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                           '&amp;tab=group_admin&filter='.self::FILTER_ALL,
                                           get_string('all'),
                                           '',
                                           false);
        echo html_writer::tag('div', $OUTPUT->tabtree($filtertabs, $filter, $inactivetabs),
                              array('id' => 'filtertabs'));

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroupings)) {
                require_capability('mod/grouptool:create_groupings', $this->context);
                $target = required_param('target', PARAM_INT);
                switch ($target) { // ...grpg_target | grpg_groupingname | use_all (0 sel | 1 all).
                    case 0: // Invalid - no action! TODO Add message!
                        $error = true;
                        $preview = '';
                        break;
                    case -2: // One grouping per group!
                        list($error, $preview) = $this->create_group_groupings();
                        break;
                    case -1: // One new grouping for all!
                        list($error, $preview) = $this->update_grouping($target, required_param('name', PARAM_ALPHANUMEXT));
                        break;
                    default:
                        list($error, $preview) = $this->update_grouping($target);
                        break;
                }
                $preview = html_writer::tag('div', $preview, array('class' => 'centered'));
                echo $OUTPUT->box($preview, 'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        if ($rename = optional_param('rename', 0, PARAM_INT)) {
            // Show Rename Form!
            $gform = new mod_grouptool_group_rename_form(null, array('id'       => $this->cm->id,
                                                                     'instance' => $this->cm->instance,
                                                                     'rename'   => $rename));
            if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
                $group = new stdClass();
                $group->id = $fromform->rename;
                $group->name = $fromform->name;
                $group->courseid = $fromform->courseid;
                groups_update_group($group);
            } else if (!$gform->is_cancelled()) {
                $data = new stdClass();
                $data->name = $DB->get_field('groups', 'name', array('id' => $rename));
                $gform->set_data($data);
                $gform->display();
                echo $OUTPUT->footer();
                die;
            }
        }

        if ($resize = optional_param('resize', 0, PARAM_INT)) {
            // Show Rename Form!
            $gform = new mod_grouptool_group_resize_form(null, array('id'       => $this->cm->id,
                                                                     'instance' => $this->cm->instance,
                                                                     'resize'   => $resize));
            if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
                if (empty($fromform->size)) {
                    $DB->set_field('grouptool_agrps', 'grpsize', null, array('groupid'     => $fromform->resize,
                                                                             'grouptoolid' => $this->cm->id));
                } else {
                    $group = new stdClass();
                    $group->id = $DB->get_field('grouptool_agrps', 'id', array('groupid' => $fromform->resize,
                                                                               'grouptoolid' => $this->cm->instance));
                    $group->grpsize = $fromform->size;
                    $DB->update_record('grouptool_agrps', $group);
                }
            } else if (!$gform->is_cancelled()) {
                $data = new stdClass();
                $data->size = $DB->get_field('grouptool_agrps', 'grpsize', array('groupid'     => $resize,
                                                                                 'grouptoolid' => $this->cm->instance));
                $gform->set_data($data);
                $gform->display();
                echo $OUTPUT->footer();
                die;
            }
        }

        if ($delete = optional_param('delete', 0, PARAM_INT)) {
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                // Show Confirm!
                $cancel = new moodle_url($PAGE->url);
                $continue = new moodle_url($cancel, array('confirm' => 1,
                                                          'delete'  => $delete));
                $cancel = new single_button($cancel, get_string('no'), 'post');
                $continue = new single_button($continue,
                                              get_string('yes'), 'post');
                $confirmtext = get_string('confirm_delete', 'grouptool');
                echo $this->confirm($confirmtext, $continue, $cancel);
                echo $OUTPUT->footer();
                die;
            } else {
                // Delete it!
                groups_delete_group($delete);
            }
        }

        if ($toggle = optional_param('toggle', 0, PARAM_INT)) {
            if (!empty($toggle)) {
                $conditions = array('grouptoolid' => $this->cm->instance, 'groupid' => $toggle);
                if (!$DB->record_exists('grouptool_agrps', $conditions)) {
                    echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_found', 'grouptool'), 'notifyproblem',
                                                            $conditions), 'generalbox');
                } else {
                    $record = $DB->get_record('grouptool_agrps', $conditions);
                    if (!empty($record->active)) {
                        $DB->set_field('grouptool_agrps', 'active', 0, $conditions);
                    } else {
                        $DB->set_field('grouptool_agrps', 'active', 1, $conditions);
                    }
                }
            }
        }

        if (($bulkaction = optional_param('bulkaction', null, PARAM_ALPHA))
            && ($selected = optional_param_array('selected', array(), PARAM_INT))
            && (optional_param('start_bulkaction', 0, PARAM_BOOL))) {
            switch ($bulkaction) {
                case 'activate':  // ...also via ajax bulk action?
                    // Activate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                        $DB->set_field_select("grouptool_agrps", "active", 1, " grouptoolid = ? AND groupid ".$grpsql,
                                              array_merge(array($this->cm->instance), $grpparams));
                    }
                    echo $OUTPUT->notification(get_string('activated_groups', 'grouptool'), 'notifysuccess');
                    $continue = new moodle_url($PAGE->url, array('tab' => 'group_admin'));
                    echo $this->confirm('', $continue);
                break;
                case 'deactivate':  // ...also via ajax bulk action?
                    // Deactivate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                        $DB->set_field_select("grouptool_agrps", "active", 0, " grouptoolid = ? AND groupid ".$grpsql,
                                              array_merge(array($this->cm->instance), $grpparams));
                    }
                    echo $OUTPUT->notification(get_string('deactivated_groups', 'grouptool'), 'notifysuccess');
                    $continue = new moodle_url($PAGE->url, array('tab' => 'group_admin'));
                    echo $this->confirm('', $continue);
                break;
                case 'delete': // ...also via ajax bulk action?
                    // Show confirmation dialogue!
                    if (optional_param('confirm', 0, PARAM_BOOL)) {
                        $groups = optional_param_array('selected', null, PARAM_INT);
                        $groups = $DB->get_records_list('groups', 'id', $groups);
                        foreach ($groups as $group) {
                            groups_delete_group($group);
                        }
                        echo $OUTPUT->notification(get_string('successfully_deleted_groups', 'grouptool'), 'notifysuccess');
                        $continue = new moodle_url($PAGE->url, array('tab' => 'group_admin'));
                        echo $this->confirm('', $continue);
                    } else {
                        $cancel = new moodle_url($PAGE->url, array('tab' => 'group_admin'));
                        $params = array('confirm' => 1, 'bulkaction' => 'delete', 'start_bulkaction' => 1);
                        $text = get_string('confirm_delete', 'grouptool').html_writer::start_tag('ul');
                        $groups = $DB->get_records_list('groups', 'id', $selected);
                        foreach ($selected as $select) {
                            $params['selected['.$select.']'] = $select;
                            $text .= html_writer::tag('li', $groups[$select]->name);
                        }
                        $text .= html_writer::end_tag('ul');
                        $continue = new moodle_url($cancel, $params);

                        echo $this->confirm($text, $continue, $cancel);
                        echo $OUTPUT->footer();
                        die;
                    }
                break;
                case 'grouping':
                    // Show grouping creation form!
                    $selected = optional_param_array('selected', array(), PARAM_INT);
                    $mform = new mod_grouptool_groupings_creation_form(null,
                                                                       array('id'       => $id,
                                                                             'selected' => $selected));
                    $groups = $DB->get_records_list('groups', 'id', $selected);
                    if ($mform->is_cancelled()) {
                        $bulkaction = null;
                        $selected = array();
                    } else if ($fromform = $mform->get_data()) {
                        // Some groupings should be created...
                        if ($fromform->target == -2) { // One new grouping per group!
                            foreach ($groups as $group) {
                                $grouping = new stdClass();
                                if (!$grouping->id = groups_get_grouping_by_name($this->course->id, $group->name)) {
                                    $grouping = new stdClass();
                                    $grouping->courseid = $this->course->id;
                                    $grouping->name     = $group->name;
                                    $grouping->id = groups_create_grouping($grouping);
                                }
                                // Insert group!
                                groups_assign_grouping($grouping->id, $group->id);
                            }
                        } else if ($fromform->target == -1) { // One new grouping!
                            // Create grouping if it doesn't exist...
                            $grouping = new stdClass();
                            if (!$grouping->id = groups_get_grouping_by_name($this->course->id, $fromform->name)) {
                                $grouping = new stdClass();
                                $grouping->courseid = $this->course->id;
                                $grouping->name     = trim($fromform->name);
                                $grouping->id = groups_create_grouping($grouping);
                            }
                            // Insert groups!
                            foreach ($groups as $group) {
                                groups_assign_grouping($grouping->id, $group->id);
                            }
                        } else if ($fromform->target > 0) { // Existing Grouping!
                            $grouping = groups_get_grouping($fromform->target);
                            if ($grouping) {
                                foreach ($groups as $group) {
                                    groups_assign_grouping($grouping->id, $group->id);
                                }
                            }
                        }
                        // ...redirect to show sortlist again!
                        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $this->cm->id,
                                                                               'tab' => 'group_admin',
                                                                               'filter' => $filter));
                        $text = $OUTPUT->notification(get_string('groupings_created_and_groups_added', 'grouptool'),
                                                      'notifymessage');
                        echo $this->confirm($text, $url);
                    } else {
                        $mform->display();
                    }
                break;
            }
        }
        if (!$bulkaction || !$selected || !optional_param('start_bulkaction', 0, PARAM_BOOL)) {
            // Show form!
            $formaction = new moodle_url('/mod/grouptool/view.php', array('id' => $this->cm->id,
                                                                          'tab' => 'group_admin',
                                                                          'filter' => $filter));
            $mform = new MoodleQuickForm('bulk', 'post', $formaction, '');

            $mform->addElement('hidden', 'sesskey');
            $mform->setDefault('sesskey', sesskey());

            $sortlist = new sortlist($this->course->id, $this->cm, $filter);
            $sortlistcontroller = new sortlist_controller($sortlist);
            $mform->addElement('html', $output->render($sortlistcontroller));
            $mform->addElement('html', $output->render($sortlist));

            $actions = array(
                '' => get_string('choose', 'grouptool'),
                'activate' => get_string('setactive', 'grouptool'),
                'deactivate' => get_string('setinactive', 'grouptool'),
                'delete' => get_string('delete'),
                'grouping' => get_string('createinsertgrouping', 'grouptool'),
            );

            $grp = array();
            $grp[] =& $mform->createElement('static', 'with_selection', '', get_string('with_selection', 'grouptool'));
            $grp[] =& $mform->createElement('select', 'bulkaction', '', $actions);
            $grp[] =& $mform->createElement('submit', 'start_bulkaction', get_string('start', 'grouptool'));
            $mform->addGroup($grp, 'actiongrp', '', ' ', false);
            $mform->disable_form_change_checker();

            $mform->display();

            switch ($filter) {
                case self::FILTER_ACTIVE:
                    $curfilter = 'active';
                break;
                case self::FILTER_INACTIVE:
                    $curfilter = 'inactive';
                break;
                case self::FILTER_ALL:
                    $curfilter = 'all';
                break;
            }
            $PAGE->requires->yui_module('moodle-mod_grouptool-administration',
                                        'M.mod_grouptool.init_administration',
                                        array(array('lang'       => current_language(),
                                                    'contextid'  => $this->context->id,
                                                    'filter'     => $curfilter,
                                                    'filterid'   => $filter,
                                                    'globalsize' => $this->grouptool->grpsize)));
            $PAGE->requires->strings_for_js(array('active', 'inactive', 'confirm_delete'), 'mod_grouptool');
            $PAGE->requires->string_for_js('ajax_edit_size_help', 'mod_grouptool');
            $PAGE->requires->strings_for_js(array('yes', 'no'), 'moodle');
        }
    }

    /**
     * Outputs the content of the creation tab and manages actions taken in this tab
     *
     * @global object $SESSION
     * @global object $OUTPUT
     * @global object $PAGE
     */
    public function view_creation() {
        global $SESSION, $OUTPUT, $PAGE, $DB;

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = array();
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroups)) {
                require_capability('mod/grouptool:create_groups', $this->context);
                // Create groups!
                $data = $SESSION->grouptool->view_administration;
                switch ($data->mode) {
                    case GROUPTOOL_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = $data->amount;
                        $userpergrp = floor($usercnt / $numgrps);
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = ceil($usercnt / $data->amount);
                        $userpergrp = $data->amount;
                        if (!empty($data->nosmallgroups) and $usercnt % $data->amount != 0) {
                            /*
                             *  If there would be one group with a small number of member
                             *  reduce the number of groups
                             */
                            $missing = $userpergrp * $numgrps - $usercnt;
                            if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                                // Spread the users from the last small group!
                                $numgrps--;
                                $userpergrp = floor($usercnt / $numgrps);
                            }
                        }
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_1_PERSON_GROUPS:
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid);
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $prev) = $this->create_one_person_groups($users,
                                                                              $data->namingscheme,
                                                                              $data->grouping,
                                                                              $data->groupingname);
                        $preview = $prev;
                        break;
                    case GROUPTOOL_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $preview) = $this->create_fromto_groups($data);
                        break;
                }
                $class = $error ? 'notifyproblem' : 'notifysuccess';
                $preview = $OUTPUT->notification($preview, $class);
                echo $OUTPUT->box(html_writer::tag('div', $preview, array('class' => 'centered')),
                                  'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        // Create the form-object!
        $mform = new mod_grouptool_grp_creation_form(null,
                                                     array('id'           => $id,
                                                           'roles'        => $rolenames,
                                                           'show_grpsize' => ($this->grouptool->use_size
                                                                              && $this->grouptool->use_individual)));

        if ($fromform = $mform->get_data()) {
            require_capability('mod/grouptool:create_groups', $this->context);
            // Save submitted data in session and show confirmation dialog!
            if (!isset($SESSION->grouptool)) {
                $SESSION->grouptool = new stdClass();
            }
            if (!isset($SESSION->grouptool->view_administration)) {
                $SESSION->grouptool->view_administration = new stdClass();
            }
            $SESSION->grouptool->view_administration = $fromform;
            $data = $SESSION->grouptool->view_administration;
            $preview = "";
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    switch ($data->allocateby) {
                        case 'no':
                        case 'random':
                        case 'lastname':
                            $orderby = 'lastname, firstname';
                            break;
                        case 'firstname':
                            $orderby = 'firstname, lastname';
                            break;
                        case 'idnumber':
                            $orderby = 'idnumber';
                            break;
                        default:
                            print_error('unknoworder');
                    }
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                          $data->cohortid, $orderby);
                    $usercnt = count($users);
                    $numgrps    = clean_param($data->amount, PARAM_INT);
                    $userpergrp = floor($usercnt / $numgrps);
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                  $numgrps, true);
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    switch ($data->allocateby) {
                        case 'no':
                        case 'random':
                        case 'lastname':
                            $orderby = 'lastname, firstname';
                            break;
                        case 'firstname':
                            $orderby = 'firstname, lastname';
                            break;
                        case 'idnumber':
                            $orderby = 'idnumber';
                            break;
                        default:
                            print_error('unknoworder');
                    }
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                          $data->cohortid, $orderby);
                    $usercnt = count($users);
                    $numgrps    = ceil($usercnt / $data->amount);
                    $userpergrp = clean_param($data->amount, PARAM_INT);
                    if (!empty($data->nosmallgroups) and $usercnt % clean_param($data->amount, PARAM_INT) != 0) {
                        /*
                         *  If there would be one group with a small number of member
                         *  reduce the number of groups
                         */
                        $missing = $userpergrp * $numgrps - $usercnt;
                        if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                            // Spread the users from the last small group!
                            $numgrps--;
                            $userpergrp = floor($usercnt / $numgrps);
                        }
                    }
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                  $numgrps, true);
                    break;
                case GROUPTOOL_1_PERSON_GROUPS:
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                          $data->cohortid);
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $prev) = $this->create_one_person_groups($users,
                                                                          $data->namingscheme,
                                                                          $data->grouping,
                                                                          $data->groupingname,
                                                                          true);
                    $preview = $prev;
                    break;
                case GROUPTOOL_FROMTO_GROUPS:
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $preview) = $this->create_fromto_groups($data, true);
                    break;
            }
            $preview = html_writer::tag('div', $preview, array('class' => 'centered'));
            $tab = required_param('tab', PARAM_ALPHANUMEXT);
            if ($error) {
                $text = get_string('create_groups_confirm_problem', 'grouptool');
                $url = new moodle_url("view.php?id=$id&tab=".$tab);
                $back = new single_button($url, get_string('back'), 'post');
                $confirmboxcontent = $this->confirm($text, $back);
            } else {
                $continue = "view.php?id=$id&tab=".$tab."&confirm=true";
                $cancel = "view.php?id=$id&tab=".$tab;
                $text = get_string('create_groups_confirm', 'grouptool');
                $confirmboxcontent = $this->confirm($text, $continue, $cancel);
            }
            echo $OUTPUT->heading(get_string('preview'), 2, 'centered').
                 $OUTPUT->box($preview, 'generalbox').
                 $confirmboxcontent;
        } else {
            $mform->display();
        }
    }

    /**
     * returns a checkboxcontroller (like in moodleform - just without form)
     *
     * @global object $CFG
     * @param int $groupid ID of checkboxgroup to control (--> checkbox has class "checkboxgroupX")
     * @param string $text optional text to output before submitlink
     * @param array $attributes optional array of attributes for submitlink
     * @param bool $originalvalue optional state of checkboxes @ first load
     * @return string HTML Fragment containing html table with necessary data/elements or message
     */
    public function add_checkbox_controller($groupid, $text = null, $attributes = null,
                                            $originalvalue = 0) {
        global $CFG;

        // Set the default text if none was specified!
        if (empty($text)) {
            $text = get_string('selectallornone', 'form');
        }

        $selectvalue = optional_param('checkbox_controller'.$groupid, null, PARAM_INT);

        if ($selectvalue == 0 || is_null($selectvalue)) {
            $newselectvalue = 1;
        } else {
            $newselectvalue = 0;
        }

        $attr = array('type'  => 'hidden',
                      'name'  => 'checkbox_controller'.$groupid,
                      'value' => $newselectvalue);
        $hiddenstate = html_writer::empty_tag('input', $attr);

        $checkboxcontrollername = 'nosubmit_checkbox_controller' . $groupid;

        // Prepare Javascript for submit element!
        $js = "\n//<![CDATA[\n";
        if (!defined('HTML_QUICKFORM_CHECKBOXCONTROLLER_EXISTS')) {
            $js .= <<<EOS
function html_quickform_toggle_checkboxes(group) {
    var checkboxes = document.getElementsByClassName('checkboxgroup' + group);
    var newvalue = false;
    var global = eval('html_quickform_checkboxgroup' + group + ';');
    if (global == 1) {
        eval('html_quickform_checkboxgroup' + group + ' = 0;');
        newvalue = '';
    } else {
        eval('html_quickform_checkboxgroup' + group + ' = 1;');
        newvalue = 'checked';
    }

    for (i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = newvalue;
    }
}
EOS;
            define('HTML_QUICKFORM_CHECKBOXCONTROLLER_EXISTS', true);
        }
        $js .= "\nvar html_quickform_checkboxgroup$groupid=$originalvalue;\n";

        $js .= "//]]>\n";

        require_once("$CFG->libdir/form/submitlink.php");
        $submitlink = new MoodleQuickForm_submitlink($checkboxcontrollername, $text, $attributes);
        $submitlink->_js = $js;
        $submitlink->_onclick = "html_quickform_toggle_checkboxes($groupid); return false;";
        return $hiddenstate."<div>".$submitlink->toHTML()."</div>";
    }

    /**
     * returns table used in group-grading form
     *
     * @global object $OUTPUT
     * @global object $USER
     * @global object $PAGE
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroupsonly limit source-grades to those given by current user
     * @param bool $incompleteonly show only groups which have not-graded members
     * @param int $filter GROUPTOOL_FILTER_ALL => all groups
     *                    GROUPTOOL_FILTER_NONCONFLICTING => groups with exactly 1 graded member
     *                    >0 => id of single group
     * @param array $selected array with ids of groups/users to copy grades to as keys
     *                        (depends on filter)
     * @param array $missingsource optional array with ids of entries for whom no source has been
     *                                      selected (just to display a clue to select a source)
     * @return string HTML Fragment containing checkbox-controller and dependencies
     */
    private function get_grading_table($activity, $mygroupsonly, $incompleteonly, $filter,
                                       $selected, $missingsource = array()) {
        global $OUTPUT, $USER, $PAGE;

        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroupsonly) {
            require_capability('mod/grouptool:grade', $this->context);
        } else if (!has_capability('mod/grouptool:grade', $this->context)) {
            /*
             * if he want's to grad his own he needs either capability to grade all
             * or to grade his own at least
             */
            require_capability('mod/grouptool:grade_own_submission', $this->context);
        }

        $grouping = optional_param('grouping', null, PARAM_INT);

        $table = new html_table();

        $title = html_writer::tag('div', get_string('groupselection', 'grouptool').
                                         $OUTPUT->help_icon('groupselection', 'grouptool'),
                                  array('class' => 'groupselectiontitle'));

        if ($activity == 0) {
            return $title.
                   $OUTPUT->box($OUTPUT->notification(get_string('chooseactivity', 'grouptool'),
                                                      'notifyproblem'), 'generalbox centered');
        }

        // General table settings!
        $table->attributes['class'] .= ' coloredrows grading_gradingtable';
        $tablepostfix = "";
        // Determin what mode we have to interpret the selected items the right way!
        if ($filter == GROUPTOOL_FILTER_ALL || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
            // Multiple groups?
            $tablecolumns = array('select',
                    'name',
                    'gradeinfo');
            $button = html_writer::tag('button', get_string('copy', 'grouptool'),
                                       array('name'  => 'copygrades',
                                             'type'  => 'submit',
                                             'value' => 'true'));
            $buttontext = get_string('copy_refgrades_feedback', 'grouptool');
            $tableheaders = array('',
                    get_string('name'),
                    get_string('reference_grade_feedback', 'grouptool'));

            $groups = groups_get_all_groups($this->course->id, 0, $grouping);
            $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);

            foreach ($groups as $group) {
                $error = "";
                $cells = array();
                $groupmembers = groups_get_members($group->id);
                // Get grading info for all groupmembers!
                $gradinginfo = grade_get_grades($this->course->id, 'mod', $cmtouse->modname,
                                                 $cmtouse->instance, array_keys($groupmembers));
                $gradeinfo = array();
                if (in_array($group->id, $missingsource)) {
                    $error = ' error';
                    $gradeinfo[] = html_writer::tag('div', get_string('missing_source_selection',
                                                                      'grouptool'));
                }

                $userwithgrades = array();
                foreach ($groupmembers as $key => $groupmember) {
                    if (!empty($gradinginfo->items[0]->grades[$groupmember->id]->dategraded)
                        && (!$mygroupsonly
                            || $gradinginfo->items[0]->grades[$groupmember->id]->usermodified == $USER->id)) {
                        $userwithgrades[] = $key;
                    }
                }
                if ((count($userwithgrades) != 1)
                        && ($filter == GROUPTOOL_FILTER_NONCONFLICTING)) {
                    /*
                     * skip groups with more than 1 grade and groups without grade
                     * if only nonconflicting should be reviewed
                     */
                    continue;
                }
                if ((count($userwithgrades) == count($groupmembers)) && ($incompleteonly == 1)) {
                    // Skip groups fully graded if it's wished!
                    continue;
                }
                foreach ($userwithgrades as $key) {
                    $finalgrade = $gradinginfo->items[0]->grades[$key];
                    if (!empty($finalgrade->dategraded)) {
                        $grademax = $gradinginfo->items[0]->grademax;
                        $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                                                        round($grademax, 2);
                        $radioattr = array(
                                'name' => 'source['.$group->id.']',
                                'value' => $groupmembers[$key]->id,
                                'type' => 'radio');
                        if (isset($source[$group->id])
                                && $source[$group->id] == $groupmembers[$key]->id) {
                            $radioattr['selected'] = 'selected';
                        }
                        if (count($userwithgrades) == 1) {
                            $radioattr['type'] = 'hidden';
                        }
                        $gradeinfocont = ((count($userwithgrades) >= 1) ?
                                            html_writer::empty_tag('input', $radioattr) : "").
                                            fullname($groupmembers[$key])." (".
                                            $finalgrade->formatted_grade;
                        if (strip_tags($finalgrade->str_feedback) != "") {
                            $gradeinfocont .= " ".
                                              shorten_text(strip_tags($finalgrade->str_feedback),
                                                           15);
                        }
                        $gradeinfocont .= ")";
                        $gradeinfo[] = html_writer::tag('div', $gradeinfocont,
                                                        array('class' => 'gradinginfo'.
                                                                         $groupmembers[$key]->id));
                    }
                }
                $selectattr = array(
                        'type' => 'checkbox',
                        'name' => 'selected[]',
                        'value' => $group->id,
                        'class' => 'checkboxgroup1');
                if ((count($groupmembers) <= 1) || count($userwithgrades) == 0) {
                    $selectattr['disabled'] = 'disabled';
                    unset($selectattr['checked']);
                } else if (isset($selected[$group->id]) && $selected[$group->id] == 1) {
                    $selectattr['checked'] = "checked";
                }
                $select = new html_table_cell(html_writer::empty_tag('input', $selectattr));
                $name = new html_table_cell($group->name);
                if (empty($gradeinfo)) {
                    $gradeinfo = new html_table_cell(get_string('no_grades_present', 'grouptool'));
                } else {
                    $gradeinfo = new html_table_cell(implode("\n", $gradeinfo));
                }

                $row = new html_table_row(array($select, $name, $gradeinfo));
                $row->attributes['class'] = isset($row->attributes['class']) ?
                                            $row->attributes['class'].$error :
                                            $row->attributes['class'];
                $data[] = $row;
            }
            $tablepostfix = html_writer::tag('div', $buttontext, array('class' => 'center centered'));
            $tablepostfix .= html_writer::tag('div', $button, array('class' => 'centered center'));

        } else if ($filter > 0) {    // Single group?
            $tablecolumns = array('select',
                    'fullname',
                    'idnumber',
                    'grade',
                    'feedback',
                    'copybutton');
            $tableheaders = array(get_string('target', 'grouptool'),
                    get_string('fullname'),
                    get_string('idnumber'),
                    get_string('grade'),
                    get_string('feedback'),
                    get_string('source', 'grouptool'));

            $groupmembers = groups_get_members($filter);
            // Get grading info for all groupmembers!
            $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);
            $gradinginfo = grade_get_grades($this->course->id, 'mod', $cmtouse->modname,
                                             $cmtouse->instance, array_keys($groupmembers));
            if (isset($gradinginfo->items[0])) {
                foreach ($groupmembers as $groupmember) {
                    $row = array();
                    $finalgrade = $gradinginfo->items[0]->grades[$groupmember->id];
                    $grademax = $gradinginfo->items[0]->grademax;
                    $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                                                    round($grademax, 2);
                    $checked = (isset($selected[$groupmember->id])
                                && ($selected[$groupmember->id] == 1)) ? true : false;
                    $row[] = html_writer::checkbox('selected[]', $groupmember->id, $checked, '',
                                                   array('class' => 'checkbox checkboxgroup1'));
                    $row[] = html_writer::tag('div', fullname($groupmember),
                                              array('class' => 'fullname'.$groupmember->id));
                    $row[] = html_writer::tag('div', $groupmember->idnumber,
                                              array('class' => 'idnumber'.$groupmember->id));
                    $row[] = html_writer::tag('div', $finalgrade->formatted_grade,
                                              array('class' => 'grade'.$groupmember->id));
                    $row[] = html_writer::tag('div',
                                              shorten_text(strip_tags($finalgrade->str_feedback),
                                                           15),
                                              array('class' => 'feedback'.$groupmember->id));
                    if ($mygroupsonly && ($finalgrade->usermodified != $USER->id)) {
                        $row[] = html_writer::tag('div', get_string('not_graded_by_me',
                                                                    'grouptool'));
                    } else {
                        if (GROUPTOOL_IE7_IS_DEAD) {
                            $row[] = html_writer::tag('button',
                                                      get_string('copygrade', 'grouptool'),
                                                      array('type'  => 'submit',
                                                            'name'  => 'source',
                                                            'value' => $groupmember->id));
                        } else {
                            $attr = array('type'  => 'submit',
                                          'name'  => 'source['.$groupmember->id.']',
                                          'value' => get_string('copygrade', 'grouptool'));
                            $row[] = html_writer::empty_tag('input', $attr);
                        }
                    }
                    $data[] = $row;
                }
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_grades_present',
                                                                     'grouptool'),
                                                          'notifyproblem'),
                                    'generalbox centered');
            }
        } else {
            print_error('uknown filter-value');
        }

        if (empty($data)) {
            if ($filter == GROUPTOOL_FILTER_ALL) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_data_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'generalbox centered');
            } else if ($filter == GROUPTOOL_FILTER_NONCONFLICTING) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_conflictfree_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'centered').
                $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_groupmembers_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'centered').
                $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            }
        }

        $table->colclasses = $tablecolumns;
        // Instead of the strings an array of html_table_cells can be set as head!
        $table->head = $tableheaders;
        // Instead of the strings an array of html_table_cells can be used for the rows!
        $table->data = $data;

        $return = $title.
                  html_writer::tag('div', $this->add_checkbox_controller(1, null, null, 0),
                                   array('class' => 'checkboxcontroller')).
                  html_writer::table($table).$tablepostfix;
        $return = html_writer::tag('div',
                                   html_writer::tag('div', $return,
                                                    array('class' => 'fieldsetsimulation')),
                                   array('class' => 'clearfix'));
        return $return;
    }

    /**
     * copies the grades from the source(s) to the target(s) for the selected activity
     *
     * @global object $DB
     * @global object $USER
     * @global object $PAGE
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroupsonly limit source-grades to those given by current user
     * @param array $selected array with ids of groups/users to copy grades to as keys
     *                        (depends on filter)
     * @param array $source optional array with ids of entries for whom no source has been
     *                                      selected (just to display a clue to select a source)
     * @param bool $overwrite optional overwrite existing grades (std: false)
     * @param bool $previewonly optional just return preview data
     * @return array ($error, $message)
     */
    private function copy_grades($activity, $mygroupsonly, $selected, $source, $overwrite = false,
                                 $previewonly = false) {
        global $DB, $USER, $PAGE;
        $error = false;
        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroupsonly) {
            require_capability('mod/grouptool:grade', $this->context);
        } else if (!has_capability('mod/grouptool:grade', $this->context)) {
            /*
             * if he wants to grade his own (=submissions where he graded at least 1 group member)
             * he needs either capability to grade all or to grade his own at least
             */
            require_capability('mod/grouptool:grade_own_submission', $this->context);
        }

        $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);
        if (!$cmtouse) {
            return array(true, get_string('couremodule_misconfigured'));
        }
        if ($previewonly) {
            $previewtable = new html_table();
            $previewtable->attributes['class'] = 'coloredrows grading_previewtable';
        } else {
            $info = "";
        }

        $gradeitem = grade_item::fetch(array('itemtype'      => 'mod',
                                              'itemmodule'   => $cmtouse->modname,
                                              'iteminstance' => $cmtouse->instance));

        if (is_array($source)) { // Then we are in multigroup mode (filter = 0 || -1)!
            $sourceusers = $DB->get_records_list('user', 'id', $source);
            $groups = groups_get_all_groups($this->course->id);
            if (!isset($previewtable)) {
                $previewtable = new stdClass();
            }
            $previewtable->head = array(get_string('groups')." (".count($selected).")",
                                        get_string('fullname'),
                                        get_string('grade'),
                                        get_string('feedback'));
            foreach ($selected as $group) {
                if ($previewonly) {
                    $grouprows = array();
                } else {
                    $groupinfo = "";
                }
                $sourcegrade = grade_grade::fetch_users_grades($gradeitem, $source[$group],
                                                                false);
                $sourcegrade = reset($sourcegrade);
                $sourcegrade->load_optional_fields();
                $origteacher = $DB->get_record('user', array('id' => $sourcegrade->usermodified));
                $formattedgrade = round($sourcegrade->finalgrade, 2) .' / ' .
                                  round($gradeitem->grademax, 2);

                $groupmembers = groups_get_members($group);
                $targetgrades = grade_grade::fetch_users_grades($gradeitem,
                                                                 array_keys($groupmembers), true);
                $propertiestocopy = array('rawgrade', 'finalgrade', 'feedback', 'feedbackformat');

                foreach ($targetgrades as $currentgrade) {

                    if ($currentgrade->id == $sourcegrade->id) {
                        continue;
                    }
                    if (!$overwrite && ($currentgrade->finalgrade != null)) {
                        if ($previewonly) {
                            $rowcells = array();
                            if (empty($grouprows)) {
                                $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                                        html_writer::empty_tag('br').
                                        "(".(count($groupmembers) - 1).")");
                            }
                            $fullname = fullname($groupmembers[$currentgrade->userid]);
                            $rowcells[] = new html_table_cell($fullname);
                            $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                            $cell->colspan = 2;
                            $rowcells[] = $cell;
                            $row = new html_table_row();
                            $row->cells = $rowcells;
                            if (empty($grouprows)) {
                                $row->attributes['class'] .= ' firstgrouprow';
                            }
                            $grouprows[] = $row;
                        }
                        continue;
                    }
                    $currentgrade->load_optional_fields();
                    foreach ($propertiestocopy as $property) {
                        $currentgrade->$property = $sourcegrade->$property;
                    }
                    $details = array('student'  => fullname($sourceusers[$source[$group]]),
                                     'teacher'  => fullname($origteacher),
                                     'date'     => userdate($sourcegrade->get_dategraded(),
                                                            get_string('strftimedatetimeshort')),
                                     'feedback' => $sourcegrade->feedback);
                    $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                                                                      'grouptool',
                                                                      $details),
                                                           $currentgrade->feedbackformat);
                    $currentgrade->usermodified = $USER->id;
                    if ($previewonly) {
                        $rowcells = array();
                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                                    html_writer::empty_tag('br').
                                    "(".count($groupmembers).")");
                        }
                        $fullname = fullname($groupmembers[$currentgrade->userid]);
                        $rowcells[] = new html_table_cell($fullname);
                        $rowcells[] = new html_table_cell($formattedgrade);
                        $rowcells[] = new html_table_cell($currentgrade->feedback);
                        $row = new html_table_row();
                        $row->cells = $rowcells;
                        if (empty($grouprows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $grouprows[] = $row;
                    } else {
                        if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                            $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                            $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                        }
                        if ($currentgrade->id) {
                            $noerror = $currentgrade->update();
                        } else {
                            $noerror = $currentgrade->insert();
                        }
                        $currentgrade->set_overridden(true, false);
                        $fullname = fullname($groupmembers[$currentgrade->userid]);
                        if ($noerror) {
                            $groupinfo .= html_writer::tag('span',
                                                           '✓&nbsp;'.$fullname.
                                                           " (".$formattedgrade.")",
                                                           array('class' => 'notifysuccess'));
                        } else {
                            $error = true;
                            $groupinfo .= html_writer::tag('span',
                                                           '✗&nbsp;'.$fullname.
                                                           " (".$formattedgrade.")",
                                                           array('class' => 'notifyproblem'));
                        }
                    }
                }
                if ($previewonly) {
                    $grouprows[0]->cells[0]->rowspan = count($grouprows);
                    if (!is_array($previewtable->data)) {
                        $previewtable->data = array();
                    }
                    $previewtable->data = array_merge($previewtable->data, $grouprows);
                } else {
                    $grpinfo = "";
                    $grpinfo .= html_writer::tag('div', $groups[$group]->name." (".
                                                        count($groupmembers)."): ".$groupinfo);
                    $data = array('student' => fullname($sourceusers[$source[$group]]),
                                  'teacher' => fullname($origteacher),
                                  'date'    => userdate($sourcegrade->get_dategraded(),
                                                        get_string('strftimedatetimeshort')),
                                  'feedback' => $sourcegrade->feedback);
                    $temp = get_string('copied_grade_feedback', 'grouptool', $data);
                    $grpinfo .= html_writer::tag('div', $formattedgrade.html_writer::empty_tag('br').
                                                        format_text($temp,
                                                                    $sourcegrade->feedbackformat));
                    $info .= html_writer::tag('div', $grpinfo, array('class' => 'box1embottom'));
                    // Trigger the event!
                    $logdata = new stdClass();
                    $logdata->groupid = $group;
                    $logdata->cmtouse = $cmtouse->id;
                    \mod_grouptool\event\group_graded::create_direct($this->cm, $logdata)->trigger();
                }
            }
        } else {
            $sourceuser = $DB->get_record('user', array('id' => $source));
            $targetusers = $DB->get_records_list('user', 'id', $selected);
            $sourcegrade = grade_grade::fetch_users_grades($gradeitem, $source, false);
            $sourcegrade = reset($sourcegrade);
            $origteacher = $DB->get_record('user', array('id' => $sourcegrade->usermodified));
            $formattedgrade = round($sourcegrade->finalgrade, 2).' / ' .
                               round($gradeitem->grademax, 2);
            $targetgrades = grade_grade::fetch_users_grades($gradeitem, $selected, true);
            $propertiestocopy = array('rawgrade', 'finalgrade', 'feedback', 'feedbackformat');
            if ($previewonly) {
                $grouprows = array();
                $count = in_array($source, $selected) ? count($selected) - 1 : count($selected);
                $previewtable->head = array('', get_string('fullname')." (".$count.")",
                        get_string('grade'), get_string('feedback'));
                $previewtable->attributes['class'] = 'coloredrows grading_previewtable';
            } else {
                $info .= html_writer::start_tag('div');
                $nameinfo = "";
            }

            foreach ($targetgrades as $currentgrade) {
                if ($currentgrade->id == $sourcegrade->id) {
                    continue;
                }
                if (!$overwrite && ($currentgrade->rawgrade != null)) {
                    if ($previewonly) {
                        $rowcells = array();
                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell(get_string('users'));
                        }
                        $fullname = fullname($targetusers[$currentgrade->userid]);
                        $rowcells[] = new html_table_cell($fullname);
                        $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                        $cell->colspan = 2;
                        $rowcells[] = $cell;
                        $row = new html_table_row();
                        $row->cells = $rowcells;
                        if (empty($grouprows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $grouprows[] = $row;
                    }
                    continue;
                }
                $currentgrade->load_optional_fields();
                foreach ($propertiestocopy as $property) {
                    $currentgrade->$property = $sourcegrade->$property;
                }

                $details = array('student' => fullname($sourceuser),
                                    'teacher' => fullname($origteacher),
                                    'date' => userdate($sourcegrade->get_dategraded(),
                                                       get_string('strftimedatetimeshort')),
                                    'feedback' => $sourcegrade->feedback);
                $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                                                                  'grouptool',
                                                                  $details),
                                                       $currentgrade->feedbackformat);
                $currentgrade->usermodified   = $USER->id;
                if ($previewonly) {
                    $rowcells = array();
                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell(get_string('users'));
                    }
                    $fullname = fullname($targetusers[$currentgrade->userid]);
                    $rowcells[] = new html_table_cell($fullname);
                    $rowcells[] = new html_table_cell($formattedgrade);
                    $rowcells[] = new html_table_cell(format_text($currentgrade->feedback,
                                                                   $currentgrade->feedbackformat));
                    $row = new html_table_row();
                    $row->cells = $rowcells;
                    if (empty($grouprows)) {
                        $row->attributes['class'] .= ' firstgrouprow';
                    }
                    $grouprows[] = $row;
                } else {
                    if ($nameinfo != "") {
                        $nameinfo .= ", ";
                    }
                    if ($currentgrade->id) {
                        $noerror = $currentgrade->update();
                    } else {
                        $noerror = $currentgrade->insert();
                    }
                    $currentgrade->set_overridden(true, false);
                    $fullname = fullname($targetusers[$currentgrade->userid]);
                    if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                        $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                        $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                    }
                    if ($noerror) {
                        $nameinfo .= html_writer::tag('span',
                                                       '✓&nbsp;'.$fullname,
                                                       array('class' => 'notifysuccess'));
                    } else {
                        $error = true;
                        $nameinfo .= html_writer::tag('span',
                                                       '✗&nbsp;'.$fullname,
                                                       array('class' => 'notifyproblem'));
                    }
                }
            }
            if ($previewonly) {
                $grouprows[0]->cells[0]->rowspan = count($grouprows);
                $previewtable->data = $grouprows;
            } else {
                $info .= $nameinfo.html_writer::end_tag('div');
                $details = array('student' => fullname($sourceuser),
                                 'teacher' => fullname($origteacher),
                                 'date' => userdate($sourcegrade->get_dategraded(),
                                                    get_string('strftimedatetimeshort')),
                                 'feedback' => $sourcegrade->feedback);
                $info .= html_writer::tag('div', get_string('grade').": ".
                                        $formattedgrade.html_writer::empty_tag('br').
                                        format_text(get_string('copied_grade_feedback', 'grouptool',
                                                               $details),
                                                    $sourcegrade->feedbackformat),
                                                    array('class' => 'gradeinfo'));
            }
            if (!$previewonly) {
                // Trigger the event!
                $logdata = new stdClass();
                $logdata->source = $source;
                $logdata->selected = $selected;
                $logdata->cmtouse = $cmtouse->id;
                \mod_grouptool\event\group_graded::create_without_groupid($this->cm, $logdata)->trigger();
            }
        }
        if ($previewonly) {
            return array($error, html_writer::tag('div', html_writer::table($previewtable),
                                                  array('class' => 'centeredblock')));
        } else {
            return array($error, html_writer::tag('div', $info, array('class' => 'centeredblock')));
        }
    }

    /**
     * view grading-tab
     *
     * @global object $SESSION
     * @global object $PAGE
     * @global object $CFG
     * @global object $OUTPUT
     * @global object $USER
     */
    public function view_grading() {
        global $SESSION, $PAGE, $CFG, $OUTPUT, $USER, $DB;

        if (!has_capability('mod/grouptool:grade', $this->context)
                && !has_capability('mod/groputool:grade_own_groups', $this->context)) {
            print_error('nopermissions');
            return;
        }

        $refreshtable = optional_param('refresh_table', 0, PARAM_BOOL);
        $activity = optional_param('activity', null, PARAM_INT); // This is the coursemodule-ID.

        // Show only groups with grades given by current user!
        $mygroupsonly = optional_param('mygroups_only', null, PARAM_BOOL);

        if (!has_capability('mod/grouptool:grade', $this->context)) {
            $mygroupsonly = 1;
        }

        if ($mygroupsonly != null) {
            set_user_preference('mygroups_only', $mygroupsonly, $USER->id);
        }

        // Show only groups with missing grades (groups with at least 1 not-graded member)!
        $incompleteonly = optional_param('incomplete_only', 0, PARAM_BOOL);

        $overwrite = optional_param('overwrite', 0, PARAM_BOOL);

        // Here -1 = nonconflicting, 0 = all     or groupid for certain group!
        $filter = optional_param('filter', GROUPTOOL_FILTER_NONCONFLICTING, PARAM_INT);
        // Steps: 0 = show, 1 = confirm, 2 = action!
        $step = optional_param('step', 0, PARAM_INT);
        if ($refreshtable) { // If it was just a refresh, reset step!
            $step = 0;
        }

        $grouping = optional_param('grouping', 0, PARAM_INT);

        if ($filter > 0) {
            if ($step == 2) {
                $source = optional_param('source', null, PARAM_INT);
                // Serialized data @todo better PARAM_TYP?
                $selected = optional_param('selected', null, PARAM_RAW);
                if (!empty($selected)) {
                    $selected = unserialize($selected);
                }
            } else {
                if (GROUPTOOL_IE7_IS_DEAD) {
                    $source = optional_param('source', null, PARAM_INT);
                } else {
                    /*
                     * compatibility for MSIE 7 (<button></button>)
                     * we use a little trick here:
                     * instead of using value attribute (which gets not submitted in MSIE 7)
                     * we use the arrays key to submit the value (=user-id)
                     */
                    $source = optional_param_array("source", array(), PARAM_TEXT);
                    $source = array_keys($source);
                    $source = reset($source);
                }
                $selected = optional_param_array('selected', null, PARAM_INT);
                if (!empty($source) && !$refreshtable) {
                    $step = 1;
                }
            }
        } else {
            if ($step == 2) {
                $source = optional_param('source', null, PARAM_RAW);
                if (!empty($source)) {
                    $source = unserialize($source);
                }
                $selected = optional_param('selected', null, PARAM_RAW);
                if (!empty($selected)) {
                    $selected = unserialize($selected);
                }
            } else {
                $source = optional_param_array('source', array(), PARAM_INT);
                $selected = optional_param_array('selected', array(), PARAM_INT);
                $copygroups = optional_param('copygrades', 0, PARAM_BOOL);
                if ($copygroups && !$refreshtable) {
                    $step = 1;
                }
            }
        }
        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        if (($step == 2) && !$confirm) {
            $step = 0;
        }

        // Reset process if some evil hacker tried to do smth!
        if (!$confirm && (!data_submitted() || !confirm_sesskey())) {
            $refreshtable = false;
            $step = 0;
        }

        if (!empty($mygroupsonly)) {
            $mygroupsonly = get_user_preferences('mygroups_only', 1, $USER->id);
        }

        $missingsource = array();

        if ($step == 1) {    // Show confirm message!

            if ($filter > 0) {
                // Single group mode!
                if (is_array($selected) && in_array($source, $selected)) {
                    foreach ($selected as $key => $cmp) {
                        if ($cmp == $source) {
                            unset($selected[$key]);
                        }
                    }
                }
                if (!empty($selected)) {
                    list($error, $preview) = $this->copy_grades($activity, $mygroupsonly,
                                                                $selected, $source, $overwrite,
                                                                true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id,
                                               array('tab'           => 'grading',
                                                     'confirm'       => 'true',
                                                     'sesskey'       => sesskey(),
                                                     'step'          => '2',
                                                     'activity'      => $activity,
                                                     'mygroups_only' => $mygroupsonly,
                                                     'overwrite'     => $overwrite,
                                                     'selected'      => serialize($selected),
                                                     'source'        => serialize($source)));
                    $cancel = new moodle_url("view.php?id=".$this->cm->id,
                                             array('tab'           => 'grading',
                                                   'confirm'       => 'false',
                                                   'sesskey'       => sesskey(),
                                                   'step'          => '2',
                                                   'activity'      => $activity,
                                                   'mygroups_only' => $mygroupsonly,
                                                   'overwrite'     => $overwrite,
                                                   'selected'      => serialize($selected),
                                                   'source'        => serialize($source)));
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm',
                                                       'grouptool'), $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm',
                                                       'grouptool'), $continue, $cancel);
                    }
                } else {
                    echo $OUTPUT->box($OUTPUT->notification(get_string('no_target_selected',
                                                                       'grouptool'),
                                                            'notifyproblem'), 'generalbox');
                    $step = 0;
                }

            } else if ($filter == GROUPTOOL_FILTER_ALL
                       || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
                // All or nonconflicting mode?
                foreach ($selected as $key => $grp) {
                    // If no grade is choosen add this group to missing-source-list!
                    if (empty($source[$grp])) {
                        $missingsource[] = $grp;
                    }
                }

                if (!empty($selected) && (count($missingsource) == 0)) {
                    list($error, $preview) = $this->copy_grades($activity, $mygroupsonly,
                                                                $selected, $source, $overwrite,
                                                                true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id,
                                               array('tab'           => 'grading',
                                                     'confirm'       => 'true',
                                                     'sesskey'       => sesskey(),
                                                     'activity'      => $activity,
                                                     'mygroups_only' => $mygroupsonly,
                                                     'overwrite'     => $overwrite,
                                                     'step'          => '2',
                                                     'selected'      => serialize($selected),
                                                     'source'        => serialize($source)));
                    $cancel = new moodle_url("view.php?id=".$this->cm->id,
                                             array('tab' => 'grading',
                                                   'confirm'       => 'false',
                                                   'sesskey'       => sesskey(),
                                                   'activity'      => $activity,
                                                   'mygroups_only' => $mygroupsonly,
                                                   'overwrite'     => $overwrite,
                                                   'step'          => '2',
                                                   'selected'      => serialize($selected),
                                                   'source'        => serialize($source)));
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm',
                                                                  'grouptool'), $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm',
                                                                  'grouptool'), $continue, $cancel);
                    }
                } else {
                    if (empty($selected)) {
                        echo $OUTPUT->box($OUTPUT->notification(get_string('no_target_selected',
                                                                           'grouptool'),
                                                                'notifyproblem'), 'generalbox');
                        $step = 0;
                    }
                    if (count($missingsource) != 0) {
                        echo $OUTPUT->box($OUTPUT->notification(get_string('sources_missing',
                                                                           'grouptool'),
                                                                'notifyproblem'), 'generalbox');
                        $step = 0;
                    }
                }
            } else {
                print_error('wrong parameter');
            }
        }

        if ($step == 2) {    // Do action and continue with showing the form!
            // if there was an error?
            list($error, $info) = $this->copy_grades($activity, $mygroupsonly, $selected, $source,
                                                     $overwrite);
            if ($error) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('copy_grades_errors',
                                                                   'grouptool'),
                                                        'notifyproblem').$info, 'generalbox tumargin');
            } else {
                echo $OUTPUT->box($OUTPUT->notification(get_string('copy_grades_success',
                                                                   'grouptool'),
                                                        'notifysuccess').$info, 'generalbox tumargin');
            }
        }

        if ($step != 1 || count($missingsource)) {    // Show form if step is either 0 or 2!

            // Prepare form content!
            $activitytitle = get_string('grading_activity_title', 'grouptool');

            if ($modinfo = get_fast_modinfo($this->course)) {
                $section = 0;
                $sectionsinfo = $modinfo->get_section_info_all();
                $sections = $modinfo->get_sections();
                foreach ($sections as $curnumber => $sectionmodules) {
                    $sectiontext = '--- '.
                                   course_get_format($this->course)->get_section_name($curnumber).
                                   ' ---';
                    $activities["section/$curnumber"] = $sectiontext;

                    foreach ($sectionmodules as $curcmid) {
                        $mod = $modinfo->get_cm($curcmid);
                        if ($mod->modname == "label") {
                            continue;
                        }

                        if (file_exists($CFG->dirroot . '/mod/'.$mod->modname.'/lib.php')) {
                            require_once($CFG->dirroot . '/mod/'.$mod->modname.'/lib.php');
                            $supportfn = $mod->modname."_supports";
                            if (function_exists($supportfn)) {
                                if ($supportfn(FEATURE_GRADE_HAS_GRADE) !== true) {
                                    continue;
                                }
                            }
                        }

                        $name = strip_tags(format_string($mod->name, true));
                        if (core_text::strlen($name) > 55) {
                            $name = core_text::substr($name, 0, 50)."...";
                        }
                        if (!$mod->visible) {
                            $name = "(".$name.")";
                        }
                        $activities["$curcmid"] = $name;
                    }
                }
            }

            $hiddenelements = html_writer::empty_tag('input', array('type'   => 'hidden',
                                                                     'name'  => 'sesskey',
                                                                     'value' => sesskey()));
            $hiddenelements .= html_writer::empty_tag('input', array('type'   => 'hidden',
                                                                     'name'  => 'tab',
                                                                     'value' => 'grading'));
            $activityelement = html_writer::select($activities, "activity", $activity);;
            $activityselect = html_writer::start_tag('div', array('class' => 'fitem')).
                              html_writer::tag('div', $activitytitle,
                                               array('class' => 'fitemtitle')).
                              html_writer::tag('div', $activityelement,
                                               array('class' => 'felement')).
                              html_writer::end_tag('div');

            $mygroupsonlytitle = "";
            if (!has_capability('mod/grouptool:grade', $this->context)) {
                $attr['disabled'] = 'disabled';
                $mygroupsonlyelement = html_writer::checkbox('mygroups_only', 1, $mygroupsonly,
                                                               get_string('mygroups_only_label',
                                                                          'grouptool'), $attr);
                $attributes['type'] = 'hidden';
                $attributes['value'] = 1;
                $attributes['name'] = 'mygroups_only';
                $mygroupsonlyelement .= html_writer::empty_tag('input', $attributes);
            } else {
                $mygroupsonlyelement = html_writer::checkbox('mygroups_only', 1, $mygroupsonly,
                                                               get_string('mygroups_only_label',
                                                                          'grouptool'));
            }
            $mygroupsonlychkbox = html_writer::start_tag('div', array('class' => 'fitem')).
                                  html_writer::tag('div', ($mygroupsonlytitle != "" ?
                                                          $mygroupsonlytitle : "&nbsp;"),
                                                   array('class' => 'fitemtitle')).
                                  html_writer::tag('div', $mygroupsonlyelement,
                                                   array('class' => 'felement')).
                                  html_writer::end_tag('div');

            $incompleteonlytitle = "";
            $incompleteonlyelement = html_writer::checkbox('incomplete_only', 1, $incompleteonly,
                                                           get_string('incomplete_only_label',
                                                                      'grouptool'));
            $incompleteonlychkbox = html_writer::start_tag('div', array('class' => 'fitem')).
                                    html_writer::tag('div', ($incompleteonlytitle != "" ?
                                                            $incompleteonlytitle : "&nbsp;"),
                                                     array('class' => 'fitemtitle')).
                                    html_writer::tag('div', $incompleteonlyelement,
                                                     array('class' => 'felement')).
                                    html_writer::end_tag('div');

            $overwritetitle = "";
            $overwriteelement = html_writer::checkbox('overwrite', 1, $overwrite,
                                                      get_string('overwrite_label', 'grouptool'));
            $overwritechkbox = html_writer::start_tag('div', array('class' => 'fitem')).
                               html_writer::tag('div', ($overwritetitle != "" ?
                                                       $overwritetitle : "&nbsp;"),
                                                array('class' => 'fitemtitle')).
                               html_writer::tag('div', $overwriteelement,
                                                array('class' => 'felement')).
                               html_writer::end_tag('div');

            $filtertitle = get_string('grading_filter_select_title', 'grouptool').
                           $OUTPUT->help_icon('grading_filter_select_title', 'grouptool');
            $groupingtitle = get_string('grading_grouping_select_title', 'grouptool');
            $groupings = groups_get_all_groupings($this->course->id);
            $options = array();
            foreach ($groupings as $currentgrouping) {
                $options[$currentgrouping->id] = $currentgrouping->name;
            }
            $groupingelement = html_writer::select($options, 'grouping', $grouping,
                                                   get_string('disabled', 'grouptool'));
            $groupingselect = html_writer::start_tag('div', array('class' => 'fitem')).
                              html_writer::tag('div', $groupingtitle, array('class' => 'fitemtitle')).
                              html_writer::tag('div', $groupingelement, array('class' => 'felement')).
                              html_writer::end_tag('div');

            $options = array("-1" => get_string('nonconflicting', 'grouptool'),
                             "0"  => get_string('all'));
            $groups = groups_get_all_groups($this->course->id, null, $grouping, 'g.id, g.name');
            foreach ($groups as $key => $group) {
                $membercount = $DB->count_records('groups_members', array('groupid' => $group->id));
                if ($membercount == 0) {
                    continue;
                }
                $options[$key] = $group->name.' ('.$membercount.')';
            }

            $filterelement = html_writer::select($options, 'filter', $filter, false);
            $filterselect = html_writer::start_tag('div', array('class' => 'fitem')).
                            html_writer::tag('div', $filtertitle, array('class' => 'fitemtitle')).
                            html_writer::tag('div', $filterelement, array('class' => 'felement')).
                            html_writer::end_tag('div');

            $refreshtitle = "";
            $refreshelement = html_writer::tag('button', get_string('refresh_table_button',
                                                                    'grouptool'),
                                               array('type'  => 'submit',
                                                     'name'  => 'refresh_table',
                                                     'value' => 'true'));
            $refreshbutton = html_writer::start_tag('div', array('class' => 'fitem')).
                             html_writer::tag('div', ($refreshtitle != "" ? $refreshtitle : "&nbsp;"),
                                              array('class' => 'fitemtitle')).
                             html_writer::tag('div', $refreshelement, array('class' => 'felement')).
                             html_writer::end_tag('div');

            $legend = html_writer::tag('legend', get_string('filters_legend', 'grouptool'));
            $filterelements = html_writer::tag('fieldset',
                                               $legend.$activityselect.$mygroupsonlychkbox.
                                               $incompleteonlychkbox.$overwritechkbox.$groupingselect.
                                               $filterselect.$refreshbutton,
                                               array('class' => 'clearfix'));
            if ($filter > 0) {
                $tablehtml = $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                                      $filter, $selected);
            } else {
                $tablehtml = $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                                      $filter, $selected, $missingsource);
            }

            $formcontent = html_writer::tag('div', $hiddenelements.$filterelements.$tablehtml,
                                            array('class' => 'clearfix'));

             $jsmodule = array(
                                'name'     => 'moodle-mod_grouptool-grading',
                                'fullpath' => '/mod/grouptool/yui/grading/grading.js',
                                'requires' => array('base', 'io', 'node'),
                                'strings' => array()
                             );
            $jsdata = array(array( 'lang' => current_language(),
                             'contextid' => $this->context->id));
            $PAGE->requires->yui_module('moodle-mod_grouptool-grading', 'M.mod_grouptool.grading.init', $jsdata);

            $formattr = array(
                    'method' => 'post',
                    'action' => $PAGE->url,
                    'name'   => 'grading_form',
                    'class'  => 'mform');
            // Print form!
            echo html_writer::tag('form', $formcontent, $formattr);
        }

    }

    /**
     * gets data about active groups for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @param bool $includeregs optional include registered users in returned object
     * @param bool $includequeues optional include queued users in returned object
     * @param int $agrpid optional filter by a single active-groupid from {grouptool_agrps}.id
     * @param int $groupid optional filter by a single group-id from {groups}.id
     * @param int $groupingid optional filter by a single grouping-id
     * @param bool $indexbygroup optional index returned array by {groups}.id
     *                                    instead of {grouptool_agrps}.id
     * @return array of objects containing all necessary information about chosen active groups
     */
    private function get_active_groups($includeregs=false, $includequeues=false, $agrpid=0,
                                       $groupid=0, $groupingid=0, $indexbygroup=true) {
        global $DB, $PAGE, $CFG;

        require_capability('mod/grouptool:view_groups', $this->context);

        $params = array('grouptoolid' => $this->cm->instance);

        if (!empty($agrpid)) {
            $agrpidwhere = " AND agrp.id = :agroup";
            $params['agroup'] = $agrpid;
        } else {
            $agrpidwhere = "";
        }
        if (!empty($groupid)) {
            $groupidwhere = " AND grp.id = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupidwhere = "";
        }
        if (!empty($groupingid)) {
            $groupingidwhere = " AND grpgs.id = :groupingid";
            $params['groupingid'] = $groupingid;
        } else {
            $groupingidwhere = "";
        }

        if (!empty($this->grouptool->use_size)) {
            if (empty($this->grouptool->use_individual)) {
                $sizesql = " ".$this->grouptool->grpsize." grpsize,";
            } else {
                $grouptoolgrpsize = get_config('mod_grouptool', 'grpsize');
                $grpsize = (!empty($this->grouptool->grpsize) ?
                            $this->grouptool->grpsize : $grouptoolgrpsize);
                if (empty($grpsize)) {
                    $grpsize = 3;
                }
                $sizesql = " COALESCE(agrp.grpsize, ".$grpsize.") grpsize,";
            }
        } else {
            $sizesql = "";
        }
        if ($indexbygroup) {
            $idstring = "grp.id id, agrp.id agrpid";
        } else {
            $idstring = "agrp.id agrpid, grp.id id";
        }

        $groupdata = $DB->get_records_sql("
                SELECT ".$idstring.", MAX(grp.name) name,".$sizesql." MAX(agrp.sort_order) sort_order
                FROM {groups} grp LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                WHERE agrp.grouptoolid = :grouptoolid AND agrp.active = 1".
                     $agrpidwhere.$groupidwhere.$groupingidwhere."
                GROUP BY grp.id, agrp.id
                ORDER BY sort_order ASC, name ASC", $params);
        if (!empty($groupdata)) {
            foreach ($groupdata as $key => $group) {
                $groupingids = $DB->get_fieldset_select('groupings_groups',
                                                        'groupingid',
                                                        'groupid = ?',
                                                        array($group->id));
                if (!empty($groupingids)) {
                    $groupdata[$key]->classes = implode(',', $groupingids);
                } else {
                    $groupdata[$key]->classes = '';
                }
            }

            if ((!empty($this->grouptool->use_size) && !$this->grouptool->use_individual)
                    || ($this->grouptool->use_queue && $includequeues)
                    || ($includeregs)) {
                $keys = array_keys($groupdata);
                foreach ($keys as $key) {
                    $groupdata[$key]->queued = null;
                    if ($includequeues && $this->grouptool->use_queue) {
                        $attr = array('agrpid' => $groupdata[$key]->agrpid);
                        $groupdata[$key]->queued = (array)$DB->get_records('grouptool_queued', $attr);
                    }

                    $groupdata[$key]->registered = null;
                    if ($includeregs) {
                        $params = array('agrpid' => $groupdata[$key]->agrpid);
                        $where = "agrpid = :agrpid AND modified_by >= 0";
                        $groupdata[$key]->registered = $DB->get_records_select('grouptool_registered',
                                                                            $where, $params);
                        $params['modifierid'] = -1;
                        $where = "agrpid = :agrpid AND modified_by = :modifierid";
                        $groupdata[$key]->marked = $DB->get_records_select('grouptool_registered',
                                                                        $where, $params);
                        $groupdata[$key]->moodle_members = groups_get_members($groupdata[$key]->id);
                    }
                }
                unset($key);
            }
        } else {
            $groupdata = array();
        }

        return $groupdata;
    }

    /**
     * unregisters/unqueues a user from a certain active-group
     *
     * @global object $USER
     * @global object $PAGE
     * @global object $DB
     * @global object $CFG
     * @param int $agrpid active-group-id to unregister/unqueue user from
     * @param int $userid user to unregister/unqueue
     * @param bool $previewonly optional don't act, just return a preview
     * @return array ($error, $message)
     */
    private function unregister_from_agrp($agrpid=0, $userid=0, $previewonly=false) {
        global $USER, $PAGE, $DB, $CFG;

        if (empty($agrpid)) {
            print_error('missing_param', null, $PAGE->url);
        }

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allow_reg
                    && (($this->grouptool->timedue == 0)
                         || (time() < $this->grouptool->timedue)));

        if (!$regopen && !has_capability('mod/grouptool:register_students', $this->context)) {
            return array(true, get_string('reg_not_open', 'grouptool'));
        }

        if (empty($this->grouptool->allow_unreg)) {
            return array(true, get_string('unreg_not_allowed', 'grouptool'));
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', array('id' => $userid));
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);
        $message->groupname = $groupdata->name;

        $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ?", array($this->grouptool->id));
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('grouptool_registered', "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql,
                                              $params);
        $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($userregs + $userqueues <= $min) {
            if ($userid == $USER->id) {
                return array(true, get_string('you_have_too_less_regs', 'grouptool', $message));
            } else {
                return array(true, get_string('user_has_too_less_regs', 'grouptool', $message));
            }
        }

        if ($groupdata) {
            if ($this->get_rank_in_queue($groupdata->registered, $userid) != false) {
                // He is registered!
                if ($previewonly) {
                    if ($userid == $USER->id) {
                        return array(false, get_string('unreg_you_from_group', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unreg_from_group', 'grouptool',
                                                       $message));
                    }
                } else {
                    $records = $DB->get_records('grouptool_registered', array('agrpid' => $agrpid,
                                                                              'userid' => $userid));
                    $DB->delete_records('grouptool_registered', array('agrpid' => $agrpid,
                                                                      'userid' => $userid));
                    if (!empty($this->grouptool->immediate_reg)) {
                        groups_remove_member($groupdata->id, $userid);
                    }
                    foreach ($records as $data) {
                        // Trigger the event!
                        $data->groupid = $groupdata->id;
                        \mod_grouptool\event\registration_deleted::create_direct($this->cm, $data)->trigger();
                    }
                    // Get next queued user and put him in the group (and delete queue entry)!
                    if (!empty($this->grouptool->use_queue) && !empty($groupdata->queued)) {
                        $sql = "SELECT queued.*, agrp.groupid
                                  FROM {grouptool_queued} queued
                                  JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                                 WHERE agrpid = ?
                              ORDER BY timestamp ASC
                                 LIMIT 1";
                        $record = $DB->get_record_sql($sql, array($agrpid));
                        $newrecord = clone $record;
                        unset($newrecord->id);
                        $newrecord->modified_by = $newrecord->userid;
                        $newrecord->id = $DB->insert_record('grouptool_registered', $newrecord);
                        if (!empty($this->grouptool->immediate_reg)) {
                            groups_add_member($groupdata->id, $newrecord->userid);
                        }
                        $allowm = $this->grouptool->allow_multiple;
                        $usrregcnt = $this->get_user_reg_count(0, $newrecord->userid);
                        $max = $this->grouptool->choose_max;
                        if (($allowm && ( $usrregcnt >= $max) ) || !$allowm) {
                            $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                            $agrpids = array_keys($agrps);
                            list($sql, $params) = $DB->get_in_or_equal($agrpids);
                            $records = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                                               FROM {grouptool_queued} queued
                                                               JOIN {grouptool_agrps} agrp ON queued.agrpid = agrp.id
                                                              WHERE userid = ? AND agrpid ".$sql,
                                                            array_merge(array($newrecord->userid), $params));
                            $DB->delete_records_select('grouptool_queued',
                                                       ' userid = ? AND agrpid '.$sql,
                                                       array_merge(array($newrecord->userid),
                                                                   $params));
                            foreach ($records as $cur) {
                                // Trigger the event!
                                \mod_grouptool\event\queue_entry_deleted::create_limit_violation($this->cm, $cur)->trigger();
                            }
                        }

                        $strgrouptools = get_string("modulenameplural", "grouptool");
                        $strgrouptool  = get_string("modulename", "grouptool");

                        $postsubject = $this->course->shortname.': '.$strgrouptools.': '.
                                       format_string($this->grouptool->name, true);
                        $posttext  = $this->course->shortname.' -> '.$strgrouptools.' -> '.
                                     format_string($this->grouptool->name, true)."\n";
                        $posttext .= "----------------------------------------------------------\n";
                        $posttext .= get_string("register_you_in_group_successmail",
                                                "grouptool", $message)."\n";
                        $posttext .= "----------------------------------------------------------\n";
                        $usermailformat = $DB->get_field('user', 'mailformat',
                                                         array('id' => $newrecord->userid));
                        if ($usermailformat == 1) {  // HTML!
                            $posthtml = "<p><font face=\"sans-serif\">";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/course/view.php?id=".
                                        $this->course->id."\">".$this->course->shortname."</a> ->";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/index.php?id=".
                                        $this->course->id."\">".$strgrouptools."</a> ->";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/view.php?id=".
                                        $this->cm->id."\">".format_string($this->grouptool->name,
                                                                          true)."</a></font></p>";
                            $posthtml .= "<hr /><font face=\"sans-serif\">";
                            $posthtml .= "<p>".get_string("register_you_in_group_successmailhtml",
                                                          "grouptool", $message)."</p>";
                            $posthtml .= "</font><hr />";
                        } else {
                            $posthtml = "";
                        }

                        $messageuser = $DB->get_record('user', array('id' => $newrecord->userid));
                        $eventdata = new stdClass();
                        $eventdata->modulename       = 'grouptool';
                        $userfrom = core_user::get_noreply_user();
                        $eventdata->userfrom         = $userfrom;
                        $eventdata->userto           = $messageuser;
                        $eventdata->subject          = $postsubject;
                        $eventdata->fullmessage      = $posttext;
                        $eventdata->fullmessageformat = FORMAT_PLAIN;
                        $eventdata->fullmessagehtml  = $posthtml;
                        $eventdata->smallmessage     = get_string('register_you_in_group_success',
                                                                  'grouptool', $message);

                        $eventdata->name            = 'grouptool_moveupreg';
                        $eventdata->component       = 'mod_grouptool';
                        $eventdata->notification    = 1;
                        $eventdata->contexturl      = $CFG->wwwroot.'/mod/grouptool/view.php?id='.
                                                      $this->cm->id;
                        $eventdata->contexturlname  = $this->grouptool->name;

                        message_send($eventdata);
                        $DB->delete_records('grouptool_queued', array('id' => $record->id));

                        // Trigger the event!
                        // We fetched groupid above in SQL.
                        \mod_grouptool\event\user_moved::promotion_from_queue($this->cm, $record, $newrecord)->trigger();
                    }
                    if ($userid == $USER->id) {
                        return array(false, get_string('unreg_you_from_group_success', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unreg_from_group_success', 'grouptool',
                                                       $message));
                    }
                }
            } else if ($this->get_rank_in_queue($groupdata->queued, $userid) != false) {
                // He is queued!
                if ($previewonly) {
                    if ($userid == $USER->id) {
                        return array(false, get_string('unqueue_you_from_group', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unqueue_from_group', 'grouptool',
                                                       $message));
                    }
                } else {
                    $records = $DB->get_records('grouptool_queued', array('agrpid' => $agrpid, 'userid' => $userid));
                    $DB->delete_records('grouptool_queued', array('agrpid' => $agrpid,
                                                                  'userid' => $userid));
                    foreach ($records as $cur) {
                        // Trigger the Event!
                        $cur->groupid = $groupdata->id;
                        \mod_grouptool\event\queue_entry_deleted::create_direct($this->cm, $cur)->trigger();
                    }
                    if ($userid == $USER->id) {
                        return array(false, get_string('unqueue_you_from_group_success',
                                                       'grouptool', $message));
                    } else {
                        return array(false, get_string('unqueue_from_group_success', 'grouptool',
                                                       $message));
                    }
                }
            } else {
                if ($userid == $USER->id) {
                    return array(true, get_string('you_are_not_in_queue_or_registered', 'grouptool',
                                                  $message));
                } else {
                    return array(true, get_string('not_in_queue_or_registered', 'grouptool',
                                                  $message));
                }
            }
        } else {
            return array(true, get_string('error_getting_data', 'grouptool'));
        }
    }

    /**
     * registers/queues a user in a certain active-group
     *
     * @global object $USER
     * @global object $PAGE
     * @global object $DB
     * @param int $agrpid active-group-id to register/queue user to
     * @param int $userid user to register/queue
     * @param bool $previewonly optional don't act, just return a preview
     * @return array ($error, $message)
     */
    private function register_in_agrp($agrpid=0, $userid=0, $previewonly=false, $movefromqueue=false) {
        global $USER, $PAGE, $DB, $SESSION;

        $grouptool = $this->grouptool;

        if (empty($agrpid)) {
            print_error('missing_param', null, $PAGE->url);
        }

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allow_reg
                    && (($this->grouptool->timedue == 0)
                        || (time() < $this->grouptool->timedue))
                    && ($this->grouptool->timeavailable < time()));

        if (!$regopen && !has_capability('mod/grouptool:register_students', $this->context)) {
            return array(true, get_string('reg_not_open', 'grouptool'));
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', array('id' => $userid));
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) == 1) {
            $groupdata = current($groupdata);
            $message->groupname = $groupdata->name;
            // We have to filter only active groups to ensure no problems counting userregs and -queues.
            $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ? AND active = 1", array($grouptool->id));
            list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
            array_unshift($params, $userid);
            $userregs = $DB->count_records_select('grouptool_registered', "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql,
                                                  $params);
            $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
            $max = $grouptool->allow_multiple ? $grouptool->choose_max : 1;
            $min = $grouptool->allow_multiple ? $grouptool->choose_min : 0;
            if (!empty($groupdata->registered)
                && $this->get_rank_in_queue($groupdata->registered, $userid) != false) {
                // We're sorry, but user's already registered in this group!
                if ($userid != $USER->id) {
                    return array(true, get_string('already_registered', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_already_registered', 'grouptool',
                                                  $message));
                }
            }

            if (empty($movefromqueue) && !empty($groupdata->queued)
                && $this->get_rank_in_queue($groupdata->queued, $userid) != false) {
                // We're sorry, but user's already queued in this group!
                if ($userid != $USER->id) {
                    return array(true, get_string('already_queued', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_aleady_queued', 'grouptol', $message));
                }
            }

            if ($this->grpmarked($groupdata->agrpid, $userid)) {
                // Allready marked for registration?
                if ($userid != $USER->id) {
                    return array(true, get_string('already_marked', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_already_marked', 'grouptool', $message));
                }
            }

            if (empty($movefromqueue)
                && ($userqueues == 1 && $userregs == $max - 1)
                || ($userqueues + $userregs == 1 && $max == 1)) {
                // Groupchange!
                if (empty($grouptool->allow_unreg)) {
                    return array(true, get_string('unreg_not_allowed', 'grouptool'));
                }

                if ($previewonly) {
                    if (!$this->grouptool->use_size
                        || (count($groupdata->registered) < $groupdata->grpsize)
                        || ($this->grouptool->use_queue
                            && $userqueues < $this->grouptool->queues_max)) {
                        return array(-1,
                                     get_string('change_group_to', 'grouptool', $message));
                    } else if (!$this->grouptool->use_queue) {
                        // Group is full!
                        if ($userid != $USER->id) {
                            return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                        } else {
                            return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                        }
                    } else if ($userqueues >= $this->grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                } else if (!$this->grouptool->use_size
                           || (count($groupdata->registered) < $groupdata->grpsize)
                           || ($this->grouptool->use_queue
                               && $userqueues - 1 < $this->grouptool->queues_max)) {
                    $record = new stdClass();
                    $record->agrpid = $agrpid;
                    $record->userid = $userid;
                    $record->timestamp = time();
                    $record->modified_by = $USER->id;
                    if ($userqueues == 1) {
                        // Delete his queue!
                        $queues = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                                          FROM {grouptool_queued} queued
                                                          JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                                                          WHERE userid = ? AND agrpid ".$agrpsql, $params);
                        $DB->delete_records_select('grouptool_queued',
                                                   "userid = ? AND agrpid ".$agrpsql, $params);
                        foreach ($queues as $cur) {
                            // Trigger the event!
                            \mod_grouptool\event\queue_entry_deleted::create_direct($this->cm, $cur);
                        }
                    } else if ($userregs == 1) {
                        $oldgrp = $DB->get_field_sql("SELECT agrp.groupid
                                                        FROM {grouptool_registered} reg
                                                        JOIN {grouptool_agrps} agrp ON agrp.id = reg.agrpid
                                                       WHERE reg.userid = ? AND reg.agrpid ".$agrpsql,
                                                     $params, MUST_EXIST);
                        $regs = $DB->get_records_select('grouptool_registered',
                                                        "userid = ? AND agrpid ".$agrpsql, $params);
                        $DB->delete_records_select('grouptool_registered',
                                                   "userid = ? AND agrpid ".$agrpsql, $params);
                        if (!empty($oldgrp) && !empty($this->grouptool->immediate_reg)) {
                            groups_remove_member($oldgrp, $userid);
                        }
                        foreach ($regs as $cur) {
                            // Trigger the event!
                            $cur->groupid = $oldgrp;
                            \mod_grouptool\event\registration_deleted::create_direct($this->cm, $cur);
                        }
                    }
                    if (!$this->grouptool->use_size
                        || (count($groupdata->registered) < $groupdata->grpsize)) {
                        $record->id = $DB->insert_record('grouptool_registered', $record);
                        if ($this->grouptool->immediate_reg) {
                            groups_add_member($groupdata->id, $userid);
                        }
                        // Trigger the event!
                        $record->groupid = $groupdata->id;
                        \mod_grouptool\event\registration_created::create_direct($this->cm, $record)->trigger();
                    } else if ($this->grouptool->use_queue
                               && $userqueues - 1 < $this->grouptool->queues_max) {
                        $record->id = $DB->insert_record('grouptool_queued', $record);
                        // Trigger the Event!
                        $record->groupid = $groupdata->id;
                        \mod_grouptool\event\queue_entry_created::create_direct($this->cm, $record)->trigger();
                    } else if (!$this->grouptool->use_queue) {
                        // Group is full!
                        if ($userid != $USER->id) {
                            return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                        } else {
                            return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                        }
                    } else if ($userqueues - 1 >= $this->grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                    if ($userid != $USER->id) {
                        return array(-1,
                                     get_string('change_group_to_success', 'grouptool',
                                                $message));
                    } else {
                        return array(-1,
                                     get_string('you_change_group_to_success', 'grouptool',
                                                $message));
                    }
                }
            }

            if ((empty($movefromqueue) && $userregs + $userqueues >= $max)
                 || (!empty($movefromqueue) && $userregs + $userqueues - 1 >= $max)) {
                return array(1, get_string('too_many_regs', 'grouptool'));
            }
            // Get users marks!
            $marks = $this->count_user_marks($userid);
            if ($grouptool->use_size) {
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    // Register!
                    if ($previewonly) {
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('register_in_group', 'grouptool', $message));
                        } else {
                            return array(false,
                                         get_string('register_you_in_group', 'grouptool',
                                                    $message));
                        }
                    } else if (empty($movefromqueue) && $this->grouptool->allow_multiple
                               && ($this->grouptool->choose_min > ($marks + $userregs + $userqueues + 1))) {
                        // Cache data until enough registrations are made!
                        $record = new stdClass();
                        $record->agrpid = $agrpid;
                        $record->userid = $userid;
                        $record->timestamp = time();
                        $record->modified_by = -1;
                        $DB->insert_record('grouptool_registered', $record);
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('your_place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        }
                    } else {
                        if ($this->grouptool->allow_multiple) {
                            // Enough registrations have been made, save them!
                            if (!empty($marks)) {
                                $usermarks = $this->get_user_marks($userid);
                                foreach ($usermarks as $cur) {
                                    if ($cur->type == 'reg') {
                                        unset($cur->type);
                                        $cur->modified_by = $USER->id;
                                        $DB->update_record('grouptool_registered', $cur);
                                        if ($this->grouptool->immediate_reg) {
                                            groups_add_member($cur->groupid, $cur->userid);
                                        }
                                    } else {
                                        unset($cur->type);
                                        $DB->insert_record('grouptool_queued', $cur);
                                    }
                                }
                                $this->delete_user_marks($userid);
                            }
                        }
                        $record = new stdClass();
                        $record->agrpid = $agrpid;
                        $record->userid = $userid;
                        $record->timestamp = time();
                        $record->modified_by = $USER->id;
                        $record->id = $DB->insert_record('grouptool_registered', $record);
                        if ($this->grouptool->immediate_reg) {
                            groups_add_member($groupdata->id, $userid);
                        }
                        $record->groupid = $groupdata->id;
                        \mod_grouptool\event\registration_created::create_direct($this->cm, $record)->trigger();

                        $regcnt = $this->get_user_reg_count(0, $userid);
                        if (($this->grouptool->allow_multiple
                            && ($regcnt >= $this->grouptool->choose_max))
                            || !$this->grouptool->allow_multiple) {
                            $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                            if (count($agrps) > 0) {
                                $agrpids = array_keys($agrps);
                                list($sql, $params) = $DB->get_in_or_equal($agrpids);
                                $queues = $DB->get_records_sql('SELECT queued.*, agrp.groupid
                                                                  FROM {grouptool_queued} queued
                                                                  JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                                                                 WHERE  userid = ? AND agrpid '.$sql,
                                                               array_merge(array($userid), $params));
                                $DB->delete_records_select('grouptool_queued',
                                                           ' userid = ? AND agrpid '.$sql,
                                                           array_merge(array($userid), $params));
                                foreach ($queues as $cur) {
                                    // Trigger the event!
                                    \mod_grouptool\event\queue_entry_deleted::create_limit_violation($this->cm, $cur)->trigger();
                                }
                            }
                        }
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('register_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('register_you_in_group_success', 'grouptool',
                                                    $message));
                        }
                    }
                } else if ($grouptool->use_queue) {
                    // Try to queue!
                    if ($userqueues >= $grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                    $marks = $this->count_user_marks($userid);
                    if ($previewonly) {
                        if ($userid != $USER->id) {
                            return array(-1,
                                         get_string('queue_in_group', 'grouptool', $message));
                        } else {
                            return array(-1,
                                         get_string('queue_you_in_group', 'grouptool',
                                                    $message));
                        }
                    } else if ($this->grouptool->allow_multiple
                               && ($this->grouptool->choose_min > ($marks + $userregs + $userqueues + 1))) {
                        // Cache data until enough registrations are made!
                        // TODO events for place allocation?
                        $record = new stdClass();
                        $record->agrpid = $agrpid;
                        $record->grp_id = $groupdata->id;
                        $record->userid = $userid;
                        $record->timestamp = time();
                        $record->modified_by = -1;
                        $DB->insert_record('grouptool_registered', $record);
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('your_place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        }
                    } else {
                        if ($this->grouptool->allow_multiple) {
                            if (!empty($marks)) {
                                // Enough registrations have been made, save them!
                                $usermarks = $this->get_user_marks($userid);
                                foreach ($usermarks as $cur) {
                                    if ($cur->type == 'reg') {
                                        unset($cur->type);
                                        if ($this->grouptool->immediate_reg) {
                                            groups_add_member($cur->groupid, $cur->userid);
                                        }
                                        // Premature triggering because of unsetting $cur->groupid afterwards.
                                        \mod_grouptool\event\registration_created::create_direct($this->cm, $cur)->trigger();
                                        unset($cur->groupid);
                                        $cur->modified_by = $USER->id;
                                        $DB->update_record('grouptool_registered', $cur);
                                    } else {
                                        unset($cur->type);
                                        // Trigger the event!
                                        \mod_grouptool\event\queue_entry_created::create_direct($this->cm, $cur)->trigger();
                                        unset($cur->groupid);
                                        $cur->id = $DB->insert_record('grouptool_queued', $cur);
                                    }
                                }
                                // TODO: Event for marks deletion?
                                $this->delete_user_marks($userid);
                            }
                        }
                        $record = new stdClass();
                        $record->agrpid = $agrpid;
                        $record->userid = $userid;
                        $record->timestamp = time();
                        $record->id = $DB->insert_record('grouptool_queued', $record);
                        // Trigger the event!
                        $record->groupid = $groupdata->id;
                        \mod_grouptool\event\queue_entry_created::create_direct($this->cm, $record)->trigger();
                        if ($userid != $USER->id) {
                            return array(-1,
                                         get_string('queue_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(-1,
                                         get_string('queue_you_in_group_success', 'grouptool',
                                                    $message));
                        }
                    }

                } else {
                    // Group is full!
                    if ($userid != $USER->id) {
                        return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                    } else {
                        return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                    }
                }
            } else {
                // Register him!
                if ($previewonly) {
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('register_in_group', 'grouptool', $message));
                    } else {
                        return array(false,
                                     get_string('register_you_in_group', 'grouptool',
                                                $message));
                    }
                } else if ($this->grouptool->allow_multiple
                           && ($this->grouptool->choose_min > ($marks + $userregs + $userqueues + 1))) {
                    // TODO Place allocation event!
                    // Cache data until enough registrations are made!
                    $record = new stdClass();
                    $record->agrpid = $agrpid;
                    $record->grp_id = $groupdata->id;
                    $record->userid = $userid;
                    $record->timestamp = time();
                    $record->modified_by = -1;
                    $DB->insert_record('grouptool_registered', $record);
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('place_allocated_in_group_success', 'grouptool',
                                                $message));
                    } else {
                        return array(false,
                                     get_string('your_place_allocated_in_group_success', 'grouptool',
                                                $message));
                    }
                } else {
                    if ($this->grouptool->allow_multiple) {
                        // Enough registrations have been made, save them!
                        if ($marks) {
                            $usermarks = $this->get_user_marks($userid);
                            foreach ($usermarks as $cur) {
                                if ($cur->type == 'reg') {
                                    unset($cur->type);
                                    $cur->modified_by = $USER->id;
                                    if ($this->grouptool->immediate_reg) {
                                        groups_add_member($cur->groupid, $cur->userid);
                                    }
                                    // Premature triggering because of unsetting $cur->groupid afterwards!
                                    \mod_grouptool\event\registration_created::create_direct($this->cm, $cur)->trigger();
                                    unset($cur->groupid);
                                    $DB->update_record('grouptool_registered', $cur);
                                } else {
                                    unset($cur->type);
                                    $DB->insert_record('grouptool_queued', $cur);
                                    // Trigger the event!
                                    \mod_grouptool\event\queue_entry_created::create_direct($this->cm, $cur)->trigger();
                                }
                            }
                            // TODO event for deletion of users marks?
                            $this->delete_user_marks($userid);
                        }
                    }
                    $record = new stdClass();
                    $record->agrpid = $agrpid;
                    $record->userid = $userid;
                    $record->timestamp = time();
                    $record->modified_by = $USER->id;
                    $record->id = $DB->insert_record('grouptool_registered', $record);
                    if ($this->grouptool->immediate_reg) {
                        groups_add_member($groupdata->id, $userid);
                    }
                    // Trigger the event!
                    $record->groupid = $groupdata->id;
                    \mod_grouptool\event\registration_created::create_direct($this->cm, $record)->trigger();

                    $regcnt = $this->get_user_reg_count(0, $userid);
                    if (($this->grouptool->allow_multiple
                        && ($regcnt >= $this->grouptool->choose_max))
                        || !$this->grouptool->allow_multiple) {
                        $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                        if (count($agrps) > 0) {
                            $agrpids = array_keys($agrps);
                            list($sql, $params) = $DB->get_in_or_equal($agrpids);
                            $queues = $DB->get_records_sql('SELECT queued.*, agrp.groupid
                                                              FROM {grouptool_queued} queued
                                                              JOIN {grouptool_agrps} agrp ON queued.agrpid = agrp.id
                                                             WHERE queued.userid = ? AND queued.agrpid '.$sql,
                                                           array_merge(array($userid), $params));
                            $DB->delete_records_select('grouptool_queued',
                                                       ' userid = ? AND agrpid '.$sql,
                                                       array_merge(array($userid), $params));
                            foreach ($queues as $cur) {
                                // Trigger the event!
                                \mod_grouptool\event\queue_entry_deleted::create_limit_violation($this->cm, $cur)->trigger();
                            }
                        }
                    }
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('register_in_group_success', 'grouptool',
                                                $message));
                    } else {
                        return array(false,
                                     get_string('register_you_in_group_success', 'grouptool',
                                                $message));
                    }
                }
            }
        } else {
            return array(true, get_string('error_getting_data', 'grouptool'));
        }
    }

    /**
     * returns number of queue-entries for a particular user in a particular grouptool-instance
     *
     * @global object $DB
     * @global object $USER
     * @param int $grouptoolid optional stats from which grouptool-instance should be obtained?
     *                                  uses this->grouptool->id if zero
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     */
    private function get_user_queues_count($grouptoolid=0, $userid=0) {
        global $DB, $USER;
        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_queued}
                                       WHERE userid = ? AND agrpid '.$sql, $params);
    }

    /**
     * returns number of reg-entries for a particular user in a particular grouptool-instance
     *
     * @global object $DB
     * @global object $USER
     * @param int $grouptoolid optional stats from which grouptool-instance should be obtained?
     *                                  uses this->grouptool->id if zero
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     */
    private function get_user_reg_count($grouptoolid=0, $userid=0) {
        global $DB, $USER;
        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_registered}
                                       WHERE modified_by >= 0 AND userid = ? AND agrpid '.$sql, $params);
    }

    /**
     * helperfunction compares to objects using a particular timestamp-property
     *
     * @param object $a object containing timestamp property
     * @param object $b object containing timestamp property
     * @return int 0 if equal, +1 if $a->timestamp > $b->timestamp or -1 if otherwise
     */
    private function cmptimestamp($a, $b) {
        if ($a->timestamp == $b->timestamp) {
            return 0;
        } else {
            return $a->timestamp > $b->timestamp ? 1 : -1;
        }
    }

    /**
     * returns rank in queue for a particular user
     * if $data is an array uses array (like queue/reg-info returned by {@link get_active_groups()})
     * to determin rank otherwise if $data is an integer uses DB-query to get queue rank in
     * active group with id == $data
     *
     * @global object $DB
     * @param array|int $data array with regs/queues for a group like returned
     *                        by get_active_groups() or agrpid
     * @param int $userid user for whom data should be returned
     * @return int rank in queue/registration (registration only via $data-array)
     */
    private function get_rank_in_queue($data=0, $userid=0) {
        global $DB;
        if (is_array($data)) { // It's the queue itself!
            uasort($data, array(&$this, "cmptimestamp"));
            $i = 1;
            foreach ($data as $entry) {
                if ($entry->userid == $userid) {
                    return $i;
                } else {
                    $i++;
                }
            }
            return false;
        } else if (!empty($data)) { // It's an active-group-id, so we gotta get the queue data!
            $params = array('agrpid' => $data,
                    'userid' => !empty($userid) ? $userid : $USER->id);
            $sql = "SELECT count(b.id) rank
            FROM {grouptool_queued} a
            INNER JOIN {grouptool_queued} b
            ON b.timestamp <= a.timestamp
            WHERE a.agrpid = :agrpid AND a.userid = :userid";
        } else {
            return null;
        }

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * returns object with information about registrations/queues for each group
     * (optional with userdata)
     * if $user == 0 no userdata is returned
     * else if $user == null data about $USERs registrations/queues is added
     * else data about $userids registrations/queues is added
     *
     * @global object $USER
     * @global object $DB
     * @param int $userid id of user for whom data should be added
     *                    or 0 (=$USER) or null (=no userdata)
     * @return object object containing information about active groups
     */
    public function get_registration_stats($userid=null) {
        global $USER, $DB;
        $return = new stdClass();
        $return->group_places = 0;
        $return->free_places = 0;
        $return->occupied_places = 0;
        $return->users = 0;
        $return->registered = array();
        $return->queued = array();
        $return->queued_users = 0;
        $return->reg_users = 0;

        switch ($userid) {
            case null:
                $userid = $USER->id;
            default:
                $groups = $this->get_active_groups(false, false);
                break;
            case 0:
                $groups = $this->get_active_groups();
        }

        foreach ($groups as $group) {
            $group = $this->get_active_groups(true, true, $group->agrpid, $group->id);
            $group = current($group);
            if ($this->grouptool->use_size) {
                $return->group_places += $group->grpsize;
            }
            $return->occupied_places += count($group->registered);
            if ($userid != 0) {
                $regrank = $this->get_rank_in_queue($group->registered, $userid);
                if (!empty($regrank)) {
                    $regdata = new stdClass();
                    $regdata->rank = $regrank;
                    $regdata->grpname = $group->name;
                    $regdata->agrpid = $group->agrpid;
                    reset($group->registered);
                    do {
                        $current = current($group->registered);
                        $regdata->timestamp = $current->timestamp;
                        next($group->registered);
                    } while ($current->userid != $userid);
                    $regdata->id = $group->id;
                    $return->registered[] = $regdata;
                }

                $queuerank = $this->get_rank_in_queue($group->queued, $userid);
                if (!empty($queuerank)) {
                    $queuedata = new stdClass();
                    $queuedata->rank = $queuerank;
                    $queuedata->grpname = $group->name;
                    $queuedata->agrpid = $group->agrpid;
                    reset($group->queued);
                    do {
                        $current = current($group->queued);
                        $queuedata->timestamp = $current->timestamp;
                        next($group->queued);
                    } while ($current->userid != $userid);
                    $queuedata->id = $group->id;
                    $return->queued[] = $queuedata;
                }
            }
        }
        $return->free_places = ($this->grouptool->use_size) ?
                                   ($return->group_places - $return->occupied_places) :
                                   null;
        $return->users = count_enrolled_users($this->context, 'mod/grouptool:register');

        $agrps = $DB->get_records('grouptool_agrps', array('grouptoolid' => $this->cm->instance, 'active' => 1));
        if (is_array($agrps) && count($agrps) >= 1) {
            $agrpids = array_keys($agrps);
            list($inorequal, $params) = $DB->get_in_or_equal($agrpids);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_registered}
                     WHERE modified_by >= 0 AND agrpid ".$inorequal;
            $return->reg_users = $DB->count_records_sql($sql, $params);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_queued}
                     WHERE agrpid ".$inorequal;
            $return->queued_users = $DB->count_records_sql($sql, $params);
        } else {
            $return->reg_users = 0;
        }
        $return->notreg_users = $return->users - $return->reg_users;

        return $return;
    }

    /**
     * resolves queues by filling empty group places in defined order with students from the queue
     *
     * @todo there's a bug which prevents deletion of some queue entries, only happened on
     *       development system with admin-users account several times, whatch out for the future
     * @todo sometimes not every queue entry is resolved, happened unregularly on development system
     *       watch in production system
     *
     * @global object $OUTPUT
     * @global object $DB
     * @global object $USER
     * @param string $mode optional, without function, reserved for optional later implementation
     *                     (random/alphabetical/... distribution, etc)
     * @param bool $previewonly show only preview of actions
     * @return array ($error, $message)
     */
    public function resolve_queues($mode = 'sortorder', $previewonly = false) {
        global $OUTPUT, $DB, $USER;
        $error = false;
        $returntext = "";

        // Trigger event!
        \mod_grouptool\event\dequeuing_started::create_from_object($this->cm)->trigger();

        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
            $grouptool = $this->grouptool;
            $context = $this->context;
        } else {
            $cmid = get_coursemodule_from_instance('grouptool', $grouptoolid);
            $grouptool = $DB->get_record('grouptool', array('id' => $grouptoolid), '*', MUST_EXIST);
            $context = context_module::instance($cmid->id);
        }

        require_capability('mod/grouptool:register_students', $context);

        $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);

        if (!empty($agrps)) {
            $agrpids = array_keys($agrps);
            list($agrpssql, $agrpsparam) = $DB->get_in_or_equal($agrpids);
            $agrpsfiltersql = " AND agrp.id ".$agrpssql;
            $agrpsfilterparams = array_merge(array($grouptool->id), $agrpsparam);
            // Get queue-entries (sorted by timestamp)!
            if (!empty($grouptool->allow_multiple)) {
                $queuedsql = " WHERE queued.agrpid ".$agrpssql." ";
                $queuedparams = array_merge($agrpsparam, $agrpsparam);

                $queueentries = $DB->get_records_sql("
                      SELECT queued.id, MAX(queued.agrpid) agrpid, MAX(queued.userid) userid,
                             MAX(queued.timestamp), (COUNT(DISTINCT reg.id) < ?) priority
                        FROM {grouptool_queued} queued
                   LEFT JOIN {grouptool_registered} reg ON queued.userid = reg.userid AND reg.agrpid ".$agrpssql.
                                                                                    " AND reg.modified_by >= 0
                    ".$queuedsql."
                    GROUP BY queued.id
                    ORDER BY priority DESC, queued.timestamp ASC",
                    array_merge(array($grouptool->choose_min), $queuedparams));
            } else {
                $queuedsql = " WHERE queued.agrpid ".$agrpssql." ";
                $queuedparams = $agrpsparam;
                $queueentries = $DB->get_records_sql("SELECT *, '1' priority
                                                        FROM {grouptool_queued} queued".
                                                             $queuedsql.
                                                   "ORDER BY timestamp ASC",
                                                     $queuedparams);
            }
            $userregs = $DB->get_records_sql_menu('SELECT reg.userid, COUNT(DISTINCT reg.id)
                                                     FROM {grouptool_registered} reg
                                                    WHERE reg.agrpid '.$agrpssql.' AND modified_by >= 0
                                                 GROUP BY reg.userid', $agrpsparam);
        } else {
            return array(true, get_string('no_active_groups', 'grouptool'));
        }

        // Get group entries (sorted by sort-order)!
        $groupsdata = $DB->get_records_sql("
                SELECT agrp.id id, MAX(agrp.groupid) groupid, MAX(agrp.grpsize) grpsize,
                       COUNT(DISTINCT reg.id) registered
                  FROM {grouptool_agrps} agrp
             LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrp.id AND modified_by >= 0
                 WHERE agrp.grouptoolid = ?".$agrpsfiltersql."
              GROUP BY agrp.id
              ORDER BY agrp.sort_order ASC", $agrpsfilterparams);

        $i = 0;

        if (!empty($groupsdata) && !empty($queueentries)) {
            $groupsnav = array_keys($groupsdata);
            $queuenav = array_keys($queueentries);
            $planned = new stdClass();
            $curgroup = null;
            $maxregs = !empty($this->grouptool->allow_multiple) ? $this->grouptool->choose_max : 1;
            reset($groupsdata);
            foreach ($queueentries as $queue) {
                // Get first non-full group!
                while (($curgroup == null)
                       ||($curgroup->grpsize <= $curgroup->registered)) {
                    if ($curgroup === null) {
                        $curgroup = current($groupsdata);
                    } else {
                        $curgroup = next($groupsdata);
                    }
                    if ($curgroup === false) {
                        $error = true;
                        $returntext .= html_writer::tag('div',
                                                        get_string('all_groups_full',
                                                                   'grouptool',
                                                                   $queue->userid),
                                                        array('class' => 'error'));
                        return array($error, $returntext);
                    } else {
                        $curgroup->grpsize = ($grouptool->use_individual && !empty($curgroup->grpsize)) ?
                                               $curgroup->grpsize :
                                               $grouptool->grpsize;
                    }
                }

                if (!isset($planned->{$queue->userid})) {
                    $planned->{$queue->userid} = array();
                }

                // If user has got too many regs allready!
                if (!empty($userregs[$queue->userid]) && ($userregs[$queue->userid] >= $maxregs)) {
                    $returntext .= html_writer::tag('div', get_string('too_many_regs', 'grouptool'),
                                                    array('class' => 'error'));
                    $error = true;
                    // Continue with next user/queue-entry!
                    continue;
                }

                while ($DB->record_exists('grouptool_registered', array('agrpid' => $curgroup->id,
                                                                        'userid' => $queue->userid))
                       || in_array($curgroup->id, $planned->{$queue->userid})
                       || $curgroup->registered >= $curgroup->grpsize) {
                    $curgroup = next($groupsdata);
                    $i++;
                    if ($curgroup === false) {
                        break; // No group left for this user!
                    }
                }

                if ($curgroup !== false) {
                    // Register him or mark as planed!
                    if ($previewonly) {
                        list($curerror, $curtext) = $this->register_in_agrp($curgroup->id,
                                                                            $queue->userid,
                                                                            true,
                                                                            true);
                        if (!$curerror) {
                            $planned->{$queue->userid}[] = $curgroup->id;
                        }
                        $class = $curerror ? 'error' : 'success';
                        $data = new stdClass();
                        $data->userid = $queue->userid;
                        $data->agrpid = $queue->agrpid;
                        $data->current_grp = $curgroup->id;
                        $data->current_text = $curtext;
                        $movetext = get_string('user_move_prev', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movetext.' ('.$curtext.')',
                                                        array('class' => $class));
                        if (!isset($status[$queue->userid])) {
                            $status[$queue->userid] = new stdClass();
                        }
                        $status[$queue->userid]->error = $curerror;
                        $error = $error || $curerror;
                        $curgroup->registered++;
                    } else {
                        list($curerror, $curtext) = $this->register_in_agrp($curgroup->id,
                                                                            $queue->userid,
                                                                            false,
                                                                            true);
                        $class = $curerror ? 'error' : 'success';
                        $data = new stdClass();
                        $data->userid = $queue->userid;
                        $data->agrpid = $queue->agrpid;
                        $data->current_grp = $curgroup->id;
                        $data->current_text = $curtext;
                        $movedtext = get_string('user_moved', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movedtext.' ('.$curtext.')',
                                                        array('class' => $class));
                        $curgroup->registered++;
                        $error = $error || $curerror;
                        $attr = array('id'     => $queue->id,
                                      'userid' => $queue->userid,
                                      'agrpid' => $queue->agrpid);
                        // Delete queue entry if successfull or print message!
                        $queues = $DB->get_records('grouptool_queued', $attr);
                        $DB->delete_records('grouptool_queued', $attr);

                        // Log user moved!
                        $queue->groupid = $DB->get_field('grouptool_agrps', 'groupid', array('id' => $queue->agrpid), MUST_EXIST);
                        $to = new stdClass();
                        $to->agrpid = $curgroup->id;
                        $to->userid = $queue->userid;
                        $to->groupid = $DB->get_field('grouptool_agrps', 'groupid', array('id' => $curgroup->id), MUST_EXIST);
                        $to->id = $DB->get_field('grouptool_registered', 'id',
                                                 array('agrpid' => $to->agrpid, 'userid' => $to->userid), MUST_EXIST);
                        \mod_grouptool\event\user_moved::move($this->cm, $queue, $to)->trigger();

                        if ($DB->record_exists('grouptool_queued', $attr)) {
                            $returntext .= "Could not delete!";
                        }
                    }
                }

                while ($i !== 0) {
                    $curgroup = prev($groupsdata);
                    $i--;
                }
            }
        }

        if (empty($returntext)) {
            $returntext = get_string('no_queues_to_resolve', 'grouptool');
            $error = false;
        }

        return array($error, $returntext);
    }

    public function get_user_marks($userid=0) {
        global $DB, $USER, $OUTPUT;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $agrps = $DB->get_fieldset_select('grouptool_agrps', 'id',
                                          'grouptoolid = ?',
                                          array($this->cm->instance));

        list($agrpssql, $params) = $DB->get_in_or_equal($agrps);
        $params[] = $userid;

        $sql = 'SELECT reg.id, reg.agrpid, reg.userid, reg.timestamp,
                       agrp.groupid
                  FROM {grouptool_registered} reg
                  JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id
                 WHERE reg.agrpid '.$agrpssql.'
                   AND modified_by = -1
                   AND userid = ?';

        $marks = $DB->get_records_sql($sql, $params);
        foreach ($marks as $id => $cur) {
            $groupdata = $this->get_active_groups(true, true, 0, $cur->groupid);
            $groupdata = current($groupdata);
            if ($this->grouptool->use_size) {
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    $marks[$id]->type = 'reg';
                } else if ($this->grouptool->use_queue) {
                    $marks[$id]->type = 'queue';
                } else {
                    // Place occupied in the meanwhile, must look for another group!
                    $info = new stdClass();
                    $info->grpname = groups_get_group_name($cur->groupid);
                    echo $OUTPUT->notification(get_string('already_occupied',
                                                          'grouptool', $info),
                                               'notifyproblem');
                    $DB->delete_records('grouptool_registered', array('id' => $id));
                    unset($marks[$id]);
                }
            } else {
                $marks[$id]->type = 'reg';
            }
        }

        return $marks;
    }

    public function delete_user_marks($userid=0) {
        global $DB;

        $marks = $this->get_user_marks($userid);
        if (!empty($marks)) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($marks));
            $select = 'id '.$select;
            $DB->delete_records_select('grouptool_registered', $select, $params);
        }
    }

    public function count_user_marks($userid=0) {
        $marks = $this->get_user_marks($userid);

        return count($marks);
    }

    public function grpmarked($agrpid, $userid=0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        return $DB->record_exists('grouptool_registered',
                                  array('agrpid' => $agrpid,
                                        'userid' => $userid,
                                        'modified_by' => -1));
    }

    /**
     * view selfregistration-tab
     *
     * @global object $OUTPUT
     * @global object  $DB
     * @global object  $CFG
     * @global object  $USER
     * @global object  $PAGE
     */
    public function view_selfregistration() {
        global $OUTPUT, $DB, $CFG, $USER, $PAGE, $SESSION;

        $userid = $USER->id;

        $regopen = ($this->grouptool->allow_reg
                     && (($this->grouptool->timedue == 0)
                         || (time() < $this->grouptool->timedue))
                     && (time() > $this->grouptool->timeavailable));

        // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed!
            $hideform = 0;
            $action = optional_param('action', 'reg', PARAM_ALPHA);
            if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                list($error, $message) = $this->unregister_from_agrp($agrpid, $USER->id);
            } else if ($action == 'reg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Register user and get feedback!
                list($error, $message) = $this->register_in_agrp($agrpid, $USER->id);
            } else if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                $mode = optional_param('mode', 'std', PARAM_ALPHA);
                list($error, $message) = $this->resolve_queues($mode);
                if ($error == -1) {
                    $error = true;
                }
            }
            if ($error === true) {
                echo $OUTPUT->notification($message, 'notifyproblem');
            } else {
                echo $OUTPUT->notification($message, 'notifysuccess');
            }
        } else if (data_submitted() && confirm_sesskey()) {

            // Display confirm-dialog!
            $hideform = 1;
            $reg = optional_param_array('reg', null, PARAM_INT);
            if ($reg != null) {
                $agrpid = array_keys($reg);
                $agrpid = reset($agrpid);
                $action = 'reg';
            }
            $unreg = optional_param_array('unreg', null, PARAM_INT);
            if ($unreg != null) {
                $agrpid = array_keys($unreg);
                $agrpid = reset($agrpid);
                $action = 'unreg';
            }
            $resolvequeues = optional_param('resolve_queues', 0, PARAM_BOOL);
            if (!empty($resolvequeues)) {
                $action = 'resolvequeues';
            }

            $attr = array();
            if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                $mode = optional_param('mode', 'random', PARAM_ALPHA);
                list($error, $confirmmessage) = $this->resolve_queues($mode, true); // Try only!
            } else if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $attr['group'] = $agrpid;
                // Try only!
                list($error, $confirmmessage) = $this->unregister_from_agrp($agrpid,
                                                                            $USER->id, true);
            } else {
                require_capability('mod/grouptool:register', $this->context);
                $action = 'reg';
                $attr['group'] = $agrpid;
                // Try only!
                list($error, $confirmmessage) = $this->register_in_agrp($agrpid,
                                                                        $USER->id, true);
            }
            $attr['confirm'] = '1';
            $attr['action'] = $action;
            $attr['sesskey'] = sesskey();

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if (($error === true) && ($action != 'resolvequeues')) {
                $continue->remove_params('confirm', 'group');
                $continue = new single_button($continue, get_string('continue'), 'get');
                $cancel = null;
            }
            echo $this->confirm($confirmmessage, $continue, $cancel);
        } else {
            $hideform = 0;
        }

        if (empty($hideform)) {
            /*
             * we need a new moodle_url-Object because
             * $PAGE->url->param('sesskey', sesskey());
             * won't set sesskey param in $PAGE->url?!?
             */
            $url = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
            $formattr = array(
                    'method' => 'post',
                    'action' => $url->out_omit_querystring(),
                    'id'     => 'registration_form',
                    'class'  => 'mform');
            echo html_writer::start_tag('form', $formattr);
            echo html_writer::start_tag('div', array('class' => 'clearfix'));
            echo html_writer::input_hidden_params($url);

            $regstat = $this->get_registration_stats($USER->id);

            if (!empty($this->grouptool->timedue) && (time() >= $this->grouptool->timedue) &&
                    has_capability('mod/grouptool:register_students', $this->context)) {
                if ($regstat->queued_users > 0) {
                    // Insert queue-resolving button!
                    $attr = array(
                            'type'  => 'submit',
                            'name'  => 'resolve_queues',
                            'value' => '1');
                    $resolvequeuebutton = html_writer::tag('button',
                                                           get_string('resolve_queue', 'grouptool'),
                                                           $attr);
                    $resolvequeue = html_writer::tag('div',
                                                     get_string('resolve_queue_title',
                                                                'grouptool'),
                                                     array('class' => 'fitemtitle')).
                                    html_writer::tag('div', $resolvequeuebutton,
                                                     array('class' => 'felement'));
                    $resolvequeue = html_writer::tag('div', $resolvequeue,
                                                     array('class' => 'fitem'));
                    $resolvequeuelegend = html_writer::tag('legend',
                                                           get_string('resolve_queue_legend',
                                                                      'grouptool'));
                    $resolvequeueelement = html_writer::tag('div', $resolvequeue,
                                                            array('class' => 'fcontainer'));
                    echo html_writer::tag('fieldset', $resolvequeuelegend.
                                                      $resolvequeueelement,
                                          array('class' => 'clearfix'));
                }
            }

            if (!empty($this->grouptool->use_size)) {
                $placestats = $regstat->group_places.'&nbsp;'.get_string('total', 'grouptool');
            } else {
                $placestats = '∞&nbsp;'.get_string('total', 'grouptool');
            }
            if (($regstat->free_places != null) && !empty($this->grouptool->use_size)) {
                $placestats .= ' / '.$regstat->free_places.'&nbsp;'.
                                get_string('free', 'grouptool');
            } else {
                $placestats .= ' / ∞&nbsp;'.get_string('free', 'grouptool');
            }
            if ($regstat->occupied_places != null) {
                $placestats .= ' / '.$regstat->occupied_places.'&nbsp;'.
                                get_string('occupied', 'grouptool');
            }
            $registrationinfo = html_writer::tag('div', get_string('group_places', 'grouptool').
                                                        $OUTPUT->help_icon('group_places',
                                                                           'grouptool'),
                                                 array('class' => 'fitemtitle')).
                                html_writer::tag('div', $placestats,
                                                 array('class' => 'felement'));
            $generalinfo = html_writer::tag('div', $registrationinfo,
                                                   array('class' => 'fitem'));

            $registrationinfo = html_writer::tag('div', get_string('number_of_students',
                                                                   'grouptool'),
                                                 array('class' => 'fitemtitle')).
                                html_writer::tag('div', $regstat->users,
                                                 array('class' => 'felement'));
            $generalinfo .= html_writer::tag('div', $registrationinfo,
                                                     array('class' => 'fitem'));

            if (($this->grouptool->allow_multiple &&
                    (count($regstat->registered) < $this->grouptool->choose_min))
                    || (!$this->grouptool->allow_multiple && !count($regstat->registered))) {
                if ($this->grouptool->allow_multiple) {
                    $missing = ($this->grouptool->choose_min - count($regstat->registered));
                    $stringlabel = ($missing > 1) ? 'registrations_missing' : 'registration_missing';
                } else {
                    $missing = 1;
                    $stringlabel = 'registration_missing';
                }
                $missingtext = get_string($stringlabel, 'grouptool', $missing);
            } else {
                $missingtext = "";
            }

            if (!empty($regstat->registered)) {
                foreach ($regstat->registered as $registration) {
                    if (empty($registrationscumulative)) {
                        $registrationscumulative = $registration->grpname.
                                                   ' ('.$registration->rank.')';
                    } else {
                        $registrationscumulative .= ', '.$registration->grpname.
                                                    ' ('.$registration->rank.')';
                    }
                }
                $registrationinfo = html_writer::tag('div', get_string('registrations',
                                                                       'grouptool'),
                                                     array('class' => 'fitemtitle')).
                                    html_writer::tag('div', html_writer::tag('div', $missingtext).
                                                            $registrationscumulative,
                                                     array('class' => 'felement'));
                $generalinfo .= html_writer::tag('div', $registrationinfo, array('class' => 'fitem'));
            } else {
                $registrationinfo = html_writer::tag('div', get_string('registrations',
                                                                       'grouptool'),
                                                     array('class' => 'fitemtitle')).
                                    html_writer::tag('div', html_writer::tag('div', $missingtext).
                                                            get_string('not_registered',
                                                                       'grouptool'),
                                                     array('class' => 'felement'));
                $generalinfo .= html_writer::tag('div', $registrationinfo, array('class' => 'fitem'));
            }

            if (!empty($regstat->queued)) {
                foreach ($regstat->queued as $queue) {
                    if (empty($queuescumulative)) {
                        $queuescumulative = $queue->grpname.' ('.$queue->rank.')';
                    } else {
                        $queuescumulative .= ', '.$queue->grpname.' ('.$queue->rank.')';
                    }
                }

                $registrationinfo = html_writer::tag('div', get_string('queues', 'grouptool'),
                                                     array('class' => 'fitemtitle')).
                                    html_writer::tag('div', $queuescumulative,
                                                     array('class' => 'felement'));
                $generalinfo .= html_writer::tag('div', $registrationinfo,
                                                 array('class' => 'fitem'));
            }

            if (!empty($this->grouptool->timeavailable)) {
                $timeavailable = html_writer::tag('div', get_string('availabledate', 'grouptool'),
                                                  array('class' => 'fitemtitle')).
                                 html_writer::tag('div',
                                                  userdate($this->grouptool->timeavailable,
                                                           get_string('strftimedatetime')),
                                                  array('class' => 'felement'));
                $generalinfo .= html_writer::tag('div', $timeavailable,
                                                 array('class' => 'fitem'));
            }

            $timedue = html_writer::tag('div', get_string('registrationdue', 'grouptool'),
                                        array('class' => 'fitemtitle'));
            if (!empty($this->grouptool->timedue)) {
                $timedue .= html_writer::tag('div',
                                             userdate($this->grouptool->timedue,
                                                      get_string('strftimedatetime')),
                                             array('class' => 'felement'));
            } else {
                $timedue .= html_writer::tag('div', get_string('noregistrationdue', 'grouptool'),
                                             array('class' => 'felement'));
            }
            $generalinfo .= html_writer::tag('div', $timedue, array('class' => 'fitem'));

            if (!empty($this->grouptool->allow_unreg)) {
                $generalinfo .= html_writer::tag('div', html_writer::tag('div',
                                                                         get_string('unreg_is', 'grouptool'),
                                                                         array('class' => 'fitemtitle')).
                                                        html_writer::tag('div',
                                                                         get_string('allowed', 'grouptool'),
                                                                         array('class' => 'felement')),
                                                 array('class' => 'fitem'));
            } else {
                $generalinfo .= html_writer::tag('div', html_writer::tag('div',
                                                                         get_string('unreg_is',
                                                                                    'grouptool'),
                                                                         array('class' => 'fitemtitle')).
                                                        html_writer::tag('div',
                                                                         get_string('not_permitted',
                                                                                    'grouptool'),
                                                                         array('class' => 'felement')),
                                                 array('class' => 'fitem'));
            }

            if (!empty($this->grouptool->allow_multiple)) {
                $minmaxtitle = html_writer::tag('div',
                                                get_string('choose_minmax_title', 'grouptool'),
                                                array('class' => 'fitemtitle'));
                if ($this->grouptool->choose_min && $this->grouptool->choose_max) {
                    $data = array('min' => $this->grouptool->choose_min,
                                  'max' => $this->grouptool->choose_max);
                    $minmaxtext = html_writer::tag('div',
                                                   get_string('choose_min_max_text', 'grouptool',
                                                              $data),
                                                   array('class' => 'felement'));
                    $class = ' choose_min choose_max';
                } else if ($this->grouptool->choose_min) {
                    $minmaxtext = html_writer::tag('div',
                                                    get_string('choose_min_text', 'grouptool',
                                                               $this->grouptool->choose_min),
                                                    array('class' => 'felement'));
                    $class = ' choose_min';
                } else if ($this->grouptool->choose_max) {
                    $minmaxtext = html_writer::tag('div',
                                                    get_string('choose_max_text', 'grouptool',
                                                               $this->grouptool->choose_max),
                                                    array('class' => 'felement'));
                    $class = ' choose_max';
                }
                $generalinfo .= html_writer::tag('div',
                                                         $minmaxtitle.$minmaxtext,
                                                         array('class' => 'fitem '.$class));
            }

            if (!empty($this->grouptool->use_queue)) {
                $generalinfo .= html_writer::tag('div', html_writer::tag('div',
                                                                         get_string('queueing_is', 'grouptool'),
                                                                         array('class' => 'fitemtitle')).
                                                        html_writer::tag('div',
                                                                         get_string('active', 'grouptool'),
                                                                         array('class' => 'felement')),
                                                 array('class' => 'fitem'));
            }

            $generalinfolegend = html_writer::tag('legend', get_string('general_information',
                                                                       'grouptool'));
            if (has_capability('mod/grouptool:view_description', $this->context)) {
                echo html_writer::tag('fieldset',
                                      $generalinfolegend.
                                      html_writer::tag('div', $generalinfo,
                                                       array('class' => 'fcontainer')),
                                      array('class' => 'clearfix'));

                // Intro-text if set!
                if (($this->grouptool->alwaysshowdescription
                     || (time() > $this->grouptool->timeavailable))
                    && $this->grouptool->intro) {
                    $intro = format_module_intro('grouptool', $this->grouptool, $this->cm->id);
                    echo html_writer::tag('fieldset',
                                          html_writer::tag('legend',
                                                           get_string('intro', 'grouptool')).
                                          html_writer::tag('div', $intro,
                                                           array('class' => 'fcontainer')),
                                          array('class' => 'clearfix'));
                }
            }
            $groups = $this->get_active_groups();

            // Student view!
            if (has_capability("mod/grouptool:view_groups", $this->context)) {

                // Prepare formular-content for registration-action!
                foreach ($groups as $key => &$group) {
                    $group = $this->get_active_groups(true, true, 0, $key);
                    $group = current($group);

                    $registered = count($group->registered);
                    $grpsize = ($this->grouptool->use_size) ? $group->grpsize : "∞";
                    $grouphtml = html_writer::tag('span', get_string('registered', 'grouptool').
                                                          ": ".$registered."/".$grpsize,
                                                  array('class' => 'fillratio'));
                    if ($this->grouptool->use_queue) {
                        $queued = count($group->queued);
                        $grouphtml .= html_writer::tag('span', get_string('queued', 'grouptool').
                                                               " ".$queued,
                                                       array('class' => 'queued'));
                    }
                    if ($this->grouptool->show_members) {
                        $grouphtml .= $this->render_members_link($group->agrpid, $group->name);
                    }
                    if (!empty($group->registered)) {
                        $regrank = $this->get_rank_in_queue($group->registered, $USER->id);
                    } else {
                        $regrank = false;
                    }
                    if (!empty($group->queued)) {
                        $queuerank = $this->get_rank_in_queue($group->queued, $USER->id);
                    } else {
                        $queuerank = false;
                    }
                    /* If we include inactive groups and there's someone registered in one of these,
                     * the label gets displayed incorrectly.
                     */
                    $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ? AND active = 1",
                                                        array($this->grouptool->id));
                    list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
                    array_unshift($params, $userid);
                    $userregs = $DB->count_records_select('grouptool_registered',
                                                          "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql, $params);
                    $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
                    $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
                    $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
                    if (!empty($group->registered)
                        && $this->get_rank_in_queue($group->registered, $userid) != false) {
                        // User is allready registered --> unreg button!
                        if ($this->grouptool->allow_unreg) {
                            $label = get_string('unreg', 'grouptool');
                            $buttonattr = array('type'  => 'submit',
                                                'name'  => 'unreg['.$group->agrpid.']',
                                                'value' => $group->agrpid,
                                                'class' => 'unregbutton');
                            if ($regopen && ($userregs + $userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('registered_on_rank',
                                                                  'grouptool', $regrank),
                                                       array('class' => 'rank'));
                    } else if (!empty($group->queued)
                        && $this->get_rank_in_queue($group->queued, $userid) != false) {
                        // We're sorry, but user's already queued in this group!
                        if ($this->grouptool->allow_unreg) {
                            $label = get_string('unqueue', 'grouptool');
                            $buttonattr = array('type'  => 'submit',
                                                'name'  => 'unreg['.$group->agrpid.']',
                                                'value' => $group->agrpid,
                                                'class' => 'unregbutton');
                            if ($regopen && ($userregs + $userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('queued_on_rank',
                                                                  'grouptool', $queuerank),
                                                       array('class' => 'rank'));
                    } else if ($this->grpmarked($group->agrpid)) {
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('grp_marked', 'grouptool'),
                                                       array('class' => 'rank'));
                    } else if ($this->grouptool->allow_unreg
                               && (($userqueues == 1 && $userregs == $max - 1)
                                   || ($userregs + $userqueues == 1 && $max == 1))) {
                        if ($regopen
                            && (!$this->grouptool->use_size
                                || (count($group->registered) < $group->grpsize)
                                || ($this->grouptool->use_queue
                                    && (count($group->registered) >= $group->grpsize)
                                    && $userqueues < $this->grouptool->queues_max))) {
                            // Groupchange!
                            $label = get_string('change_group', 'grouptool');
                            if ($this->grouptool->use_size
                                && count($group->registered) >= $group->grpsize) {
                                    $label .= ' ('.get_string('queue', 'grouptool').')';
                            }
                            $buttonattr = array('type'   => 'submit',
                                                 'name'  => 'reg['.$group->agrpid.']',
                                                 'value' => $group->agrpid,
                                                 'class' => 'regbutton');
                            $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                        } else if ($regopen && $this->grouptool->use_queue
                                   && (count($group->registered) >= $group->grpsize)
                                   && $userqueues >= $this->grouptool->queues_max) {
                            // Too many queues!
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('max_queues_reached',
                                                                      'grouptool'),
                                                                      array('class' => 'rank'));
                        } else if ($regopen) {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('fullgroup',
                                                                      'grouptool'),
                                                                      array('class' => 'rank'));
                        }
                    } else if ($regopen && ($userregs + $userqueues < $max)) {
                        if (!$this->grouptool->use_size || (count($group->registered) < $group->grpsize)) {
                            // Register button!
                            $label = get_string('register', 'grouptool');
                            $buttonattr = array('type'  => 'submit',
                                                'name'  => 'reg['.$group->agrpid.']',
                                                'value' => $group->agrpid,
                                                'class' => 'regbutton');
                            $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                        } else if ($this->grouptool->use_queue) {
                            if ($userqueues < $this->grouptool->queues_max) {
                                // Queue button!
                                $label = get_string('queue', 'grouptool');
                                $buttonattr = array('type'  => 'submit',
                                                    'name'  => 'reg['.$group->agrpid.']',
                                                    'value' => $group->agrpid,
                                                    'class' => 'queuebutton');
                                $grouphtml .= html_writer::tag('button', $label,
                                                               $buttonattr);
                            } else {
                                // Too many queues!
                                $grouphtml .= html_writer::tag('div',
                                                               get_string('max_queues_reached',
                                                                          'grouptool'),
                                                                          array('class' => 'rank'));
                            }
                        } else {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('fullgroup',
                                                                      'grouptool'),
                                                                      array('class' => 'rank'));
                        }
                    } else if ($regopen) {
                        $grouphtml .= html_writer::tag('div',
                                                       get_string('max_regs_reached',
                                                                  'grouptool'),
                                                                  array('class' => 'rank'));
                    }
                    $status = "";
                    if ($regrank !== false) {
                        $status = 'registered';
                    } else if ($queuerank !== false) {
                        $status = 'queued';
                    } else if (($this->grouptool->use_size) && ($registered >= $group->grpsize)) {
                        $status = 'full';
                    } else {
                        $status = 'empty';
                    }
                    echo html_writer::tag('fieldset',
                                          html_writer::tag('legend',
                                                           $group->name,
                                                           array('class' => 'groupname')).
                                          html_writer::tag('div',
                                                           $grouphtml,
                                                           array('class' => 'fcontainer clearfix')),
                                          array('class' => 'clearfix group '.$status));
                }
            }

            echo html_writer::end_tag('div');
            echo html_writer::end_tag('form');
        }
    }

    /**
     * import users into a certain moodle-group and enrole them if not allready enroled
     *
     * @global object $DB
     * @global object $OUTPUT
     * @global object $CFG
     * @global object $PAGE
     * @param int $group id of group to import into
     * @param object $data from form in import tab (textfield with idnumbers and group-selection)
     * @param bool $previewonly optional preview only, don't take any action
     * @return array ($error, $message)
     */
    public function import($groups, $data, $forceregistration = false, $includedeleted = 0, $previewonly = false) {
        global $DB, $OUTPUT, $CFG, $PAGE, $USER;

        $message = "";
        $error = false;
        $users = preg_split("/[ ,;\t\n\r]+/", $data);
        // Prevent selection of all users if one of the above defined characters are in the beginning!
        foreach ($users as $key => $user) {
            if (empty($user)) {
                unset($users[$key]);
            }
        }
        $groupinfo = array();
        foreach ($groups as $group) {
            $groupinfo[$group] = groups_get_group($group);
        }
        $imported = array();
        $columns = $DB->get_columns('user');
        if (empty($field) || !key_exists($field, $columns)) {
            $field = 'idnumber';
        }
        $agrp = array();
        foreach ($groups as $group) {
            $agrp[$group] = $DB->get_field('grouptool_agrps', 'id', array('grouptoolid' => $this->grouptool->id,
                                                                          'groupid'     => $group), IGNORE_MISSING);
            if (!$DB->record_exists('grouptool_agrps', array('grouptoolid' => $this->grouptool->id,
                                                             'groupid'     => $group,
                                                             'active'      => 1))) {
                $message .= $OUTPUT->notification(get_string('import_in_inactive_group_warning',
                                                             'grouptool', $groupinfo[$group]->name),
                                                  array('notifyproblem'));
            }
            // We use MAX to trick Postgres into thinking this is a full GROUPU BY statement!
            $sql = '     SELECT agrps.id id, MAX(agrps.groupid) grpid, COUNT(regs.id) regs,
                                MAX(grptl.use_individual) indi, MAX(grptl.grpsize) globalsize, MAX(agrps.grpsize) size,
                                MAX(grptl.name) instancename
                           FROM {grouptool_agrps} agrps
                           JOIN {grouptool} grptl ON agrps.grouptoolid = grptl.id
                      LEFT JOIN {grouptool_registered} regs ON agrps.id = regs.agrpid AND regs.modified_by >= 0
                          WHERE agrps.groupid = :grpid
                            AND grptl.use_size = 1
                            AND agrps.active = 1
                       GROUP BY agrps.id
                       ';
            $agrps = $DB->get_records_sql($sql, array('grpid' => $group));
            $usercnt = count($users);
            foreach ($agrps as $cur) {
                if ($cur->indi && !empty($cur->size)) {
                    if (($cur->regs + $usercnt) > $cur->size) {
                        $message .= html_writer::tag('div',
                                                     $OUTPUT->notification(get_string('overflowwarning',
                                                                                      'grouptool', $cur),
                                                                           'notifytiny'));
                    }
                } else {
                    if (($cur->regs + $usercnt) > $cur->globalsize) {
                        $message .= html_writer::tag('div',
                                                     $OUTPUT->notification(get_string('overflowwarning',
                                                                                      'grouptool', $cur),
                                                                           'notifytiny'));
                    }
                }
            }
        }
        if (false !== ($gtimportfields = get_config('mod_grouptool', 'importfields'))) {
            $importfields = explode(',', $gtimportfields);
        } else {
            $importfields = array('username', 'idnumber');
        }
        $prevtable = new html_table();
        $prevtable->attributes['class'] = 'importpreview table table-striped table-hover';
        $prevtable->id = 'importpreview';
        $prevtable->head = array(get_string('fullname'));
        foreach ($importfields as $field) {
            $prevtable->head[] = get_string($field);
        }
        $prevtable->head[] = get_string('status');
        $prevtable->data = array();

        $pbar = new progress_bar('checkmarkimportprogress', 500, true);
        $count = count($users);
        $processed = 0;
        $pbar->update($processed, $count, get_string('import_progress_start', 'grouptool'));
        core_php_time_limit::raise(count($users) * 5);
        raise_memory_limit(MEMORY_HUGE);
        foreach ($users as $user) {
            $pbar->update($processed, $count, get_string('import_progress_search', 'grouptool').' '.$user);
            foreach ($importfields as $field) {
                $sql = 'SELECT * FROM {user} WHERE '.$DB->sql_like($field, ':userpattern');
                if (empty($includedeleted)) {
                    $sql .= ' AND deleted = :deleted';
                    $param = array('userpattern' => $user, 'deleted' => 0);
                } else {
                    $param = array('userpattern' => $user);
                }

                $userinfo = $DB->get_records_sql($sql, $param);

                if (empty($userinfo)) {
                    $param['userpattern'] = '%'.$user;
                    $userinfo = $DB->get_records_sql($sql, $param);
                } else if (count($userinfo) == 1) {
                    break;
                }

                if (empty($userinfo)) {
                    $param['userpattern'] = $user.'%';
                    $userinfo = $DB->get_records_sql($sql, $param);
                } else if (count($userinfo) == 1) {
                    break;
                }

                if (empty($userinfo)) {
                    $param['userpattern'] = '%'.$user.'%';
                    $userinfo = $DB->get_records_sql($sql, $param);
                } else if (count($userinfo) == 1) {
                    break;
                }

                if (!empty($userinfo) && count($userinfo) == 1) {
                    break;
                }
            }
            $row = new html_table_row();
            if (empty($userinfo)) {
                $row->cells[] = new html_table_cell($OUTPUT->notification(get_string('user_not_found',
                                                                              'grouptool', $user),
                                                                   'notifyproblem'));
                $row->cells[0]->colspan = count($prevtable->head);
                $error = true;
            } else if (count($userinfo) > 1) {
                $tmprows = array();
                foreach ($userinfo as $currentuser) {
                    $tmprow = new html_table_row();
                    $tmprow->cells = array();
                    $tmprow->cells[] = new html_table_cell(fullname($currentuser));
                    foreach ($importfields as $curfield) {
                        $tmprow->cells[] = new html_table_cell($currentuser->$curfield);
                    }
                    $tmprows[] = $tmprow;
                }
                $curkey = count($tmprows[0]->cells);
                $tmprows[0]->cells[$curkey] = new html_table_cell($OUTPUT->notification(get_string('found_multiple', 'grouptool'),
                                                                                        'notifyproblem'));
                $tmprows[0]->cells[$curkey]->rowspan = count($tmprows);
                foreach ($tmprows as $tmprow) {
                    $prevtable->data[] = $tmprow;
                }
                $error = true;
                // We've added multiple rows manualle and can continue with the next user!
                continue;
            } else {
                $userinfo = reset($userinfo);
                $row->cells = array(new html_table_cell(fullname($userinfo)));
                foreach ($importfields as $curfield) {
                    $row->cells[] = new html_table_cell(empty($userinfo->$curfield) ? '' : $userinfo->$curfield);
                }
                if (!is_enrolled($this->context, $userinfo->id)) {

                    // We have to catch deleted users now, give a message and continue!
                    if (!empty($userinfo->deleted)) {
                        $userinfo->fullname = fullname($userinfo);
                        $text = get_string('user_is_deleted', 'grouptool', $userinfo);
                        $row->cells[] = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                        $error = true;
                        continue;
                    }
                    /*
                     * if user's not enrolled already we force manual enrollment in course,
                     * so we can add the user to the group
                     */
                    require_once($CFG->dirroot.'/enrol/manual/locallib.php');
                    require_once($CFG->libdir.'/accesslib.php');
                    if (!$enrolmanual = enrol_get_plugin('manual')) {
                        throw new coding_exception('Can not instantiate enrol_manual');
                    }
                    if (!$instance = $DB->get_record('enrol', array('courseid' => $this->course->id,
                                                                    'enrol'    => 'manual'),
                                                     '*', IGNORE_MISSING)) {
                        if ($instanceid = $enrolmanual->add_default_instance($this->course)) {
                            $instance = $DB->get_record('enrol',
                                                        array('courseid' => $this->course->id,
                                                              'enrol'    => 'manual'), '*',
                                                        MUST_EXIST);
                        }
                    }
                    if ($instance != false) {
                        $archroles = get_archetype_roles('student');
                        $archrole = array_shift($archroles);
                        $enrolmanual->enrol_user($instance, $userinfo->id, $archrole->id, time());
                    } else {
                        $row->cells[] = new html_table_cell($OUTPUT->notification(get_string('cant_enrol',
                                                                                  'grouptool'), 'notifyproblem'));
                    }
                }
                foreach ($groups as $group) {
                    $data = array(
                            'id' => $userinfo->id,
                            'idnumber' => $userinfo->idnumber,
                            'fullname' => fullname($userinfo),
                            'groupname' => $groupinfo[$group]->name);
                    if (!$previewonly && $userinfo) {
                        $attr = array('class' => 'notifysuccess');
                        $pbar->update($processed, $count,
                                      get_string('import_progress_import', 'grouptool').' '.fullname($userinfo).'...');
                        if (!groups_add_member($group, $userinfo->id)) {
                            $error = true;
                            $notification = $OUTPUT->notification(get_string('import_user_problem',
                                                                              'grouptool', $data),
                                                                   'notifyproblem');
                            $row->cells[] = new html_table_cell($notification);
                            $row->attributes['class'] = 'error';
                        } else {
                            $imported[] = $userinfo->id;
                            $row->cells[] = get_string('import_user', 'grouptool', $data);
                            $row->attributes['class'] = 'notifysuccess';
                        }
                        if ($forceregistration && empty($agrp[$group])) {
                            /* Registering in an non active Grouptool-group would cause problems
                             * with incorrectly labeled buttons under certain circumstances.
                             * We removed the automatic creation and registration in this newly inserted inactive group.
                             * In no case, there should be a missing agrp entry anyway.
                             */
                            $newgrpdata = $DB->get_record_sql('SELECT MAX(sort_order), MAX(grpsize)
                                                                 FROM {grouptool_agrps}
                                                               WHERE grouptoolid = ?',
                                                              array($this->grouptool->id));
                            // Insert agrp-entry for this group (even if it's not active)!
                            var_dump($group);
                            $agrp[$group] = new stdClass();
                            $agrp[$group]->grouptoolid = $this->grouptool->id;
                            $agrp[$group]->groupid = $group;
                            $agrp[$group]->active = 0;
                            $agrp[$group]->sort_order = $newgrpdata->sortorder + 1;
                            $agrp[$group]->grpsize = $newgrpdata->grpsize;
                            $agrp[$group]->id = $DB->insert_record('grouptool_agrps', $agrp[$group]);
                            \mod_grouptool\event\agrp_created::create_from_object($this->cm, $agrp[$group])->trigger();
                            $notification = $OUTPUT->notification(get_string('import_in_inactive_group_rejected',
                                                                             'grouptool', $agrp[$group]),
                                                                  'notifyproblem');
                            $row->cells[] = $notification;
                            $row->attributes['class'] = 'error';
                            $agrp[$group] = $agrp[$group]->id;
                        } else if ($forceregistration && !empty($agrp[$group])
                                   && !$DB->record_exists_select('grouptool_registered',
                                                                 "modified_by >= 0 AND agrpid = :agrpid AND userid = :userid",
                                                                 array('agrpid' => $agrp[$group], 'userid' => $userinfo->id))) {
                            if ($reg = $DB->get_record('grouptool_registered',
                                                       array('agrpid' => $agrp[$group],
                                                             'userid' => $userinfo->id,
                                                             'modified_by' => -1),
                                                       IGNORE_MISSING)) {
                                // If user is marked, we register him right now!
                                $reg->modified_by = $USER->id;
                                $DB->update_record('grouptool->registered', $reg);
                                // TODO do we have to delete his marks and queues if theres enough registrations?
                            } else {
                                $reg = new stdClass();
                                $reg->agrpid = $agrp[$group];
                                $reg->userid = $userinfo->id;
                                $reg->timestamp = time();
                                $reg->modified_by = $USER->id;
                                // We don't need to log creation of registration, because we log import as whole!
                                $reg->id = $DB->insert_record('grouptool_registered', $reg);
                            }

                            \mod_grouptool\event\user_imported::import_forced($this->cm, $reg->id, $agrp[$group],
                                                                              $group, $userinfo->id)->trigger();
                        } else if (!$forceregistration) {
                            // Trigger the event!
                            \mod_grouptool\event\user_imported::import($this->cm, $group, $userinfo->id)->trigger();
                        }
                    } else if ($userinfo) {
                        $attr = array('class' => 'prevsuccess');
                        $row->cells[] = get_string('import_user_prev', 'grouptool', $data);
                        $row->attributes['class'] = 'prevsuccess';
                    }
                }
            }
            $prevtable->data[] = $row;
            unset($row);
            $processed++;
        }
        $processed++;
        if (!$previewonly) {
            $pbar->update($processed, $count, get_string('import_progress_completed', 'grouptool'));
        } else {
            $pbar->update($processed, $count, get_string('import_progress_preview_completed', 'grouptool'));
        }
        $message .= html_writer::table($prevtable);
        return array($error, $message);
    }

    /**
     * view import-tab
     *
     * @global object $OUTPUT
     * @global object  $DB
     * @global object  $CFG
     * @global object  $USER
     * @global object  $PAGE
     */
    public function view_import() {
        global $PAGE, $OUTPUT;
        require_capability('mod/grouptool:register_students', $this->context);

        $id = $this->cm->id;
        $form = new mod_grouptool_view_import_form(null, array('id' => $id));

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $group = required_param_array('group', PARAM_INT);
            $data = required_param('data', PARAM_RAW);
            $includedeleted = optional_param('includedeleted', 0, PARAM_BOOL);
            $forceregistration = optional_param('forceregistration', 0, PARAM_BOOL);
            if (!empty($data)) {
                $data = unserialize($data);
            }
            list($error, $message) = $this->import($group, $data, $forceregistration, $includedeleted);

            if (!empty($error)) {
                $message = $OUTPUT->notification(get_string('ignored_not_found_users', 'grouptool'),
                                                 'notifyproblem').
                           html_writer::empty_tag('br').
                           $message;
            }
            echo html_writer::tag('div', $message, array('class' => 'centered'));
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            list($error, $confirmmessage) = $this->import($fromform->group, $fromform->data,
                                                          $fromform->forceregistration,
                                                          $fromform->includedeleted, true);

            $attr = array(
                    'confirm'           => '1',
                    'data'              => serialize($fromform->data),
                    'forceregistration' => $fromform->forceregistration,
                    'includedeleted'    => $fromform->includedeleted);
            foreach ($fromform->group as $group) {
                $attr['group['.$group.']'] = $group;
            }

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if ($error) {
                $confirmmessage = $OUTPUT->notification(get_string('ignoring_not_found_users',
                                                                   'grouptool'),
                                                        'notifyproblem').
                                  html_writer::empty_tag('br').
                                  $confirmmessage;
            }
            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered').
                 $confirmmessage.
                 $this->confirm('', $continue, $cancel);

        } else {
            $form->display();
        }

    }

    /**
     * get all data necessary for displaying/exporting group-overview table
     *
     * @global object $OUTPUT
     * @global object $CFG
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param bool $onlydata optional return object with raw data not html-fragment-string
     * @return string|object either html-fragment representing table or raw data as object
     */
    public function group_overview_table($groupingid = 0, $groupid = 0, $onlydata = false) {
        global $OUTPUT, $CFG, $DB;
        if (!$onlydata) {
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/mod/grouptool/download.php',
                                          array('id'          => $this->cm->id,
                                                'groupingid'  => $groupingid,
                                                'groupid'     => $groupid,
                                                'orientation' => $orientation,
                                                'sesskey'     => sesskey(),
                                                'tab'         => 'overview'));
        } else {
            $return = array();
        }

        // We just get an overview and fetch data later on a per group basis to save memory!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid);
        $groupids = array_keys($agrps);
        $groupinfo = groups_get_all_groups($this->grouptool->course);
        $userinfo = array();
        $syncstatus = $this->get_sync_status();
        $context = context_module::instance($this->cm->id);
        if ((!$onlydata && count($agrps)) && has_capability('mod/grouptool:export', $context)) {
            // Global-downloadlinks!
            $txturl = new moodle_url($downloadurl, array('format' => GROUPTOOL_TXT));
            $xlsxurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_XLSX));
            $pdfurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_PDF));
            $odsurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_ODS));
            $downloadlinks = html_writer::tag('span', get_string('downloadall').":",
                                              array('class' => 'title')).'&nbsp;'.
                             html_writer::link($txturl, '.TXT').'&nbsp;'.
                             html_writer::link($xlsxurl, '.XLSX').'&nbsp;'.
                             html_writer::link($pdfurl, '.PDF').'&nbsp;'.
                             html_writer::link($odsurl, '.ODS');
            echo html_writer::tag('div', $downloadlinks, array('class' => 'download all'));
        }

        foreach ($agrps as $agrp) {
            // We give each group 30 seconds (minimum) and hope it doesn't time out because of no output in case of download!
            core_php_time_limit::raise(30);
            if (!$onlydata) {
                if ($syncstatus[1][$agrp->agrpid]->status == GROUPTOOL_UPTODATE) {
                    echo $OUTPUT->box_start('generalbox groupcontainer uptodate');
                } else {
                    echo $OUTPUT->box_start('generalbox groupcontainer outdated');
                }
                $groupinfos = $OUTPUT->heading($groupinfo[$agrp->id]->name, 3);
                flush();
            } else {
                $groupdata = new stdClass();
                $groupdata->name = $groupinfo[$agrp->id]->name;
            }

            // Get all registered userids!
            $select = " agrpid = ? AND modified_by > 0 ";
            $registered = $DB->get_fieldset_select('grouptool_registered', 'userid', $select, array($agrp->agrpid));
            // Get all moodle-group-member-ids!
            $select = " groupid = ? ";
            $members = $DB->get_fieldset_select('groups_members', 'userid', $select, array($agrp->id));
            // Get all registered users with moodle-group-membership!
            $absregs = array_intersect($registered, $members);
            // Get all registered users without moodle-group-membership!
            $gtregs = array_diff($registered, $members);
            // Get all moodle-group-members without registration!
            $mdlregs = array_diff($members, $registered);
            // Get all queued users!
            $select = " agrpid = ? ";
            $queued = $DB->get_fieldset_select('grouptool_queued', 'userid', $select, array($agrp->agrpid));

            // We give additional 1 second per registration/queue/moodle entry in this group!
            core_php_time_limit::raise(30 * (count($registered) + count($members) + count($queued)));

            if (!empty($this->grouptool->use_size)) {
                if (!empty($this->grouptool->use_individual) && !empty($agrp->grpsize)) {
                    $size = $agrp->grpsize;
                    $free = $agrp->grpsize - count($registered);
                } else {
                    $size = !empty($this->grouptool->grpsize) ?
                    $this->grouptool->grpsize :
                    get_config('mod_grouptool', 'grpsize');
                    $free = ($size - count($registered));

                }
            } else {
                $size = "∞";
                $free = '∞';
            }
            if (!$onlydata) {
                $groupinfos .= html_writer::tag('span', get_string('total', 'grouptool').' '.$size,
                                                array('class' => 'groupsize'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('registered', 'grouptool').
                                                              ' '.count($registered),
                                                      array('class' => 'registered'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('queued', 'grouptool').' '.
                                                              count($queued),
                                                      array('class' => 'queued'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('free', 'grouptool').' '.
                                                              $free, array('class' => 'free'));

                echo html_writer::tag('div', $groupinfos, array('class' => 'groupinfo'));

                echo html_writer::start_tag('table',
                                        array('class' => 'centeredblock userlist table table-striped table-hover table-condensed'));
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', get_string('status', 'grouptool').
                                            $OUTPUT->help_icon('status', 'grouptool'), array('class' => 'text-center'));
                echo html_writer::tag('th', get_string('fullname'), array('class' => ''));
                echo html_writer::tag('th', get_string('idnumber'), array('class' => ''));
                echo html_writer::tag('th', get_string('email'), array('class' => ''));
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
            } else {
                $groupdata->total = $size;
                $groupdata->registered = count($registered);
                $groupdata->queued = count($queued);
                $groupdata->free = $free;
                $groupdata->reg_data = array();
                $groupdata->queue_data = array();
            }

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = get_all_user_name_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            if (count($registered) + count($members) >= 1) {
                if (count($absregs) >= 1) {
                    foreach ($absregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);
                        if (!$onlydata) {
                            echo html_writer::start_tag('tr', array('class' => ''));
                            echo html_writer::tag('td', "✔", array('class' => 'status'));
                            $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                    $curuser.'&course='.$this->course->id,
                                    'title' => $fullname);
                            $userlink = html_writer::tag('a', $fullname, $userlinkattr);
                            echo html_writer::tag('td', $userlink, array('class' => 'userlink'));
                            if (!empty($userinfo[$curuser]->idnumber)) {
                                $idnumber = html_writer::tag('span',
                                                             $userinfo[$curuser]->idnumber,
                                                             array('class' => 'idnumber'));
                            } else {
                                $idnumber = html_writer::tag('span', '-', array('class' => 'idnumber'));
                            }
                            echo html_writer::tag('td', $idnumber, array('class' => 'idnumber'));
                            if (!empty($userinfo[$curuser]->email)) {
                                $email = html_writer::tag('span', $userinfo[$curuser]->email,
                                                          array('class' => 'email'));
                            } else {
                                $email = html_writer::tag('span', '-', array('class' => 'email'));
                            }
                            echo html_writer::tag('td', $email, array('class' => 'email'));
                            echo html_writer::end_tag('tr');
                            flush();
                        } else {
                            $row = array();
                            $row['name'] = $fullname;
                            foreach ($namefields as $field) {
                                $row[$field] = $userinfo[$curuser]->$field;
                            }
                            // We set those in any case, because PDF and TXT export needs them anyway!
                            $row['email'] = $userinfo[$curuser]->email;
                            $row['idnumber'] = $userinfo[$curuser]->idnumber;
                            $row['status'] = "✔";
                            $groupdata->reg_data[] = $row;
                            $row = null;
                            unset($row);
                        }
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($gtregs) >= 1) {
                    foreach ($gtregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);
                        if (!$onlydata) {
                            echo html_writer::start_tag('tr', array('class' => ''));
                            echo html_writer::tag('td', "+", array('class' => 'status'));
                            $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                    $curuser.'&course='.$this->course->id,
                                    'title' => $fullname);
                            $userlink = html_writer::tag('a', $fullname, $userlinkattr);
                            echo html_writer::tag('td', $userlink, array('class' => 'userlink'));
                            if (!empty($userinfo[$curuser]->idnumber)) {
                                $idnumber = html_writer::tag('span',
                                                             $userinfo[$curuser]->idnumber,
                                                             array('class' => 'idnumber'));
                            } else {
                                $idnumber = html_writer::tag('span', '-', array('class' => 'idnumber'));
                            }
                            echo html_writer::tag('td', $idnumber, array('class' => 'idnumber'));
                            if (!empty($userinfo[$curuser]->email)) {
                                $email = html_writer::tag('span', $userinfo[$curuser]->email,
                                                          array('class' => 'email'));
                            } else {
                                $email = html_writer::tag('span', '-', array('class' => 'email'));
                            }
                            echo html_writer::tag('td', $email, array('class' => 'email'));
                            html_writer::end_tag('tr');
                            flush();
                        } else {
                            $row = array();
                            $row['name'] = $fullname;
                            foreach ($namefields as $field) {
                                $row[$field] = $userinfo[$curuser]->$field;
                            }
                            $row['email'] = $userinfo[$curuser]->email;
                            $row['idnumber'] = $userinfo[$curuser]->idnumber;
                            $row['status'] = "+";
                            $groupdata->reg_data[] = $row;
                            $row = null;
                            unset($row);
                        }
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($mdlregs) >= 1) {
                    foreach ($mdlregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);
                        if (!$onlydata) {
                            echo html_writer::start_tag('tr', array('class' => ''));
                            echo html_writer::tag('td', "?", array('class' => 'status'));
                            $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                    $curuser.'&course='.$this->course->id,
                                    'title' => $fullname);
                            $userlink = html_writer::tag('a', $fullname, $userlinkattr);
                            echo html_writer::tag('td', $userlink, array('class' => 'userlink'));
                            if (!empty($userinfo[$curuser]->idnumber)) {
                                $idnumber = html_writer::tag('span',
                                                             $userinfo[$curuser]->idnumber,
                                                             array('class' => 'idnumber'));
                            } else {
                                $idnumber = html_writer::tag('span', '-', array('class' => 'idnumber'));
                            }
                            echo html_writer::tag('td', $idnumber, array('class' => 'idnumber'));
                            if (!empty($userinfo[$curuser]->email)) {
                                $email = html_writer::tag('span', $userinfo[$curuser]->email,
                                                          array('class' => 'email'));
                            } else {
                                $email = html_writer::tag('span', '-', array('class' => 'email'));
                            }
                            echo html_writer::tag('td', $email, array('class' => 'email'));
                            echo html_writer::end_tag('tr');
                            flush();
                        } else {
                            $row = array();
                            $row['name'] = $fullname;
                            foreach ($namefields as $field) {
                                $row[$field] = $userinfo[$curuser]->$field;
                            }
                            // We set those in any case, because PDF and TXT export needs them anyway!
                            $row['email'] = $userinfo[$curuser]->email;
                            $row['idnumber'] = $userinfo[$curuser]->idnumber;
                            $row['status'] = "?";
                            $groupdata->mreg_data[] = $row;
                            $row = null;
                            unset($row);
                        }
                    }
                    $regentry = null;
                    unset($regentry);
                }
            } else {
                if (!$onlydata) {
                    echo html_writer::start_tag('tr', array('class' => 'regentry reg'));
                    echo html_writer::tag('td', get_string('no_registrations', 'grouptool'),
                                          array('class' => 'no_registrations',
                                                'colspan' => 4));
                    echo html_writer::end_tag('tr');
                }
            }

            if (count($queued) >= 1) {
                $queuedlist = $DB->get_records('grouptool_queued', array('agrpid' => $agrp->agrpid), 'timestamp ASC');
                foreach ($queued as $curuser) {
                    if (!array_key_exists($curuser, $userinfo)) {
                        $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                    }
                    $fullname = fullname($userinfo[$curuser]);
                    $rank = $this->get_rank_in_queue($queuedlist, $curuser);
                    if (!$onlydata) {
                        echo html_writer::start_tag('tr', array('class' => 'queueentry'));
                        echo html_writer::tag('td', $rank, array('class' => 'rank'));
                        $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                $curuser.'&course='.$this->course->id,
                                'title' => $fullname);
                        $userlink = html_writer::tag('a', $fullname, $userlinkattr);
                        echo html_writer::tag('td', $userlink, array('class' => 'userlink'));
                        if (!empty($userinfo[$curuser]->idnumber)) {
                            $idnumber = html_writer::tag('span',
                                                         $userinfo[$curuser]->idnumber,
                                                         array('class' => 'idnumber'));
                        } else {
                            $idnumber = html_writer::tag('span', '-', array('class' => 'idnumber'));
                        }
                        echo html_writer::tag('td', $idnumber, array('class' => 'idnumber'));
                        if (!empty($userinfo[$curuser]->email)) {
                            $email = html_writer::tag('span', $userinfo[$curuser]->email,
                                                      array('class' => 'email'));
                        } else {
                            $email = html_writer::tag('span', '-', array('class' => 'email'));
                        }
                        echo html_writer::tag('td', $email, array('class' => 'email'));
                        echo html_writer::end_tag('tr');
                        flush();
                    } else {
                        $row = array();
                        $row['rank'] = $rank;
                        $row['name'] = $fullname;
                        foreach ($namefields as $namefield) {
                            if (!empty($userinfo[$curuser]->$namefield)) {
                                $row[$namefield] = $userinfo[$curuser]->$namefield;
                            } else {
                                $row[$namefield] = '';
                            }
                        }
                        if (empty($CFG->showuseridentity)) {
                            if (!empty($userinfo[$curuser]->idnumber)) {
                                $row['idnumber'] = $userinfo[$curuser]->idnumber;
                            } else {
                                $row['idnumber'] = '-';
                            }
                            if (!empty($userinfo[$curuser]->email)) {
                                $row['email'] = $userinfo[$curuser]->email;
                            } else {
                                $row['email'] = '-';
                            }
                        } else {
                            $fields = explode(',', $CFG->showuseridentity);
                            foreach ($fields as $field) {
                                if (!empty($userinfo[$curuser]->$field)) {
                                    $row[$field] = $userinfo[$curuser]->$field;
                                } else {
                                    $row[$field] = '';
                                }
                            }
                        }
                        // We set those in any case, because PDF and TXT export needs them anyway!
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $groupdata->queue_data[] = $row;
                    }
                }
            } else {
                if (!$onlydata) {
                    echo html_writer::start_tag('tr', array('class' => 'queueentry queue'));
                    echo html_writer::tag('td', get_string('nobody_queued', 'grouptool'),
                                          array('class' => 'no_queues',
                                                'colspan' => 4));
                    echo html_writer::end_tag('tr');
                }
            }
            if (!$onlydata) {
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                // Group-downloadlinks!
                if (((count($queued) > 0) || (count($registered) > 0))
                    && has_capability('mod/grouptool:export', $context)) {
                    $urltxt = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_TXT));
                    $urlxlsx = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_XLSX));
                    $urlpdf = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_PDF));
                    $urlods = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_ODS));

                    $downloadlinks = html_writer::tag('span', get_string('download').":",
                                                      array('class' => 'title')).'&nbsp;'.
                                     html_writer::link($urltxt, '.TXT').'&nbsp;'.
                                     html_writer::link($urlxlsx, '.XLSX').'&nbsp;'.
                                     html_writer::link($urlpdf, '.PDF').'&nbsp;'.
                                     html_writer::link($urlods, '.ODS');
                    echo html_writer::tag('div', $downloadlinks, array('class' => 'download group'));
                }
                // Faster browser output prevents browser timeout!
                echo $OUTPUT->box_end();
                flush();
            } else {
                $return[] = $groupdata;
            }
            $groupdata = null;
            unset($groupdata);
        }

        if (count($agrps) == 0) {
            $boxcontent = $OUTPUT->notification(get_string('no_data_to_display', 'grouptool'),
                                                'notifyproblem');
            $return = $OUTPUT->box($boxcontent, 'generalbox centered');
            if (!$onlydata) {
                echo $return;
            }
        }
        if ($onlydata) {
            return $return;
        } else {
            return 0;
        }
    }

    /**
     * outputs generated pdf-file for overview (forces download)
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_pdf($groupid=0, $groupingid=0) {
        global $USER;
        require_once('./grouptool_pdf.php');

        $data = $this->group_overview_table($groupingid, $groupid, true);

        $pdf = new grouptool_pdf();
        $pdf->setFontSubsetting(false);

        // Set orientation (P/L)!
        $orientation = (optional_param('orientation', 0, PARAM_BOOL) == 0) ? 'P' : 'L';
        $pdf->setPageOrientation($orientation);

        // Set document information!
        $pdf->SetCreator('TUWEL');
        $pdf->SetAuthor($USER->firstname . " " . $USER->lastname);

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupid)) {
            $viewname = groups_get_group_name($groupid);
        } else {
            if (!empty($groupingid)) {
                $viewname = groups_get_grouping_name($groupingid);
            } else {
                $viewname = get_string('all').' '.get_string('groups');
            }
        }

        $pdf->set_overview_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                       $viewname);

        // Set header/footer!
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);

        $textsize = optional_param('textsize', 1, PARAM_INT);
        switch ($textsize){
            case "0":
                $pdf->SetFontSize(8);
                break;
            case "1":
                $pdf->SetFontSize(10);
                break;
            case "2":
                $pdf->SetFontSize(12);
                break;
        }

        // Set margins!
        if (1) {
            $pdf->SetMargins(10, 30, 10, true); // Left Top Right.
        } else {
            $pdf->SetMargins(10, 10, 10, true);
        }
        // Set default monospaced font!
        $pdf->SetDefaultMonospacedFont(/*PDF_FONT_MONOSPACED*/'freeserif');

        // Set margins!
        $pdf->SetHeaderMargin(7);
        $pdf->SetFooterMargin(7);

        // Set auto page breaks!
        $pdf->SetAutoPageBreak(true, /*PDF_MARGIN_BOTTOM*/10);

        // Set image scale factor
        $pdf->setImageScale(/*PDF_IMAGE_SCALE_RATIO*/1);

        /*
         * ---------------------------------------------------------
         */

        // Set font!
        $pdf->SetFont('freeserif', '');
        $pdf->addPage($orientation, 'A4', false, false);
        if (count($data) > 0) {

            foreach ($data as $group) {
                $groupname = $group->name;
                $groupinfo = get_string('total').' '.$group->total.' / '.
                             get_string('registered', 'grouptool').' '.$group->registered.' / '.
                             get_string('queued', 'grouptool').' '.$group->queued.' / '.
                             get_string('free', 'grouptool').' '.$group->free;
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = isset($group->mreg_data) ? $group->mreg_data : array();
                $pdf->add_grp_overview($groupname, $groupinfo, $regdata, $queuedata, $mregdata);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'B', 'L', false, 1, null, null, true, 1,
                                true, false, $pdf->getLastH(), 'M', true);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'T', 'L', false, 1, null, null, true, 1,
                                true, false, $pdf->getLastH(), 'M', true);
            }
            $pdf->SetFontSize(8);
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('status', 'grouptool'), '', 'L', false,
                            1, null, null, true, 1, true, false, $pdf->getLastH(), 'M', true);
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $pdf->MultiCell(0, $pdf->getLastH(), strip_tags($legendline), '', 'L', false, 1,
                                null, null, true, 1, true, false, $pdf->getLastH(), 'M', true);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'), 'B',
                            'LRTB', false, 1, null, null, true, 1, true, false, $pdf->getLastH(),
                            'M', true);
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').' '.get_string('overview', 'grouptool');
        }
        $pdf->Output($filename.'.pdf', 'D');
        exit();
    }

    /**
     * returns raw data for overview
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @return object raw data
     */
    public function download_overview_raw($groupid=0, $groupingid=0) {
        return $this->group_overview_table($groupid, $groupingid, true);
    }

    /**
     * outputs generated txt-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_txt($groupid=0, $groupingid=0) {
        ob_start();
        $return = "";
        $lines = array();
        $groups = $this->group_overview_table($groupingid, $groupid, true);
        if (count($groups) > 0) {
            $lines[] = "*** ".get_string('status', 'grouptool')."\n";
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $lines[] = "***\t".strip_tags($legendline);
            }
            $lines[] = "";

            foreach ($groups as $group) {
                $lines[] = $group->name;
                $lines[] = "\t".get_string('total').' '.$group->total." / ".
                                get_string('registered', 'grouptool').' '.$group->registered." / ".
                                get_string('queued', 'grouptool').' '.$group->queued." / ".
                                get_string('free', 'grouptool').' '.$group->free;
                if ($group->registered > 0) {
                    $lines[] = "\t".get_string('registrations', 'grouptool');
                    foreach ($group->reg_data as $reg) {
                        $lines[] = "\t\t".$reg['status']."\t".$reg['name']."\t".$reg['idnumber'].
                                   "\t".$reg['email'];
                    }
                } else if (count($group->mreg_data) == 0) {
                    $lines[] = "\t\t--".get_string('no_registrations', 'grouptool')."--";
                }
                if (count($group->mreg_data) >= 1) {
                    foreach ($group->mreg_data as $mreg) {
                        $lines[] = "\t\t?\t".$mreg['name']."\t".$mreg['idnumber']."\t".
                                   $mreg['email'];
                    }
                }
                if ($group->queued > 0) {
                    $lines[] = "\t".get_string('queue', 'grouptool');
                    foreach ($group->queue_data as $queue) {
                        $lines[] = "\t\t".$queue['rank']."\t".$queue['name']."\t".
                                   $queue['idnumber']."\t".$queue['email'];
                    }
                } else {
                    $lines[] = "\t\t--".get_string('nobody_queued', 'grouptool')."--";
                }
                $lines[] = "";
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').' '.get_string('overview', 'grouptool');
        }
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header('Content-Disposition: attachment; filename="'.$filename.'.txt"; filename*="'.
               rawurlencode($filename).'.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * fills workbook (either XLSX or ODS) with data
     *
     * @param MoodleExcelWorkbook $workbook workbook to put data into
     * @param array $groups which groups from whom to include data
     */
    private function overview_fill_workbook(&$workbook, $groups, $collapsed=array()) {
        global $CFG;
        if (count($groups) > 0) {

            $columnwidth = array( 7, 22, 14, 17); // Unit: mm!

            if (count($groups) > 1) {
                // General information? unused at the moment!
                $allgroupsworksheet = $workbook->add_worksheet(get_string('all'));
                // The standard column widths: 7 - 22 - 14 - 17!
                $allgroupsworksheet->set_column(0, 0, $columnwidth[0]);
                $allgroupsworksheet->set_column(1, 1, $columnwidth[1]);
                $allgroupsworksheet->set_column(2, 2, $columnwidth[2]);
                $allgroupsworksheet->set_column(3, 3, $columnwidth[3]);
                $generalsheet = true;
            } else {
                $generalsheet = false;
            }

            $legendworksheet = $workbook->add_worksheet(get_string('status', 'grouptool').' '.
                                                        get_string('help'));
            $legendworksheet->write_string(0, 0, get_string('status', 'grouptool').' '.
                                                 get_string('help'));
            $line = 1;
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                if (strstr($legendline, "</span>")) {
                    $lineelements = explode("</span>", $legendline);
                    $legendworksheet->write_string($line, 0, strip_tags($lineelements[0]));
                    $legendworksheet->write_string($line, 1, strip_tags($lineelements[1]));
                    $line++;
                }
            }

            // Add content for all groups!
            $groupworksheets = array();

            // Prepare formats!
            $headlineprop = array(    'size' => 14,
                    'bold' => 1,
                    'align' => 'center');
            $headlineformat = $workbook->add_format($headlineprop);
            $groupinfoprop1 = array(  'size' => 10,
                    'bold' => 1,
                    'align' => 'left');
            $groupinfoprop2 = $groupinfoprop1;
            unset($groupinfoprop2['bold']);
            $groupinfoprop2['italic'] = true;
            $groupinfoprop2['align'] = 'right';
            $groupinfoformat1 = $workbook->add_format($groupinfoprop1);
            $groupinfoformat2 = $workbook->add_format($groupinfoprop2);
            $regheadprop = array(    'size' => 10,
                    'align' => 'center',
                    'bold' => 1,
                    'bottom' => 2);
            $regentryprop = array(   'size' => 10,
                    'align' => 'left');
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regheadformat = $workbook->add_format($regheadprop);
            $regheadformat->set_right(1);
            $regheadlast = $workbook->add_format($regheadprop);

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_top(1);
            $regentryformat->set_bottom(0);
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_top(1);
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('center');
            $queueentryformat = $workbook->add_format($queueentryprop);
            $queueentryformat->set_right(1);
            $queueentryformat->set_top(1);
            $queueentryformat->set_bottom(false);
            $queueentrylast = $workbook->add_format($queueentryprop);
            $queueentrylast->set_top(1);
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('center');

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = get_all_user_name_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = array(0               => 26,
                                 'fullname'      => 26,
                                 'firstname'     => 20,
                                 'surname'       => 20,
                                 'email'         => 35,
                                 'registrations' => 47,
                                 'queues_rank'   => 7.5,
                                 'queues_grp'    => 47); // Unit: mm!

            // Start row for groups general sheet!
            $j = 0;
            foreach ($groups as $key => $group) {
                // Add worksheet for each group!
                $groupworksheets[$key] = $workbook->add_worksheet($group->name);

                $groupname = $group->name;
                $groupinfo = array();
                $groupinfo[] = array(get_string('total'), $group->total);
                $groupinfo[] = array(get_string('registered', 'grouptool'), $group->registered);
                $groupinfo[] = array(get_string('queued', 'grouptool'), $group->queued);
                $groupinfo[] = array(get_string('free', 'grouptool'), $group->free);
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = isset($group->mreg_data) ? $group->mreg_data : array();
                // Groupname as headline!
                $groupworksheets[$key]->write_string(0, 0, $groupname, $headlineformat);
                $groupworksheets[$key]->merge_cells(0, 0, 0, 3);
                if ($generalsheet) {
                    $allgroupsworksheet->write_string($j, 0, $groupname, $headlineformat);
                    $allgroupsworksheet->merge_cells($j, 0, $j, 3);
                }

                // Groupinfo on top!
                $groupworksheets[$key]->write_string(2, 0, $groupinfo[0][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(2, 0, 2, 1);
                $groupworksheets[$key]->write(2, 2, $groupinfo[0][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(3, 0, $groupinfo[1][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(3, 0, 3, 1);
                $groupworksheets[$key]->write(3, 2, $groupinfo[1][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(4, 0, $groupinfo[2][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(4, 0, 4, 1);
                $groupworksheets[$key]->write(4, 2, $groupinfo[2][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(5, 0, $groupinfo[3][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(5, 0, 5, 1);
                $groupworksheets[$key]->write(5, 2, $groupinfo[3][1], $groupinfoformat2);
                if ($generalsheet) {
                    $allgroupsworksheet->write_string($j + 2, 0, $groupinfo[0][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 2, 0, $j + 2, 1);
                    $allgroupsworksheet->write($j + 2, 2, $groupinfo[0][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 3, 0, $groupinfo[1][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 3, 0, $j + 3, 1);
                    $allgroupsworksheet->write($j + 3, 2, $groupinfo[1][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 4, 0, $groupinfo[2][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 4, 0, $j + 4, 1);
                    $allgroupsworksheet->write($j + 4, 2, $groupinfo[2][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 5, 0, $groupinfo[3][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 5, 0, $j + 5, 1);
                    $allgroupsworksheet->write($j + 5, 2, $groupinfo[3][1], $groupinfoformat2);
                }

                // Registrations and queue headline!
                // First the headline!
                $k = 0;
                $groupworksheets[$key]->write_string(7, $k, get_string('status', 'grouptool'),
                                                      $regheadformat);
                $k++; // ...k = 1!

                // First we output every namefield from used by fullname in exact the defined order!
                foreach ($namefields as $namefield) {
                    $groupworksheets[$key]->write_string(7, $k, get_user_field_name($namefield), $regheadformat);
                    $hidden = in_array($namefield, $collapsed) ? true : false;
                    $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                    $k++;
                }
                // ...k = n!
                if (!empty($CFG->showuseridentity)) {
                    $fields = explode(',', $CFG->showuseridentity);
                    $curfieldcount = 1;
                    foreach ($fields as $field) {
                        if ($curfieldcount == count($fields)) {
                            $groupworksheets[$key]->write_string(7, $k, get_user_field_name($field), $regheadlast);
                        } else {
                            $groupworksheets[$key]->write_string(7, $k, get_user_field_name($field), $regheadformat);
                            $curfieldcount++;
                        }
                        $hidden = in_array($field, $collapsed) ? true : false;
                        $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                        $groupworksheets[$key]->set_column($k, $k, $columnwidth[$field], null, $hidden);
                        $k++; // ...k = n+x!
                    }
                } else {
                    $groupworksheets[$key]->write_string(7, $k, get_user_field_name('idnumber'), $regheadformat);
                    $hidden = in_array('idnumber', $collapsed) ? true : false;
                    $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                    $k++; // ...k = n+1!

                    $groupworksheets[$key]->write_string(7, $k, get_user_field_name('email'), $regheadlast);
                    $hidden = in_array('email', $collapsed) ? true : false;
                    $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['email'], null, $hidden);
                    $k++; // ...k = n+2!
                }

                if ($generalsheet) {
                    $k = 0;
                    $allgroupsworksheet->write_string($j + 7, $k, get_string('status', 'grouptool'),
                                                      $regheadformat);
                    $k++;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $allgroupsworksheet->write_string($j + 7, $k, get_user_field_name($namefield), $regheadformat);
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k = n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        $curfieldcount = 1;
                        foreach ($fields as $field) {
                            if ($curfieldcount == count($fields)) {
                                $allgroupsworksheet->write_string($j + 7, $k, get_user_field_name($field), $regheadlast);
                            } else {
                                $allgroupsworksheet->write_string($j + 7, $k, get_user_field_name($field), $regheadformat);
                                $curfieldcount++;
                            }
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $allgroupsworksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $allgroupsworksheet->write_string($j + 7, $k, get_user_field_name('idnumber'), $regheadformat);
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $k++; // ...k = n+1!

                        $allgroupsworksheet->write_string($j + 7, $k, get_user_field_name('email'), $regheadlast);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $k++; // ...k = n+2!
                    }
                }
                // Now the registrations!
                $i = 0;
                if (!empty($regdata)) {
                    foreach ($regdata as $reg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, $reg['status'],
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($generalsheet) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['status'],
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                } else if (count($mregdata) == 0) {
                    $groupworksheets[$key]->write_string(8 + $i, 0,
                                                         get_string('no_registrations',
                                                                    'grouptool'),
                                                         $noregentriesformat);
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($generalsheet) {
                        $allgroupsworksheet->write_string($j + 8 + $i, 0,
                                                          get_string('no_registrations',
                                                                     'grouptool'),
                                                          $noregentriesformat);
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }

                if (count($mregdata) >= 1) {
                    foreach ($mregdata as $mreg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, '?',
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($generalsheet) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, '?',
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheets->write_string($j + 8 + $i, $k, $mreg['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                }
                // Don't forget the queue!
                if (!empty($queuedata)) {
                    foreach ($queuedata as $queue) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, $queue['rank'],
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($generalsheet) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['rank'],
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheets->write_string($j + 8 + $i, $k, $queue['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                } else {
                    $groupworksheets[$key]->write_string(8 + $i, 0,
                                                         get_string('nobody_queued', 'grouptool'),
                                                         $noqueueentriesformat);
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($generalsheet) {
                        $allgroupsworksheet->write_string($j + 8 + $i, 0,
                                                          get_string('nobody_queued',
                                                                     'grouptool'),
                                                          $noqueueentriesformat);
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }
                $j += 9 + $i;    // One row space between groups!
            }

        }
    }

    /**
     * outputs generated ods-file for overview (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_ods($groupid=0, $groupingid=0) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').' '.get_string('overview', 'grouptool');
        }
        $workbook = new MoodleODSWorkbook("-");

        $groups = $this->group_overview_table($groupingid, $groupid, true);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename.'.ods');
        $workbook->close();
    }

    /**
     * outputs generated xlsx-file for overview (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_xlsx($groupid = 0, $groupingid = 0) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_group_name($groupid).'_'.
                                       get_string('overview', 'grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_grouping_name($groupingid).'_'.
                                       get_string('overview', 'grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       get_string('group').' '.get_string('overview', 'grouptool'));
        }
        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $groups = $this->group_overview_table($groupingid, $groupid, true);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename.'.xlsx');
        $workbook->close();
    }

    /**
     * get object containing informatino about syncronisation of active-groups with moodle-groups
     *
     * @global object $DB
     * @param int $grouptoolid optional get stats for this grouptool-instance
     *                                  uses $this->instance if zero
     * @return array (global out of sync, array of objects with sync-status for each group)
     */
    private function get_sync_status($grouptoolid = 0) {
        global $DB;
        $outofsync = false;

        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }

        // We use MAX to trick postgres into thinking this is a full group_by statement!
        $sql = "SELECT agrps.id agrpid, MAX(agrps.groupid) groupid,
                       COUNT(DISTINCT reg.userid) grptoolregs,
                       COUNT(DISTINCT mreg.userid) mdlregs
                FROM {grouptool_agrps} agrps
                    LEFT JOIN {grouptool_registered} reg ON agrps.id = reg.agrpid AND reg.modified_by >= 0
                    LEFT JOIN {groups_members} mreg ON agrps.groupid = mreg.groupid
                                                       AND reg.userid = mreg.userid
                WHERE agrps.active = 1 AND agrps.grouptoolid = ?
                GROUP BY agrps.id";
        $return = $DB->get_records_sql($sql, array($grouptoolid));

        foreach ($return as $key => $group) {
            $return[$key]->status = ($group->grptoolregs > $group->mdlregs) ?
                                    GROUPTOOL_OUTDATED : GROUPTOOL_UPTODATE;
            $outofsync |= ($return[$key]->status == GROUPTOOL_OUTDATED);
        }
        return array($outofsync, $return);
    }

    /**
     * push in grouptool registered users to moodle-groups
     *
     * @global object $DB
     * @param int $groupid optional only for this group
     * @param int $groupingid optional only for this grouping
     * @param bool $previewonly optional get only the preview
     * @return array($error, $message)
     */
    public function push_registrations($groupid=0, $groupingid=0, $previewonly=false) {
        global $DB, $CFG;

        // Trigger the event!
        \mod_grouptool\event\registration_push_started::create_from_object($this->cm)->trigger();

        $userinfo = get_enrolled_users($this->context);
        $return = array();
        // Get active groups filtered by groupid, grouping_id, grouptoolid!
        $agrps = $this->get_active_groups(true, false, 0, $groupid, $groupingid);
        foreach ($agrps as $groupid => $agrp) {
            foreach ($agrp->registered as $reg) {
                $info = new stdClass();
                if (!key_exists($reg->userid, $userinfo)) {
                    $userinfo[$reg->userid] = $DB->get_record('user', array('id' => $reg->userid));
                }
                $info->username = fullname($userinfo[$reg->userid]);
                $info->groupname = $agrp->name;
                if (!groups_is_member($groupid, $reg->userid)) {
                    // Add to group if is not already!
                    if (!$previewonly) {
                        if (!is_enrolled($this->context, $reg->userid)) {
                            /*
                             * if user's not enrolled already we force manual enrollment in course,
                             * so we can add the user to the group
                             */
                            require_once($CFG->dirroot.'/enrol/manual/locallib.php');
                            require_once($CFG->libdir.'/accesslib.php');
                            if (!$enrolmanual = enrol_get_plugin('manual')) {
                                throw new coding_exception('Can not instantiate enrol_manual');
                            }
                            if (!$instance = $DB->get_record('enrol', array('courseid' => $this->course->id,
                                                                            'enrol'    => 'manual'),
                                                             '*', IGNORE_MISSING)) {
                                if ($instanceid = $enrolmanual->add_default_instance($this->course)) {
                                    $instance = $DB->get_record('enrol',
                                                                array('courseid' => $this->course->id,
                                                                      'enrol'    => 'manual'), '*',
                                                                MUST_EXIST);
                                }
                            }
                            if ($instance != false) {
                                $archroles = get_archetype_roles('student');
                                $archrole = array_shift($archroles);
                                $enrolmanual->enrol_user($instance, $reg->userid, $archrole->id, time());
                            } else {
                                $message .= html_writer::tag('div',
                                                             $OUTPUT->notification(get_string('cant_enrol',
                                                                                              'grouptool'),
                                                             'notifyproblem'));
                            }
                        }
                        if (groups_add_member($groupid, $reg->userid)) {
                            $return[] = html_writer::tag('div',
                                                         get_string('added_member', 'grouptool',
                                                                    $info),
                                                         array('class' => 'notifysuccess'));
                        } else {
                            $return[] = html_writer::tag('div',
                                                         get_string('could_not_add', 'grouptool',
                                                                    $info),
                                                         array('class' => 'notifyproblem'));
                        }
                    } else {
                        $return[] = html_writer::tag('div', get_string('add_member', 'grouptool',
                                                                       $info),
                                                     array('class' => 'notifysuccess'));
                    }
                } else {
                    $return[] = html_writer::tag('div', get_string('already_member', 'grouptool',
                                                                   $info),
                                                 array('class' => 'ignored'));
                }
            }
        }
        switch (count($return)) {
            default:
                return array(false, implode("<br />\n", $return));
                break;
            case 1:
                return array(false, current($return));
                break;
            case 0:
                return array(true, get_string('nothing_to_push', 'grouptool'));
                break;
        }

    }

    /**
     * Render link for Member-List
     *
     * @global object $CFG
     * @global object $PAGE
     * @param int $agrpid active group id, for which the members should be displayed
     * @param string $groupname name of the group
     * @return string HTML fragment
     */
    private function render_members_link($agrpid, $groupname) {
        global $CFG, $PAGE;

        // Init Members-Popup-JS!
        $PAGE->requires->yui_module('moodle-mod_grouptool-memberspopup', 'M.mod_grouptool.init_memberspopup');
        $PAGE->requires->string_for_js('loading', 'mod_grouptool');

        $output = get_string('show_members', 'grouptool');

        // Now create the link around it - we need https on loginhttps pages!
        $url = new moodle_url($CFG->httpswwwroot.'/mod/grouptool/showmembers.php',
                              array('agrpid'    => $agrpid,
                                    'contextid' => $this->context->id));

        $attributes = array('href' => $url, 'title' => get_string('show_members', 'grouptool'));
        $id = html_writer::random_id('showmembers');
        $attributes['id'] = $id;
        $output = html_writer::tag('a', $output, $attributes);

        // And finally wrap in a span!
        return html_writer::tag('span', $output, array('class' => 'showmembers memberstooltip'));
    }

    /**
     * view overview tab
     *
     * @global object $PAGE
     * @global object $OUTPUT
     */
    public function view_overview() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $url = new moodle_url($PAGE->url, array('sesskey'     => sesskey(),
                                                'groupid'     => $groupid,
                                                'groupingid'  => $groupingid,
                                                'orientation' => $orientation));

            // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed?!
            $hideform = 0;
            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                list($error, $message) = $this->push_registrations($groupid, $groupingid);
            }
            if ($error) {
                echo $OUTPUT->notification($message, 'notifyproblem');
            } else {
                echo $OUTPUT->notification($message, 'notifysuccess');
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hideform = 1;

            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                // Try only!
                list($error, $message) = $this->push_registrations($groupid, $groupingid, true);
                $attr = array();
                $attr['confirm'] = 1;
                $attr['pushtomdl'] = 1;
                $attr['sesskey'] = sesskey();

                $continue = new moodle_url($PAGE->url, $attr);
                $cancel = new moodle_url($PAGE->url);

                if ($error) {
                    $continue->remove_params('confirm', 'group');
                    $continue = new single_button($continue, get_string('continue'), 'get');
                    $cancel = null;
                }
                echo $this->confirm($message, $continue, $cancel);
            } else {
                $hideform = 0;
            }
        } else {
            $hideform = 0;
        }

        if (!$hideform) {
            $groupings = groups_get_all_groupings($this->course->id);
            $options = array(0 => get_string('all'));
            if (count($groupings)) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = $grouping->name;
                }
            }
            $groupingselect = new single_select($url, 'groupingid', $options, $groupingid, false);

            $groups = $this->get_active_groups(false, false, 0, 0, $groupingid);
            $options = array(0 => get_string('all'));
            if (count($groups)) {
                foreach ($groups as $group) {
                    $options[$group->id] = $group->name;
                }
            }
            if (!key_exists($groupid, $options)) {
                $groupid = 0;
                $url->param('groupid', 0);
                echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping',
                                                                   'grouptool').
                                                        html_writer::empty_tag('br').
                                                        get_string('switched_to_all_groups',
                                                                   'grouptool'),
                                                        'notifyproblem'), 'generalbox centered');
            }
            $groupselect = new single_select($url, 'groupid', $options, $groupid, false);

            $options = array(0 => get_string('portrait', 'grouptool'),
                             1 => get_string('landscape', 'grouptool'));
            $orientationselect = new single_select($url, 'orientation', $options, $orientation, false);

            $syncstatus = $this->get_sync_status();

            if ($syncstatus[0]) {
                /*
                 * Out of sync? --> show button to get registrations from grouptool to moodle
                 * (just register not already registered persons and let the others be)
                 */
                $url = new moodle_url($PAGE->url, array('pushtomdl' => 1, 'sesskey' => sesskey()));
                $button = new single_button($url, get_string('updatemdlgrps', 'grouptool'));
                echo $OUTPUT->box(html_writer::empty_tag('br').
                                  $OUTPUT->render($button).
                                  html_writer::empty_tag('br'), 'generalbox centered');
            }

            echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupingselect),
                                  array('class' => 'centered grouptool_overview_filter')).
                 html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupselect),
                                  array('class' => 'centered grouptool_overview_filter')).
                 html_writer::tag('div', get_string('orientation', 'grouptool').'&nbsp;'.
                                         $OUTPUT->render($orientationselect),
                                  array('class' => 'centered grouptool_userlist_filter'));

            // If we don't only get the data, the output happens directly per group!
            $this->group_overview_table($groupingid, $groupid);
        }
    }

    /**
     * get information about particular users with their registrations/queues
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $OUTPUT
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group
     * @param int|array $userids optional get only this user(s)
     * @param array $orderby array how data should be sorted (column as key and ASC/DESC as value)
     * @return array of objects records from DB with all necessary data
     */
    public function get_user_data($groupingid = 0, $groupid = 0, $userids = 0, $orderby = array()) {
        global $DB, $PAGE, $OUTPUT;

        // After which table-fields can we sort?
        $sortable = array('firstname', 'lastname', 'idnumber', 'email');

        $return = new stdClass();

        // Indexed by agrpid!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid, false);
        $agrpids = array_keys($agrps);
        if (!empty($agrpids)) {
            list($agrpsql, $agrpparams) = $DB->get_in_or_equal($agrpids);
        } else {
             $agrpsql = '';
             $agrpparams = array();
             echo $OUTPUT->box($OUTPUT->notification(get_string('no_groups_to_display',
                                                                'grouptool'),
                                                     'notifyproblem'),
                               'generalbox centered');
        }

        if (!empty($userids)) {
            if (!is_array($userids)) {
                $userids = array($userids);
            }
            list($usersql, $userparams) = $DB->get_in_or_equal($userids);
        } else {
            $usersql = ' LIKE *';
            $userparams = array();
        }

        $extrauserfields = get_extra_user_fields_sql($this->context, 'u');
        $mainuserfields = user_picture::fields('u', array('idnumber', 'email'));
        $orderbystring = "";
        if (!empty($orderby)) {
            foreach ($orderby as $field => $direction) {
                if (in_array($field, $sortable)) {
                    if ($orderbystring != "") {
                        $orderbystring .= ", ";
                    } else {
                        $orderbystring .= " ORDER BY";
                    }
                    $orderbystring .= " ".$field." ".
                                      ((!empty($direction) && $direction == 'ASC') ? 'ASC' : 'DESC');
                } else {
                    unset($orderby[$field]);
                }
            }
        }

        $sql = "SELECT $mainuserfields $extrauserfields ".
               "FROM {user} u ".
               "WHERE u.id ".$usersql.
               $orderbystring;
        $params = array_merge($userparams);

        $data = $DB->get_records_sql($sql, $params);

        // Add reg and queue data...
        if (!empty($agrpsql)) {
            foreach ($data as $idx => &$cur) {
                $sql = "SELECT agrps.id
                          FROM {grouptool_registered} regs
                     LEFT JOIN {grouptool_agrps}      agrps ON regs.agrpid = agrps.id
                     LEFT JOIN {groups}               grps  ON agrps.groupid = grps.id
                         WHERE regs.modified_by >= 0
                               AND regs.userid = ?
                               AND regs.agrpid ".$agrpsql;
                $params = array_merge(array($cur->id), $agrpparams);
                $cur->regs = $DB->get_fieldset_sql($sql, $params);
                $sql = "SELECT agrps.id
                          FROM {grouptool_queued} queued
                     LEFT JOIN {grouptool_agrps}  agrps ON queued.agrpid = agrps.id
                     LEFT JOIN {groups}           grps  ON agrps.groupid = grps.id
                         WHERE queued.userid = ?
                               AND queued.agrpid ".$agrpsql;
                $params = array_merge(array($cur->id), $agrpparams);
                $cur->queued = $DB->get_fieldset_sql($sql, $params);
            }
        }

        return $data;
    }

    /**
     * returns picture indicating sort-direction if data is primarily sorted by this column
     * or empty string if not
     *
     * @global object $OUTPUT
     * @param array $oderby array containing current state of sorting
     * @param string $search columnname to print sortpic for
     * @return string html fragment with sort-pic or empty string
     */
    private function pic_if_sorted($orderby = array(), $search) {
        global $OUTPUT;
        $keys = array_keys($orderby);
        if (reset($keys) == $search) {
            if ($orderby[$search] == 'ASC') {
                return $OUTPUT->pix_icon('t/up', 'sorted ASC');
            } else {
                return $OUTPUT->pix_icon('t/down', 'sorted DESC');
            }
        }

        return "";
    }

    /**
     * returns collapselink (= symbol to show column or column-name and symbol to hide column)
     *
     * @global object $PAGE
     * @global object $OUTPUT
     * @param array $collapsed array with collapsed columns
     * @param string $search column-name to print link for
     * @return string html-fragment with icon to show column or column header text with icon to hide
     *                              column
     */
    private function collapselink($collapsed = array(), $search) {
        global $PAGE, $OUTPUT;
        if (in_array($search, $collapsed)) {
            $url = new moodle_url($PAGE->url, array('tshow' => $search));
            $pic = $OUTPUT->pix_icon('t/switch_plus', 'show');
        } else {
            $url = new moodle_url($PAGE->url, array('thide' => $search));
            $pic = $OUTPUT->pix_icon('t/switch_minus', 'hide');
        }
        return html_writer::tag('div', html_writer::link($url, $pic),
                                                         array('class' => 'collapselink'));
    }

    /**
     * get all data necessary for displaying/exporting userlist table
     *
     * @global object $OUTPUT
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     * @global object $SESSION
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     * @param bool $onlydata optional return object with raw data not html-fragment-string
     * @return string|object either html-fragment representing table or raw data as object
     */
    public function userlist_table($groupingid = 0, $groupid = 0, $orderby = array(),
                                   $collapsed = array(), $onlydata = false) {
        global $OUTPUT, $CFG, $DB, $PAGE, $SESSION;

        $context = context_module::instance($this->cm->id);
        if (!isset($SESSION->mod_grouptool->userlist)) {
            $SESSION->mod_grouptool->userlist = new stdClass();
        }
        // Handles order direction!
        if (!isset($SESSION->mod_grouptool->userlist->orderby)) {
            $SESSION->mod_grouptool->userlist->orderby = array();
        }
        $orderby = $SESSION->mod_grouptool->userlist->orderby;
        if ($tsort = optional_param('tsort', 0, PARAM_ALPHA)) {
            $olddir = 'DESC';
            if (key_exists($tsort, $orderby)) {
                $olddir = $orderby[$tsort];
                unset($orderby[$tsort]);
            }
            // Insert as first element and rebuild!
            $oldorderby = array_keys($orderby);
            $oldorderdir = array_values($orderby);
            array_unshift($oldorderby, $tsort);
            array_unshift($oldorderdir, (($olddir == 'DESC') ? 'ASC' : 'DESC'));
            $orderby = array_combine($oldorderby, $oldorderdir);
            $SESSION->mod_grouptool->userlist->orderby = $orderby;
        }

        // Handles collapsed columns!
        if (!isset($SESSION->mod_grouptool->userlist->collapsed)) {
            $SESSION->mod_grouptool->userlist->collapsed = array();
        }
        $collapsed = $SESSION->mod_grouptool->userlist->collapsed;
        if ($thide = optional_param('thide', 0, PARAM_ALPHA)) {
            if (!in_array($thide, $collapsed)) {
                array_push($collapsed, $thide);
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }
        if ($tshow = optional_param('tshow', 0, PARAM_ALPHA)) {
            foreach ($collapsed as $key => $value) {
                if ($value == $tshow) {
                    unset($collapsed[$key]);
                }
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }

        if (!$onlydata) {
            flush();
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/mod/grouptool/download.php',
                                          array('id'          => $this->cm->id,
                                                'groupingid'  => $groupingid,
                                                'groupid'     => $groupid,
                                                'orientation' => $orientation,
                                                'sesskey'     => sesskey(),
                                                'tab'         => 'userlist'));
        } else {
            $return = array();
        }

        // Get all ppl that are allowed to register!
        list($esql, $params) = get_enrolled_sql($this->context, 'mod/grouptool:register');

        $sql = "SELECT u.id FROM {user} u ".
               "LEFT JOIN ($esql) eu ON eu.id=u.id ".
               "WHERE u.deleted = 0 AND eu.id=u.id ";
        if (!empty($groupingid)) {
            // Get all groupings groups!
            $groups = groups_get_all_groups($this->course->id, 0, $groupingid);
            $ufields = user_picture::fields('u', array('idnumber'));
            $groupingsusers = groups_get_grouping_members($groupingid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupingusers)) {
                $groupingusers = array();
            } else {
                $groupingusers = array_keys($groupingusers);
            }
            list($groupssql, $groupsparams) = $DB->get_in_or_equal(array_keys($groups));
            $groupingusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid ".$groupssql, $groupsparams);
            $groupingusers = array_merge($groupingusers, $groupingusers2);
            if (empty($groupingusers)) {
                $userssql = " = :groupingparam";
                $groupingparams = array('groupingparam' => -1);
            } else {
                list($userssql, $groupingparams) = $DB->get_in_or_equal($groupingusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupingparams);
        }
        if (!empty($groupid)) {
            // Same as with groupingid but just with 1 group!
            // Get all group members!
            $ufields = user_picture::fields('u', array('idnumber'));
            $groupusers = groups_get_members($groupid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupusers)) {
                $groupusers = array();
            } else {
                $groupusers = array_keys($groupusers);
            }
            $groupusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid = ?", array($groupid));
            $groupusers = array_merge($groupusers, $groupusers2);
            if (empty($groupusers)) {
                $userssql = " = :groupparam";
                $groupparams = array('groupparam' => -1);
            } else {
                list($userssql, $groupparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupparams);
        }
        $users = $DB->get_records_sql($sql, $params);

        if (!$onlydata) {
            if (has_capability('mod/grouptool:export', $context)) {
                $txturl = new moodle_url($downloadurl, array('format' => GROUPTOOL_TXT));
                $xlsxurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_XLSX));
                $pdfurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_PDF));
                $odsurl = new moodle_url($downloadurl, array('format' => GROUPTOOL_ODS));
                $downloadlinks = html_writer::tag('span', get_string('downloadall').":",
                                                  array('class' => 'title')).'&nbsp;'.
                        html_writer::link($txturl, '.TXT').'&nbsp;'.
                        html_writer::link($xlsxurl, '.XLSX').'&nbsp;'.
                        html_writer::link($pdfurl, '.PDF').'&nbsp;'.
                        html_writer::link($odsurl, '.ODS');
                echo html_writer::tag('div', $downloadlinks, array('class' => 'download all'));
            }
            flush();
        }

        if (!empty($users)) {
            $users = array_keys($users);
            $userdata = $this->get_user_data($groupingid, $groupid, $users, $orderby);
        } else {
            if (!$onlydata) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('no_users_to_display',
                                                                   'grouptool'),
                                                        'notifyproblem'),
                                  'centered generalbox');
            } else {
                return get_string('no_users_to_display', 'grouptool');
            }
        }
        $groupinfo = $this->get_active_groups(false, false, 0, $groupid, $groupingid, false);

        if (!$onlydata) {
            echo html_writer::start_tag('table',
                                        array('class' => 'centeredblock userlist table table-striped table-hover table-condensed'));

            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', $this->collapselink($collapsed, 'picture'), array('class' => ''));
            flush();
            if (!in_array('fullname', $collapsed)) {
                $firstnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                  array('tsort' => 'firstname')),
                                                   get_string('firstname').
                                                   $this->pic_if_sorted($orderby, 'firstname'));
                $surnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                array('tsort' => 'lastname')),
                                                  get_string('lastname').
                                                  $this->pic_if_sorted($orderby, 'lastname'));
                $fullname = html_writer::tag('div', get_string('fullname').
                                                    html_writer::empty_tag('br').
                                                    $firstnamelink.'&nbsp;/&nbsp;'.$surnamelink);
                echo html_writer::tag('th', $fullname.$this->collapselink($collapsed, 'fullname'), array('class' => ''));
            } else {
                echo html_writer::tag('th', $this->collapselink($collapsed, 'fullname'), array('class' => ''));
            }
            if (!in_array('idnumber', $collapsed)) {
                $idnumberlink = html_writer::link(new moodle_url($PAGE->url,
                                                                 array('tsort' => 'idnumber')),
                                                  get_string('idnumber').
                                                  $this->pic_if_sorted($orderby, 'idnumber'));
                echo html_writer::tag('th', $idnumberlink.$this->collapselink($collapsed, 'idnumber'), array('class' => ''));
            } else {
                echo html_writer::tag('th', $this->collapselink($collapsed, 'idnumber'), array('class' => ''));
            }
            if (!in_array('email', $collapsed)) {
                $emaillink = html_writer::link(new moodle_url($PAGE->url, array('tsort' => 'email')),
                                               get_string('email').
                                               $this->pic_if_sorted($orderby, 'email'));
                echo html_writer::tag('th', $emaillink.$this->collapselink($collapsed, 'email'), array('class' => ''));
            } else {
                echo html_writer::tag('th', $this->collapselink($collapsed, 'email'), array('class' => ''));
            }
            if (!in_array('registrations', $collapsed)) {
                $registrationslink = get_string('registrations', 'grouptool');
                echo html_writer::tag('th', $registrationslink.
                                            $this->collapselink($collapsed, 'registrations'), array('class' => ''));
            } else {
                echo html_writer::tag('th', $this->collapselink($collapsed, 'registrations'), array('class' => ''));
            }
            if (!in_array('queues', $collapsed)) {
                $queueslink = get_string('queues', 'grouptool').' ('.get_string('rank', 'grouptool').')';
                echo html_writer::tag('th', $queueslink.
                                            $this->collapselink($collapsed, 'queues'), array('class' => ''));
            } else {
                echo html_writer::tag('th', $this->collapselink($collapsed, 'queues'), array('class' => ''));
            }
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
        } else {
            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = get_all_user_name_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $head = array('name'          => get_string('fullname'));
            foreach ($namefields as $namefield) {
                $head[$namefield] = get_user_field_name($namefield);
            }
            if (empty($CFG->showuseridentity)) {
                $head['idnumber'] = get_user_field_name('idnumber');
            } else {
                $fields = explode(',', $CFG->showuseridentity);
                foreach ($fields as $field) {
                    $head[$field] = get_user_field_name($field);
                }
            }
            $head['idnumber'] = get_user_field_name('idnumber');
            $head['email']         = get_user_field_name('email');
            $head['registrations'] = get_string('registrations', 'grouptool');
            $head['queues']        = get_string('queues', 'grouptool').' ('.get_string('rank', 'grouptool').')';
        }

        if (!$onlydata) {
            echo html_writer::start_tag('tbody');
        }
        if (!empty($userdata)) {
            core_php_time_limit::raise(5 * count($userdata));
            foreach ($userdata as $key => $user) {
                if (!$onlydata) {
                    echo html_writer::start_tag('tr', array('class' => ''));

                    $userlink = new moodle_url($CFG->wwwroot.'/user/view.php',
                                               array('id'     => $user->id,
                                                     'course' => $this->course->id));
                    if (!in_array('picture', $collapsed)) {
                        $picture = html_writer::link($userlink, $OUTPUT->user_picture($user));
                        echo html_writer::tag('td', $picture, array('class' => ''));
                    } else {
                        $picture = "";
                    }
                    if (!in_array('fullname', $collapsed)) {
                        $fullname = html_writer::link($userlink, fullname($user));
                        echo html_writer::tag('td', $fullname, array('class' => ''));
                    } else {
                        $fullname = "";
                    }
                    if (!in_array('idnumber', $collapsed)) {
                        $idnumber = $user->idnumber;
                        echo html_writer::tag('td', $idnumber, array('class' => ''));
                    } else {
                        $idnumber = "";
                    }
                    if (!in_array('email', $collapsed)) {
                        $email = $user->email;
                        echo html_writer::tag('td', $email, array('class' => ''));
                    } else {
                        $email = "";
                    }
                    if (!in_array('registrations', $collapsed)) {
                        if (!empty($user->regs)) {
                            $registrations = array();
                            foreach ($user->regs as $reg) {
                                $grouplink = new moodle_url($PAGE->url,
                                                            array('tab'     => 'overview',
                                                                  'groupid' => $groupinfo[$reg]->id));
                                $registrations[] = html_writer::link($grouplink,
                                                                     $groupinfo[$reg]->name);
                            }
                        } else {
                            $registrations = array('-');
                        }
                        $registrations = implode(html_writer::empty_tag('br'), $registrations);
                        echo html_writer::tag('td', $registrations, array('class' => ''));
                    } else {
                        $registrations = "";
                    }
                    if (!in_array('queues', $collapsed)) {
                        if (!empty($user->queued)) {
                            $queueentries = array();
                            foreach ($user->queued as $queue) {
                                $grouplink = new moodle_url($PAGE->url,
                                                            array('tab'     => 'overview',
                                                                  'groupid' => $groupinfo[$queue]->id));
                                $groupdata = $this->get_active_groups(false, true, $queue);
                                $groupdata = current($groupdata);
                                $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                                $groupdata = null;
                                unset($groupdata);
                                if (empty($rank)) {
                                    $rank = '*';
                                }
                                $queueentries[] = html_writer::link($grouplink,
                                                                    $groupinfo[$queue]->name." (#".$rank.")");
                            }
                        } else {
                            $queueentries = array('-');
                        }
                        $queueentries = implode(html_writer::empty_tag('br'), $queueentries);
                        echo html_writer::tag('td', $queueentries, array('class' => ''));
                    } else {
                        $queueentries = "";
                    }
                    echo html_writer::end_tag('tr');
                    flush();
                    $picture = null;
                    unset($picture);
                    $fullname = null;
                    unset($fullname);
                    $idnumber = null;
                    unset($idnumber);
                    $email = null;
                    unset($email);
                    $registrations = null;
                    unset($registrations);
                    $queueentries = null;
                    unset($queueentries);
                } else {
                    $row = array();
                    $row['name'] = fullname($user);

                    foreach ($namefields as $namefield) {
                        $row[$namefield] = $user->$namefield;
                        $user->namefield = null;
                        unset($user->namefield);
                    }
                    $row['idnumber'] = $user->idnumber;
                    $row['email'] = $user->email;
                    if (empty($CFG->showuseridentity)) {
                        $row['idnumber'] = $user->idnumber;
                        $user->idnumber = null;
                        unset($user->idnumber);
                        $row['email'] = $user->email;
                        $user->email = null;
                        unset($user->email);
                    } else {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $row[$field] = $user->$field;
                            $user->$field = null;
                            unset($user->$field);
                        }
                    }
                    if (!empty($user->regs)) {
                        $registrations = array();
                        foreach ($user->regs as $reg) {
                            $registrations[] = $groupinfo[$reg]->name;
                        }
                        $row['registrations'] = $registrations;
                    } else {
                        $row['registrations'] = array();
                    }
                    $user->regs = null;
                    unset($user->regs);
                    if (!empty($user->queued)) {
                        $queueentries = array();
                        foreach ($user->queued as $queue) {
                            $groupdata = $this->get_active_groups(false, true, $queue);
                            $groupdata = current($groupdata);
                            $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                            if (empty($rank)) {
                                $rank = '*';
                            }
                            $queueentries[] = array('rank' => $rank,
                                                    'name' => $groupinfo[$queue]->name);
                        }
                        $row['queues'] = $queueentries;
                    } else {
                        $row['queues'] = array();
                    }
                    $user->queues = null;
                    unset($user->queues);
                    $rows[] = $row;
                    $row = null;
                    unset($row);
                }
            }
        }
        if (!$onlydata) {
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        } else {
            return array_merge(array($head), $rows);
        }
    }

    /**
     * outputs generated pdf-file for userlist (forces download)
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_pdf($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        global $USER;
        require_once('./grouptool_pdf.php');

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $pdf = new grouptool_pdf();
        $pdf->setFontSubsetting(false);

        // Set orientation (P/L)!
        $orientation = (optional_param('orientation', 0, PARAM_BOOL) == 0) ? 'P' : 'L';
        $pdf->setPageOrientation($orientation);

        // Set document information!
        $pdf->SetCreator('TUWEL');
        $pdf->SetAuthor(fullname($USER));

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupingid) || !empty($groupid)) {
            $viewname = "";
            if (!empty($groupingid)) {
                $viewname .= groups_get_grouping_name($groupingid);
            } else {
                $viewname .= get_string('all');
            }
            if ($viewname != "") {
                $viewname .= " / ";
            }
            if (!empty($groupid)) {
                $viewname .= groups_get_group_name($groupid);
            } else {
                $viewname .= get_string('all');
            }
        } else {
            $viewname = get_string('all').' '.get_string('groups');
        }

        $pdf->set_userlist_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                    $viewname);

        // Set header/footer!
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);

        $textsize = optional_param('textsize', 1, PARAM_INT);
        switch ($textsize){
            case "0":
                $pdf->SetFontSize(8);
                break;
            case "1":
                $pdf->SetFontSize(10);
                break;
            case "2":
                $pdf->SetFontSize(12);
                break;
        }

        // Set margins!
        if (1) {
            $pdf->SetMargins(10, 30, 10, true); // Left Top Right!
        } else {
            $pdf->SetMargins(10, 10, 10, true);
        }
        // Set default monospaced font!
        $pdf->SetDefaultMonospacedFont(/*PDF_FONT_MONOSPACED*/'freeserif');

        // Set margins!
        $pdf->SetHeaderMargin(7);
        $pdf->SetFooterMargin(7);

        // Set auto page breaks!
        $pdf->SetAutoPageBreak(true, /*PDF_MARGIN_BOTTOM*/10);

        // Set image scale factor!
        $pdf->setImageScale(/*PDF_IMAGE_SCALE_RATIO*/1);

        /*
         * ---------------------------------------------------------
         */

        // Set font!
        $pdf->SetFont('freeserif', '');
        $pdf->addPage($orientation, 'A4', false, false);
        if (count($data) > 1) {
            $user = reset($data);
            $name = $user['name'];
            $idnumber = $user['idnumber'];
            $email = $user['email'];
            $regdata = $user['registrations'];
            $queuedata = $user['queues'];
            $pdf->add_userdata($name, $idnumber, $email, $regdata, $queuedata, true);
            while (next($data)) {
                $user = current($data);
                $name = $user['name'];
                $idnumber = $user['idnumber'];
                $email = $user['email'];
                $regdata = $user['registrations'];
                $queuedata = $user['queues'];
                $pdf->add_userdata($name, $idnumber, $email, $regdata, $queuedata);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'),
                            'B', 'LRTB', false, 1, null, null, true, 1, true, false,
                            $pdf->getLastH(), 'M', true);
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                        get_string('userlist', 'grouptool');
        }

        $pdf->Output($filename.'.pdf', 'D');
        exit();
    }

    /**
     * returns data for userlist
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     * @return object raw data
     */
    public function download_userlist_raw($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        return $this->userlist_table($groupid, $groupingid, $orderby, $collapsed, true);
    }

    /**
     * outputs generated txt-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_txt($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        ob_start();

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        $return = "";
        $lines = array();
        $users = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);
        if (count($users) > 0) {
            foreach ($users as $key => $user) {
                if ($key == 0) { // Headline!
                    $lines[] = get_string('fullname')."\t".
                               get_string('idnumber')."\t".
                               get_string('email')."\t".
                               get_string('registrations', 'grouptool')."\t".
                               get_string('queues', 'grouptool')." (".get_string('rank', 'grouptool').")";
                } else {
                    $rows = max(array(1, count($user['registrations']), count($user['queues'])));

                    for ($i = 0; $i < $rows; $i++) {
                        $line = "";
                        if ($i == 0) {
                            $line = $user['name']."\t".$user['idnumber']."\t".$user['email'];
                        } else {
                            $line = "\t\t";
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('no_registrations', 'grouptool');
                        } else if (key_exists($i, $user['registrations'])) {
                            $line .= "\t".$user['registrations'][$i];
                        } else {
                            $line .= "\t";
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('nowhere_queued', 'grouptool');
                        } else if (key_exists($i, $user['queues'])) {
                            $line .= "\t".$user['queues'][$i]['name']."(".$user['queues'][$i]['rank'].")";
                        } else {
                            $line .= "\t";
                        }
                        $lines[] = $line;
                    }
                }
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                        get_string('userlist', 'grouptool');
        }
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1!
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in past!
        header('Content-Disposition: attachment; filename="'.$filename.'.txt";'.
               ' filename*="'.rawurlencode($filename).'.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * fills workbook (either XLSX or ODS) with data
     *
     * @global object $SESSION
     * @param MoodleExcelWorkbook $workbook workbook to put data into
     * @param array $data userdata with headline at index 0
     * @param array $orderby current sort-array
     * @param array $collapsed current collapsed columns
     */
    private function userlist_fill_workbook(&$workbook, $data=array(), $orderby=array(),
                                            $collapsed=array()) {
        global $SESSION, $CFG;
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        if (count($data) > 0) {
            if (count($data) > 1) {
                // General information? unused at the moment!
                $worksheet = $workbook->add_worksheet(get_string('all'));
                if (is_a($worksheet, 'Moodle_Excel_Worksheet')) {
                    if ($orientation) {
                        $worksheet->pear_excel_worksheet->setLandscape();
                    } else {
                        $worksheet->pear_excel_worksheet->setPortrait();
                    }
                }

            }

            // Prepare formats!
            $headlineprop = array(    'size' => 12,
                    'bold' => 1,
                    'HAlign' => 'center',
                    'bottom' => 2,
                    'VAlign' => 'vcenter');
            $headlineformat = $workbook->add_format($headlineprop);
            $headlineformat->set_right(1);
            $headlineformat->set_align('center');
            $headlineformat->set_align('vcenter');
            $headlinelast = $workbook->add_format($headlineprop);
            $headlinelast->set_align('center');
            $headlinelast->set_align('vcenter');
            $headlinelast->set_left(1);
            $headlinenb = $workbook->add_format($headlineprop);
            $headlinenb->set_align('center');
            $headlinenb->set_align('vcenter');
            unset($headlineprop['bottom']);
            $headlinenbb = $workbook->add_format($headlineprop);
            $headlinenbb->set_align('center');
            $headlinenbb->set_align('vcenter');

            $regentryprop = array(   'size' => 10,
                    'align' => 'left');
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_align('vcenter');
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_align('vcenter');
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('center');
            $noregentriesformat->set_align('vcenter');
            $noregentriesformat->set_right(1);
            $queueentryformat = $workbook->add_format($queueentryprop);
            $queueentryformat->set_right(1);
            $queueentryformat->set_align('vcenter');
            $queueentrylast = $workbook->add_format($queueentryprop);
            $queueentrylast->set_align('vcenter');
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('center');
            $noqueueentriesformat->set_align('vcenter');

            // Start row for groups general sheet!
            $j = 0;

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = get_all_user_name_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = array(0               => 26,
                                 'fullname'      => 26,
                                 'firstname'     => 20,
                                 'surname'       => 20,
                                 'email'         => 35,
                                 'registrations' => 47,
                                 'queues_rank'   => 7.5,
                                 'queues_grp'    => 47); // Unit: mm!

            foreach ($data as $key => $user) {
                if ($key == 0) {
                    // Headline!
                    $k = 0;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $worksheet->write_string($j, $k, get_user_field_name($namefield), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $worksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k = n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $worksheet->write_string($j, $k, get_user_field_name($field), $headlineformat);
                            $worksheet->write_blank($j + 1, $k, $headlineformat);
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $worksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $worksheet->merge_cells($j, $k, $j + 1, $k);
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, get_user_field_name('idnumber'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $worksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, get_user_field_name('email'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $worksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+2!
                    }
                    $worksheet->write_string($j, $k, $user['registrations'], $headlineformat);
                    $worksheet->write_blank($j + 1, $k, $headlineformat);
                    $hidden = in_array('registrations', $collapsed) ? true : false;
                    $columnwidth['registrations'] = empty($columnwidth['registrations']) ? $columnwidth[0] :
                                                    $columnwidth['registrations'];
                    $worksheet->set_column($k, $k, $columnwidth['registrations'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j + 1, $k);
                    $k++; // ...k = n+3!
                    $worksheet->write_string($j, $k, $user['queues'], $headlinenbb);
                    $worksheet->write_blank($j, $k + 1, $headlinenbb);
                    $hidden = in_array('queues', $collapsed) ? true : false;
                    $columnwidth['queues_rank'] = empty($columnwidth['queues_rank']) ? $columnwidth[0] :
                                                  $columnwidth['queues_rank'];
                    $worksheet->set_column($k, $k, $columnwidth['queues_rank'], null, $hidden);
                    $columnwidth['queues_grp'] = empty($columnwidth['queues_grp']) ? $columnwidth[0] : $columnwidth['queues_grp'];
                    $worksheet->set_column($k + 1, $k + 1, $columnwidth['queues_grp'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j, $k + 1);
                    $worksheet->write_string($j + 1, $k, get_string('rank', 'grouptool'),
                                             $headlinelast);
                    $worksheet->write_string($j + 1, $k + 1, get_string('group', 'group'), $headlinenb);
                    $k += 2; // ...k = n+5!
                    $rows = 2;
                } else {
                    $k = 0;
                    $rows = max(array(1, count($user['registrations']), count($user['queues'])));

                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        if (empty($user[$namefield])) {
                            $user[$namefield] = '';
                        }
                        $worksheet->write_string($j, $k, $user[$namefield], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++;
                    }
                    // ...k = n!

                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            if (empty($user[$field])) {
                                $worksheet->write_blank($j, $k, $regentryformat);
                            } else {
                                $worksheet->write_string($j, $k, $user[$field], $regentryformat);
                            }
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, $user['idnumber'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, $user['email'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+2!
                    }

                    for ($i = 0; $i < $rows; $i++) {
                        if ($i != 0) {
                            for ($m = 0; $m < $k; $m++) {
                                // Write all the empty cells!
                                $worksheet->write_blank($j + $i, $m, $regentryformat);
                            }
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k, get_string('no_registrations',
                                                                       'grouptool'),
                                                     $noregentriesformat);
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                        } else if (key_exists($i, $user['registrations'])) {
                            $worksheet->write_string($j + $i, $k, $user['registrations'][$i],
                                                     $regentryformat);
                        } else {
                            $worksheet->write_blank($j + $i, $k, $regentryformat);
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k + 1, get_string('nowhere_queued',
                                                                       'grouptool'),
                                                     $noqueueentriesformat);
                            $worksheet->merge_cells($j, $k + 1, $j + $rows - 1, $k + 2);
                        } else if (key_exists($i, $user['queues'])) {
                            $worksheet->write_number($j + $i, $k + 1, $user['queues'][$i]['rank'],
                                                     $queueentrylast);
                            $worksheet->write_string($j + $i, $k + 2, $user['queues'][$i]['name'],
                                                     $queueentrylast);
                        } else {
                            $worksheet->write_blank($j + $i, $k + 1, $queueentrylast);
                            $worksheet->write_blank($j + $i, $k + 2, $queueentrylast);
                        }
                    }
                    $k += 3;
                }
                $j += $rows;    // We use 1 row space between groups!
            }
        }
    }

    /**
     * outputs generated ods-file for userlist (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_ods($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleODSWorkbook("-");

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $this->userlist_fill_workbook($workbook, $data, $orderby, $collapsed);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('userlist', 'grouptool');
        }

        $workbook->send($filename.'.ods');
        $workbook->close();
    }

    /**
     * outputs generated xlsx-file for userlist (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_xlsx($groupid = 0, $groupingid = 0, $orderby=array(),
                                          $collapsed=array()) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = $this->course->fullname;
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $this->userlist_fill_workbook($workbook, $data);

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_group_name($groupid).'_'.
                                       get_string('userlist', 'grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_grouping_name($groupingid).'_'.
                                       get_string('userlist', 'grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       get_string('userlist', 'grouptool'));
        }

        $workbook->send($filename.'.xlsx');
        $workbook->close();
    }

    /**
     * view userlist tab
     *
     * @global object $PAGE
     * @global object $OUTPUT
     */
    public function view_userlist() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);

        $url = new moodle_url($PAGE->url, array('sesskey'     => sesskey(),
                                                'groupid'     => $groupid,
                                                'groupingid'  => $groupingid,
                                                'orientation' => $orientation));

        $groupings = groups_get_all_groupings($this->course->id);
        $options = array(0 => get_string('all'));
        if (count($groupings)) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = $grouping->name;
            }
        }
        $groupingselect = new single_select($url, 'groupingid', $options, $groupingid, false);

        $groups = $this->get_active_groups(false, false, 0, 0, $groupingid);
        $options = array(0 => get_string('all'));
        if (count($groups)) {
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
        }
        if (!key_exists($groupid, $options)) {
            $groupid = 0;
            $url->param('groupid', 0);
            echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping',
                                                               'grouptool').
                                                    html_writer::empty_tag('br').
                                                    get_string('switched_to_all_groups',
                                                               'grouptool'),
                                                    'notifyproblem'), 'generalbox centered');
        }
        $groupselect = new single_select($url, 'groupid', $options, $groupid, false);

        $options = array(0 => get_string('portrait', 'grouptool'),
                         1 => get_string('landscape', 'grouptool'));
        $orientationselect = new single_select($url, 'orientation', $options, $orientation, false);

        echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupingselect),
                              array('class' => 'centered grouptool_userlist_filter')).
             html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupselect),
                              array('class' => 'centered grouptool_userlist_filter')).
             html_writer::tag('div', get_string('orientation', 'grouptool').'&nbsp;'.
                                     $OUTPUT->render($orientationselect),
                              array('class' => 'centered grouptool_userlist_filter'));
        flush();
        $this->userlist_table($groupingid, $groupid);

    }

}
