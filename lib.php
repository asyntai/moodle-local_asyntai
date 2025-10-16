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
 * Plugin library functions.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the Asyntai chat widget on all pages when configured.
 * This runs on most page loads as navigation is built.
 *
 * @param global_navigation $nav
 * @return void
 */
function local_asyntai_extend_navigation(global_navigation $nav) {
    global $PAGE;

    $siteid = (string) get_config('local_asyntai', 'site_id');
    if ($siteid === '') {
        return;
    }
    $scripturl = (string) get_config('local_asyntai', 'script_url');
    if ($scripturl === '') {
        $scripturl = 'https://asyntai.com/static/js/chat-widget.js';
    }

    // Inline loader mirrors other CMS plugins: async+defer and data-asyntai-id attribute.
    $code = '(function(){var s=document.createElement("script");s.async=true;s.defer=true;s.src=' . json_encode($scripturl) . ';s.setAttribute("data-asyntai-id",' . json_encode($siteid) . ');s.charset="UTF-8";var f=document.getElementsByTagName("script")[0];if(f&&f.parentNode){f.parentNode.insertBefore(s,f);}else if(document.head){document.head.appendChild(s);}})();';
    $PAGE->requires->js_init_code($code);
}

