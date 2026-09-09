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
 * Content sync helpers.
 *
 * Nothing here reads a single row of student data. It reads course material
 * only, and only from the courses the administrator ticked on the plugin's
 * settings page. An empty tick list means nothing is readable at all.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyntai;

defined('MOODLE_INTERNAL') || die();

/**
 * Decides what may be read, and turns it into plain text.
 */
class sync {

    /** @var string[] Activity types the sync can read text from. */
    const MODULE_TYPES = array('page', 'book', 'label', 'resource', 'url', 'folder', 'assign', 'forum', 'glossary', 'quiz');

    /** @var string[] Types ticked when the administrator has never chosen. */
    const DEFAULT_TYPES = array('page', 'book', 'label', 'resource', 'url');

    /** @var int Hard ceiling on the characters returned for one activity. */
    const MAX_MODULE_CHARS = 60000;

    /** @var int Hard ceiling on the files listed for one course. */
    const MAX_COURSE_FILES = 200;

    /** @var string Shortname of the single-function service the token belongs to. */
    const SERVICE_SHORTNAME = 'local_asyntai_sync';

    /** @var string Where the token is parked until the account owner claims it. */
    const STAGE_URL = 'https://asyntai.com/api/v1/wp-search/stage/';

    /** @var string Where the account owner goes to claim it.
     * The trailing slash is deliberate. Without it the site answers with a
     * redirect first, which costs a round trip and is one more thing that can
     * go wrong behind a school proxy. */
    const CLAIM_URL = 'https://asyntai.com/moodle-auth/?platform=moodle';

    /**
     * Is content sync switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (int) get_config('local_asyntai', 'syncenabled') === 1;
    }

    /**
     * Course ids the administrator ticked.
     *
     * @return int[]
     */
    public static function selected_course_ids(): array {
        $raw = (string) get_config('local_asyntai', 'synccourses');
        if (trim($raw) === '') {
            return array();
        }
        $ids = array();
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 1) {
                // Course 1 is the site front page, never a real course.
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * Activity types the administrator ticked.
     *
     * @return string[]
     */
    public static function selected_types(): array {
        $raw = get_config('local_asyntai', 'synctypes');
        if ($raw === false || $raw === null) {
            return self::DEFAULT_TYPES;
        }
        $raw = (string) $raw;
        if (trim($raw) === '') {
            return array();
        }
        $out = array();
        foreach (explode(',', $raw) as $part) {
            $type = trim($part);
            if (in_array($type, self::MODULE_TYPES, true)) {
                $out[$type] = $type;
            }
        }
        return array_values($out);
    }

    /**
     * Store the administrator's choices.
     *
     * @param bool $enabled
     * @param int[] $courseids
     * @param string[] $types
     * @return void
     */
    public static function save_selection(bool $enabled, array $courseids, array $types): void {
        $clean = array();
        foreach ($courseids as $id) {
            $id = (int) $id;
            if ($id > 1) {
                $clean[$id] = $id;
            }
        }
        $cleantypes = array();
        foreach ($types as $type) {
            if (in_array($type, self::MODULE_TYPES, true)) {
                $cleantypes[$type] = $type;
            }
        }
        set_config('syncenabled', $enabled ? 1 : 0, 'local_asyntai');
        set_config('synccourses', implode(',', array_values($clean)), 'local_asyntai');
        set_config('synctypes', implode(',', array_values($cleantypes)), 'local_asyntai');
    }

    /**
     * Every course on the site, for the tick list on the settings page.
     *
     * @return \stdClass[] id, fullname, shortname, categoryname, visible
     */
    public static function all_courses(): array {
        global $DB;
        $sql = "SELECT c.id, c.fullname, c.shortname, c.visible, cc.name AS categoryname
                  FROM {course} c
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.id > 1
              ORDER BY cc.name ASC, c.fullname ASC";
        return array_values($DB->get_records_sql($sql));
    }

    /**
     * The ticked courses, one page at a time.
     *
     * @param int $page zero based
     * @param int $perpage
     * @return array [total, \stdClass[] courses]
     */
    public static function courses_page(int $page, int $perpage): array {
        global $DB;
        $ids = self::selected_course_ids();
        if (!$ids || !self::enabled()) {
            return array(0, array());
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
        $total = $DB->count_records_select('course', "id $insql", $params);
        $courses = $DB->get_records_select('course', "id $insql", $params, 'id ASC',
            '*', $page * $perpage, $perpage);
        return array((int) $total, array_values($courses));
    }

    /**
     * One course rendered for Asyntai.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function course_payload(\stdClass $course): array {
        $types = self::selected_types();
        $context = \context_course::instance($course->id, IGNORE_MISSING);
        $summary = '';
        if (!empty($course->summary)) {
            $summary = self::to_text($course->summary, (int) $course->summaryformat);
        }
        $out = array(
            'id' => (int) $course->id,
            'fullname' => (string) $course->fullname,
            'shortname' => (string) $course->shortname,
            'categoryname' => self::category_name((int) $course->category),
            'summary' => $summary,
            'url' => (new \moodle_url('/course/view.php', array('id' => $course->id)))->out(false),
            'startdate' => (int) $course->startdate,
            'enddate' => (int) $course->enddate,
            'visible' => (int) $course->visible,
            'modules' => array(),
        );
        if (!$types || !$context) {
            return $out;
        }

        $modinfo = get_fast_modinfo($course);
        $filecount = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            if (!in_array($cm->modname, $types, true)) {
                continue;
            }
            $text = self::module_text($cm);
            $files = array();
            if ($filecount < self::MAX_COURSE_FILES) {
                $files = self::module_files($cm, self::MAX_COURSE_FILES - $filecount);
                $filecount += count($files);
            }
            if ($text === '' && !$files) {
                continue;
            }
            $out['modules'][] = array(
                'id' => (int) $cm->id,
                'name' => (string) $cm->name,
                'modname' => (string) $cm->modname,
                'url' => $cm->url ? $cm->url->out(false) : '',
                'text' => $text,
                'files' => $files,
            );
        }
        return $out;
    }

    /**
     * Category name, or an empty string.
     *
     * @param int $categoryid
     * @return string
     */
    protected static function category_name(int $categoryid): string {
        global $DB;
        if ($categoryid <= 0) {
            return '';
        }
        $name = $DB->get_field('course_categories', 'name', array('id' => $categoryid));
        return $name === false ? '' : (string) $name;
    }

    /**
     * The readable text of one activity.
     *
     * Deliberately never touches anything a student wrote: forum posts, quiz
     * questions, submissions and glossary entries are all left alone. Only the
     * teacher's own description and page content are read.
     *
     * @param \cm_info $cm
     * @return string
     */
    protected static function module_text(\cm_info $cm): string {
        global $DB;
        $parts = array();
        $record = $DB->get_record($cm->modname, array('id' => $cm->instance));
        if (!$record) {
            return '';
        }
        if (!empty($record->intro)) {
            $parts[] = self::to_text($record->intro,
                isset($record->introformat) ? (int) $record->introformat : FORMAT_HTML);
        }
        if ($cm->modname === 'page' && !empty($record->content)) {
            $parts[] = self::to_text($record->content, (int) $record->contentformat);
        }
        if ($cm->modname === 'url' && !empty($record->externalurl)) {
            $parts[] = (string) $record->externalurl;
        }
        if ($cm->modname === 'book') {
            $chapters = $DB->get_records('book_chapters',
                array('bookid' => $cm->instance, 'hidden' => 0), 'pagenum ASC');
            foreach ($chapters as $chapter) {
                $parts[] = (string) $chapter->title;
                $parts[] = self::to_text($chapter->content, (int) $chapter->contentformat);
            }
        }
        if ($cm->modname === 'assign' && !empty($record->activity)) {
            $parts[] = self::to_text($record->activity,
                isset($record->activityformat) ? (int) $record->activityformat : FORMAT_HTML);
        }
        $text = trim(implode("\n\n", array_filter($parts, 'strlen')));
        if (\core_text::strlen($text) > self::MAX_MODULE_CHARS) {
            $text = \core_text::substr($text, 0, self::MAX_MODULE_CHARS);
        }
        return $text;
    }

    /**
     * Downloadable files attached to an activity.
     *
     * @param \cm_info $cm
     * @param int $limit
     * @return array
     */
    protected static function module_files(\cm_info $cm, int $limit): array {
        if ($limit <= 0) {
            return array();
        }
        $areas = array(
            'resource' => array('mod_resource', 'content'),
            'folder' => array('mod_folder', 'content'),
        );
        if (!isset($areas[$cm->modname])) {
            return array();
        }
        list($component, $filearea) = $areas[$cm->modname];
        $fs = get_file_storage();
        $files = $fs->get_area_files($cm->context->id, $component, $filearea, false, 'sortorder', false);
        $out = array();
        foreach ($files as $file) {
            if (count($out) >= $limit) {
                break;
            }
            $url = \moodle_url::make_webservice_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
            $out[] = array(
                'filename' => (string) $file->get_filename(),
                'mimetype' => (string) $file->get_mimetype(),
                'filesize' => (int) $file->get_filesize(),
                'timemodified' => (int) $file->get_timemodified(),
                'fileurl' => $url->out(false),
            );
        }
        return $out;
    }

    /**
     * Switch on web services and the REST protocol.
     *
     * Both are site-wide settings, so this only ever runs from the plugin's
     * own settings page, after the administrator presses the button. Nothing
     * here runs on install or on upgrade.
     *
     * @return void
     */
    public static function enable_webservices(): void {
        set_config('enablewebservices', 1);
        $protocols = (string) get_config('core', 'webserviceprotocols');
        $list = array_filter(array_map('trim', explode(',', $protocols)), 'strlen');
        if (!in_array('rest', $list, true)) {
            $list[] = 'rest';
            set_config('webserviceprotocols', implode(',', $list));
        }
    }

    /**
     * The service record this plugin defines, or null when it is missing.
     *
     * @return \stdClass|null
     */
    public static function service() {
        global $DB;
        $service = $DB->get_record('external_services',
            array('shortname' => self::SERVICE_SHORTNAME, 'component' => 'local_asyntai'));
        return $service ?: null;
    }

    /**
     * A token for Asyntai, making one if there is none yet.
     *
     * The token belongs to a service holding one read-only function, so it
     * cannot reach anything else in Moodle.
     *
     * @return string empty when the service is missing
     */
    public static function issue_token(): string {
        global $DB, $USER;

        $service = self::service();
        if (!$service) {
            return '';
        }
        if (empty($service->enabled)) {
            $DB->set_field('external_services', 'enabled', 1, array('id' => $service->id));
        }

        $existing = $DB->get_record('external_tokens', array(
            'externalserviceid' => $service->id,
            'userid' => $USER->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
        ));
        if ($existing) {
            return (string) $existing->token;
        }

        $context = \context_system::instance();
        if (class_exists('\core_external\util')) {
            return (string) \core_external\util::generate_token(
                EXTERNAL_TOKEN_PERMANENT, $service, $USER->id, $context);
        }
        return (string) external_generate_token(
            EXTERNAL_TOKEN_PERMANENT, $service, $USER->id, $context);
    }

    /**
     * Park the token at Asyntai under a one-time state token.
     *
     * Nothing is linked to an account by this call. The administrator then
     * opens the claim page and signs in to Asyntai, and only then is the
     * token attached to their site. That extra step is the point: the site id
     * this plugin holds is printed in the page source of every page, so a
     * call that trusted it alone would let a stranger attach their own Moodle
     * to somebody else's account.
     *
     * @param string $state
     * @param string $token
     * @return bool true when Asyntai accepted it
     */
    public static function stage_token(string $state, string $token): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $payload = json_encode(array(
            'state' => $state,
            'platform' => 'moodle',
            // Never 'search': the paid search bar is not part of this.
            'product' => 'chat',
            'site_url' => $CFG->wwwroot,
            'moodle_token' => $token,
        ));

        try {
            $curl = new \curl();
            $curl->setHeader(array('Content-Type: application/json'));
            $response = $curl->post(self::STAGE_URL, $payload);
            $info = $curl->get_info();
        } catch (\Exception $e) {
            debugging('Asyntai: staging failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        $code = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        if ($code !== 200) {
            debugging('Asyntai: staging returned HTTP ' . $code, DEBUG_DEVELOPER);
            return false;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) && !empty($decoded['ok']);
    }

    /**
     * Delete every token this plugin issued.
     *
     * Called when the administrator disconnects, so no live key is left behind.
     *
     * @return void
     */
    public static function revoke_tokens(): void {
        global $DB;
        $service = self::service();
        if (!$service) {
            return;
        }
        $DB->delete_records('external_tokens', array('externalserviceid' => $service->id));
    }

    /**
     * HTML or Markdown to plain text.
     *
     * @param string $content
     * @param int $format
     * @return string
     */
    protected static function to_text($content, int $format = FORMAT_HTML): string {
        $content = (string) $content;
        if (trim($content) === '') {
            return '';
        }
        // Files are listed separately, so the placeholder is only noise here.
        $content = str_replace('@@PLUGINFILE@@', '', $content);
        return trim(content_to_text($content, $format));
    }
}
