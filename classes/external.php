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
 * External API functions for Asyntai plugin.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyntai;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use \context_system;
use \external_api;
use \external_function_parameters;
use \external_multiple_structure;
use \external_single_structure;
use \external_value;

/**
 * External API class for Asyntai connection management.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

    public static function get_course_content_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Page number, zero based', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Courses per page, 1 to 20', VALUE_DEFAULT, 5),
        ]);
    }

    /**
     * The course material the administrator ticked for Asyntai.
     *
     * Returns nothing at all unless content sync is switched on and at least
     * one course is ticked. It never reads student data.
     *
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_course_content(int $page = 0, int $perpage = 5): array {
        $params = self::validate_parameters(self::get_course_content_parameters(), [
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/asyntai:sync', $context);

        $page = max(0, (int) $params['page']);
        $perpage = min(20, max(1, (int) $params['perpage']));

        if (!sync::enabled()) {
            return ['total' => 0, 'syncenabled' => false, 'courses' => []];
        }

        list($total, $courses) = sync::courses_page($page, $perpage);
        $payload = [];
        foreach ($courses as $course) {
            $payload[] = sync::course_payload($course);
        }

        return ['total' => $total, 'syncenabled' => true, 'courses' => $payload];
    }

    public static function get_course_content_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'How many courses are ticked in total'),
            'syncenabled' => new external_value(PARAM_BOOL, 'Whether content sync is switched on'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course id'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                    'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
                    'summary' => new external_value(PARAM_RAW, 'Course summary as plain text'),
                    'url' => new external_value(PARAM_URL, 'Course URL'),
                    'startdate' => new external_value(PARAM_INT, 'Start date, unix time'),
                    'enddate' => new external_value(PARAM_INT, 'End date, unix time'),
                    'visible' => new external_value(PARAM_INT, 'Whether the course is visible'),
                    'modules' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Course module id'),
                            'name' => new external_value(PARAM_TEXT, 'Activity name'),
                            'modname' => new external_value(PARAM_PLUGIN, 'Activity type'),
                            'url' => new external_value(PARAM_RAW, 'Activity URL'),
                            'text' => new external_value(PARAM_RAW, 'Activity text, plain'),
                            'files' => new external_multiple_structure(
                                new external_single_structure([
                                    'filename' => new external_value(PARAM_FILE, 'File name'),
                                    'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                                    'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
                                    'timemodified' => new external_value(PARAM_INT, 'Last change, unix time'),
                                    'fileurl' => new external_value(PARAM_URL, 'Download URL, needs the token'),
                                ])
                            ),
                        ])
                    ),
                ])
            ),
        ]);
    }
}


