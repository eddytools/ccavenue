<?php


defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_ccavenue_settings', '', get_string('pluginname_desc', 'enrol_ccavenue')));

    $settings->add(new admin_setting_configtext('enrol_ccavenue/ccavenuebusiness', get_string('businessemail', 'enrol_ccavenue'), get_string('businessemail_desc', 'enrol_ccavenue'), '', PARAM_EMAIL));

    $settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailstudents', get_string('mailstudents', 'enrol_ccavenue'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailteachers', get_string('mailteachers', 'enrol_ccavenue'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailadmins', get_string('mailadmins', 'enrol_ccavenue'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_ccavenue/expiredaction', get_string('expiredaction', 'enrol_ccavenue'), get_string('expiredaction_help', 'enrol_ccavenue'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_ccavenue_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_ccavenue/status',
        get_string('status', 'enrol_ccavenue'), get_string('status_desc', 'enrol_ccavenue'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_ccavenue/cost', get_string('cost', 'enrol_ccavenue'), '', 0, PARAM_FLOAT, 4));

    $ccavenuecurrencies = enrol_get_plugin('ccavenue')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_ccavenue/currency', get_string('currency', 'enrol_ccavenue'), '', 'USD', $ccavenuecurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_ccavenue/roleid',
            get_string('defaultrole', 'enrol_ccavenue'), get_string('defaultrole_desc', 'enrol_ccavenue'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_ccavenue/enrolperiod',
        get_string('enrolperiod', 'enrol_ccavenue'), get_string('enrolperiod_desc', 'enrol_ccavenue'), 0));
}
