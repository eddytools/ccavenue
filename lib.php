<?php
/**
 * CCAvenue enrolment plugin.
 *
 * This plugin allows you to set up paid courses.
 *
 * @package    enrol_ccavenue
 * @copyright  2010 EddyTools(TM)
 * @web : www.eddytools.com
 */

defined('MOODLE_INTERNAL') || die();


class enrol_ccavenue_plugin extends enrol_plugin {

    public function get_currencies() {
        $codes = array(
            'INR', 'USD');
        $currencies = array();
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }

   public function get_info_icons(array $instances) {
        return array(new pix_icon('icon', get_string('pluginname', 'enrol_ccavenue'), 'enrol_ccavenue'));
    }

    public function roles_protected() {
        // users with role assign cap may tweak the roles later
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        // users with unenrol cap may unenrol other users manually - requires enrol/ccavenue:unenrol
        return true;
    }

    public function allow_manage(stdClass $instance) {
        // users with manage cap may tweak period and status - requires enrol/ccavenue:manage
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'ccavenue') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/ccavenue:config', $context)) {
            $managelink = new moodle_url('/enrol/ccavenue/edit.php', array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ccavenue') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/ccavenue:config', $context)) {
            $editlink = new moodle_url("/enrol/ccavenue/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/ccavenue:config', $context)) {
            return NULL;
        }

        // multiple instances supported - different cost for different roles
        return new moodle_url('/enrol/ccavenue/edit.php', array('courseid'=>$courseid));
    }

    function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = context_course::instance($course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_ccavenue').'</p>';
        } else {

            $localisedcost = format_float($cost, 2, true);
            $cost = format_float($cost, 2, false);

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
                $instancename    = $this->get_instance_name($instance);

                
                require_once($CFG->dirroot.'/enrol/ccavenue/api/libfuncs.php3');
                $ccavenueurl = 'https://www.ccavenue.com/shopzone/cc_details.jsp';
                $Merchant_Id = $instance->merchantId; //"M_purvesh_5546" ;//This id(also User Id)  available at "Generate Working Key" of "Settings & Options" 
                $Redirect_Url = "$CFG->wwwroot/enrol/ccavenue/ipn.php?uid=".$USER->id ;//your redirect URL where your customer will be redirected after authorisation from CCAvenue
                $WorkingKey = $instance->workingKey; //"inmrhas38opj5ye79xv2a9epkwlkcr2v"  ;//put in the 32 bit alphanumeric key in the quotes provided here.Please note that get this key ,login to your CCAvenue merchant account and visit the "Generate Working Key" section at the "Settings & Options" page. 

                $Amount = $instance->cost ;//your script should substitute the amount in the quotes provided here
                $course_id=$course->id;
                $category="ONLINE";

                $Order_Id = $USER->id."-".$course->id."-".$instance->id."-".time()."-".rand(0,100) ;//your script should substitute the order description in the quotes provided here
                $Checksum = getCheckSum($Merchant_Id,$Amount,$Order_Id ,$Redirect_Url,$WorkingKey);
                $Merchant_Param=$USER->id."-".$course->id."-".$instance->id;
                
                
                
                include($CFG->dirroot.'/enrol/ccavenue/enrol.html');
            }

        }

        return $OUTPUT->box(ob_get_clean());
    }

    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
         $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }
   
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/ccavenue:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        if ($this->allow_manage($instance) && has_capability("enrol/ccavenue:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/edit', ''), get_string('edit'), $url, array('class'=>'editenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

}
