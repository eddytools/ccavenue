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
 
 
/**
 * CCAvenue India (payment) enrolment plugin .
 *
 * @package    enrol_ccavenue
 * @copyright  2010 EddyTools(TM) Anwesha Software Pvt. Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

require_once($CFG->dirroot.'/enrol/ccavenue/api/libfuncs.php3');


/// Keep out casual intruders
/**********Comment for testing VVVVVVVVV****************
if (empty($_POST) or !empty($_GET)) {
    print_error("Sorry, you can not use the script that way.");
}

/// Read all the data from ccavenue and get it ready for later;
/// we expect only valid UTF-8 encoding, it is the responsibility
/// of user to set it up properly in ccavenue business account,
/// it is documented in docs wiki.



$data = new stdClass();

foreach ($_POST as $key => $value) {
    $req .= "&$key=".urlencode($value);
    $data->$key = $value;
}


$custom = explode('-', $data->Merchant_Param);
$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
$data->Merchant_Id      = $data->Merchant_Id;
$data->Order_Id         = $data->Order_Id;
$data->Amount           = $data->Amount;
$data->AuthDesc         = $data->AuthDesc;
$data->Merchant_Param   = $data->Merchant_Param;
$data->Checksum         = $data->Checksum;
$data->timeupdated      = time();

**************COMMENT for testing********************/
/***************** Test Data ******vvvv**************/
$data->userid           = $_GET['uid'];
$data->courseid         = 6;
$data->instanceid       = 20;
$data->Merchant_Id      = "M_purvesh_5546";
$data->Order_Id         = "3-6-20-1376655852-5";
$data->Amount           = "10";
$data->AuthDesc         = "Y";
$data->Merchant_Param   = "3-6-20";
$data->Checksum         = "true";
$data->timeupdated      = time();
/***************** Test Data *******^^^^^*************/

/// get the user and course records

if (! $user = $DB->get_record("user", array("id"=>$data->userid))) {
    message_ccavenue_error_to_admin("Not a valid user id", $data);
    die;
}

if (! $course = $DB->get_record("course", array("id"=>$data->courseid))) {
    message_ccavenue_error_to_admin("Not a valid course id", $data);
    die;
}

if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
    message_ccavenue_error_to_admin("Not a valid context id", $data);
    die;
}

if (! $plugin_instance = $DB->get_record("enrol", array("id"=>$data->instanceid, "status"=>0))) {
    message_ccavenue_error_to_admin("Not a valid instance id", $data);
    die;
}


// Check that amount paid is the correct amount

if ( (float) $plugin_instance->cost <= 0 ) {
    $cost = (float) $plugin->get_config('cost');
} else {
    $cost = (float) $plugin_instance->cost;
}



// Use the same rounding of floats as on the enrol form.
$cost = format_float($cost, 2, false);

if ($data->Amount < $cost) {
    message_ccavenue_error_to_admin("Amount paid is not enough ($data->payment_gross < $cost))", $data);
    die;

}

// PARAM ALL CLEAR !

$Checksum = verifyChecksum($data->Merchant_Id, $Order_Id , $data->Amount , $AuthDesc , $Checksum , $plugin_instance->workingKey);


if($data->Checksum=="true" && $data->AuthDesc=="Y")
{
    $plugin = enrol_get_plugin('ccavenue');
    $DB->insert_record("enrol_ccavenue", $data);

    if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }

    
    $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);
    print_r($plugin);
    // Pass $view=true to filter hidden caps if the user cannot see them
    
    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC','', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = false;
    }

    
    
    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));


    if (!empty($mailstudents)) {
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_ccavenue';
        $eventdata->name              = 'ccavenue_enrolment';
        $eventdata->userfrom          = $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);

    }

    if (!empty($mailteachers)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_ccavenue';
        $eventdata->name              = 'ccavenue_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_ccavenue';
            $eventdata->name              = 'ccavenue_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
         
    }
    notice("<br><br><br>Thank for your payment , Course is added to your account" , $CFG->wwwroot);

} else if($data->Checksum=="true" && $data->AuthDesc=="B") { // ERROR
    $DB->insert_record("enrol_ccavenue", $data, false);
    message_ccavenue_error_to_admin("Received an payment status is pending!!", $data);
    notice("<br><br><br>Thank you for shopping with us.We will keep you posted regarding the status of your order through e-mail<br><br><br>",$CFG->webroot);

    //echo "<br>Thank you for shopping with us.We will keep you posted regarding the status of your order through e-mail";
    //Here you need to put in the routines/e-mail for a  "Batch Processing" order
    //This is only if payment for this transaction has been made by an American Express Card
    //since American Express authorisation status is available only after 5-6 hours by mail from ccavenue and at the "View Pending Orders"
} else if($data->Checksum=="true" && $data->AuthDesc=="N"){
    notice("<br><br><br>CCAvenue : Sorry !!! The transaction has been <strong>declined</strong>.<br><br><br>",$CFG->webroot);		
    //Here you need to put in the routines for a failed
    //transaction such as sending an email to customer
    //setting database status etc etc

}else{
    notice("<br><br><br>CCAvenue : INVALID TRANSATION<br><br><br>",$CFG->webroot);

}


exit;


//--- HELPER FUNCTIONS --------------------------------------------------------------------------------------


function message_ccavenue_error_to_admin($subject, $data) {
    echo $subject;
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_ccavenue';
    $eventdata->name              = 'ccavenue_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "ccavenue ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    
    message_send($eventdata);
}
?>
