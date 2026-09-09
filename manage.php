<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Asyntai management page.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/asyntai:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/asyntai/manage.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_asyntai'));
$PAGE->set_heading(get_string('pluginname', 'local_asyntai'));

$reset = optional_param('reset', 0, PARAM_INT);
if ($reset && confirm_sesskey()) {
    // Take back the read key before forgetting the connection, so no live
    // token is left behind on a site that is no longer connected.
    \local_asyntai\sync::revoke_tokens();
    set_config('site_id', '', 'local_asyntai');
    set_config('script_url', '', 'local_asyntai');
    set_config('account_email', '', 'local_asyntai');
    set_config('syncenabled', 0, 'local_asyntai');
    redirect(new moodle_url('/local/asyntai/manage.php'));
}

$savesync = optional_param('savesync', 0, PARAM_INT);
if ($savesync && confirm_sesskey()) {
    $enabled = (bool) optional_param('syncenabled', 0, PARAM_INT);
    $courseids = optional_param_array('courses', array(), PARAM_INT);
    $types = optional_param_array('types', array(), PARAM_PLUGIN);
    \local_asyntai\sync::save_selection($enabled, $courseids, $types);

    if (!$enabled) {
        redirect(new moodle_url('/local/asyntai/manage.php'),
            get_string('syncsaved', 'local_asyntai'));
    }

    // Web services stay off until the administrator actually asks for content
    // sync. Nothing is switched on behind their back, and nothing here runs
    // on install or upgrade.
    \local_asyntai\sync::enable_webservices();

    $token = \local_asyntai\sync::issue_token();
    if ($token === '') {
        redirect(new moodle_url('/local/asyntai/manage.php'),
            get_string('synctokenfailed', 'local_asyntai'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Park the token at Asyntai under a one-time state. It is attached to
    // nobody until the administrator claims it while signed in, which is what
    // stops a stranger pointing their own Moodle at this account.
    $state = 'mdl_' . random_string(24);
    if (!\local_asyntai\sync::stage_token($state, $token)) {
        // The token is real and this site keeps it, but Asyntai never got it.
        // Take it back rather than leave a live key nobody is using.
        \local_asyntai\sync::revoke_tokens();
        redirect(new moodle_url('/local/asyntai/manage.php'),
            get_string('syncstagefailed', 'local_asyntai'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    set_config('syncstate', $state, 'local_asyntai');
    redirect(new moodle_url('/local/asyntai/manage.php', array('claim' => 1)),
        get_string('syncsaved', 'local_asyntai'));
}

$siteid = (string) get_config('local_asyntai', 'site_id');
$accountemail = (string) get_config('local_asyntai', 'account_email');
$isconnected = $siteid !== '';

$selectedcourses = \local_asyntai\sync::selected_course_ids();
$selectedtypes = \local_asyntai\sync::selected_types();

$courserows = array();
if ($isconnected) {
    foreach (\local_asyntai\sync::all_courses() as $course) {
        $courserows[] = array(
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'categoryname' => $course->categoryname,
            'hidden' => empty($course->visible),
            'checked' => in_array((int) $course->id, $selectedcourses, true),
        );
    }
}

// Only offer activity types this site actually has. An administrator can
// uninstall a module, and asking for its name would then be a fatal error on
// the settings page rather than a missing tick box.
$installedmods = array_keys(core_component::get_plugin_list('mod'));
$typerows = array();
foreach (\local_asyntai\sync::MODULE_TYPES as $type) {
    if (!in_array($type, $installedmods, true)) {
        continue;
    }
    $typerows[] = array(
        'type' => $type,
        'label' => get_string('pluginname', 'mod_' . $type),
        'checked' => in_array($type, $selectedtypes, true),
    );
}

echo $OUTPUT->header();

$data = array(
    'isconnected' => $isconnected,
    'accountemail' => $accountemail,
    'panelurl' => 'https://asyntai.com/dashboard',
    'setupurl' => 'https://asyntai.com/dashboard#setup',
    'reseturl' => (new moodle_url('/local/asyntai/manage.php', array('reset' => 1, 'sesskey' => sesskey())))->out(false),
    'formurl' => (new moodle_url('/local/asyntai/manage.php'))->out(false),
    'sesskey' => sesskey(),
    'syncenabled' => \local_asyntai\sync::enabled(),
    'courses' => $courserows,
    'hascourses' => !empty($courserows),
    'types' => $typerows,
    'showclaim' => (bool) optional_param('claim', 0, PARAM_INT),
    'claimurl' => \local_asyntai\sync::CLAIM_URL . '&state='
        . urlencode((string) get_config('local_asyntai', 'syncstate')),
);

echo $OUTPUT->render_from_template('local_asyntai/manage', $data);

$params = array(
    'expectedOrigin' => 'https://asyntai.com',
    'connectUrl' => 'https://asyntai.com/moodle-auth?platform=moodle',
    'resetUrl' => (new moodle_url('/local/asyntai/manage.php', array('reset' => 1, 'sesskey' => sesskey())))->out(false),
);
$PAGE->requires->js_call_amd('local_asyntai/manage', 'init', array($params));

echo $OUTPUT->footer();
