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

namespace local_asyntai;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use \context_system;
use \external_api;
use \external_function_parameters;
use \external_single_structure;
use \external_value;

class external extends external_api {
    public static function save_connection_parameters(): external_function_parameters {
        return new external_function_parameters([
            'siteid' => new external_value(PARAM_ALPHANUMEXT, 'Asyntai site ID'),
            'scripturl' => new external_value(PARAM_URL, 'Widget script URL', VALUE_DEFAULT, ''),
            'accountemail' => new external_value(PARAM_EMAIL, 'Account email', VALUE_DEFAULT, ''),
        ]);
    }

    public static function save_connection(string $siteid, string $scripturl = '', string $accountemail = ''): array {
        self::validate_parameters(self::save_connection_parameters(), [
            'siteid' => $siteid,
            'scripturl' => $scripturl,
            'accountemail' => $accountemail,
        ]);

        require_sesskey();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/asyntai:manage', $context);

        $siteid = clean_param($siteid, PARAM_ALPHANUMEXT);
        $scripturl = clean_param($scripturl, PARAM_URL);
        $accountemail = clean_param($accountemail, PARAM_EMAIL);

        if ($siteid === '') {
            throw new \invalid_parameter_exception('missing siteid');
        }

        set_config('site_id', $siteid, 'local_asyntai');
        if ($scripturl !== '') {
            set_config('script_url', $scripturl, 'local_asyntai');
        }
        if ($accountemail !== '') {
            set_config('account_email', $accountemail, 'local_asyntai');
        }

        return ['success' => true];
    }

    public static function save_connection_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation status')
        ]);
    }
}


