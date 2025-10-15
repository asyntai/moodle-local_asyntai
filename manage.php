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
    set_config('site_id', '', 'local_asyntai');
    set_config('script_url', '', 'local_asyntai');
    set_config('account_email', '', 'local_asyntai');
    redirect(new moodle_url('/local/asyntai/manage.php'));
}

$siteid = (string) get_config('local_asyntai', 'site_id');
$accountemail = (string) get_config('local_asyntai', 'account_email');

echo $OUTPUT->header();

$data = array(
    'isconnected' => $siteid !== '',
    'accountemail' => $accountemail,
    'panelurl' => 'https://asyntai.com/dashboard',
    'setupurl' => 'https://asyntai.com/dashboard#setup',
    'reseturl' => (new moodle_url('/local/asyntai/manage.php', array('reset' => 1, 'sesskey' => sesskey())))->out(false),
);

echo $OUTPUT->render_from_template('local_asyntai/manage', $data);

$params = array(
    'expectedOrigin' => 'https://asyntai.com',
    'connectUrl' => 'https://asyntai.com/moodle-auth?platform=moodle',
    'resetUrl' => (new moodle_url('/local/asyntai/manage.php', array('reset' => 1, 'sesskey' => sesskey())))->out(false),
);
$PAGE->requires->js_call_amd('local_asyntai/manage', 'init', array($params));

echo $OUTPUT->footer();

