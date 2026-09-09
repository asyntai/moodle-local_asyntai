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

define('ASYNTAI_WIDGET_URL', 'https://widget.asyntai.com/static/js/chat-widget.js');

/**
 * Where the widget script is loaded from.
 *
 * The widget moved to its own host. Sites that connected before that still
 * have the old address saved from the day they connected, and asking a
 * hundred administrators to reconnect is not reasonable. So the old address
 * is swapped for the new one here, when the page is built. Only that exact
 * address is swapped: anything else an administrator set deliberately, or
 * anything we may serve from elsewhere later, is left alone.
 *
 * @return string
 */
function local_asyntai_script_url() {
    $src = (string) get_config('local_asyntai', 'script_url');
    if ($src === '') {
        return ASYNTAI_WIDGET_URL;
    }
    $host = parse_url($src, PHP_URL_HOST);
    $path = parse_url($src, PHP_URL_PATH);
    if (in_array($host, array('asyntai.com', 'www.asyntai.com'), true)
            && $path === '/static/js/chat-widget.js') {
        return ASYNTAI_WIDGET_URL;
    }
    return $src;
}

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
    $scripturl = local_asyntai_script_url();

    // Inline loader mirrors other CMS plugins: injects the widget script only
    // after the page's load event, since an async script tag still holds the
    // load event open until its fetch times out when Asyntai is unreachable.
    $code = '(function(){var l=function(){var s=document.createElement("script");s.src=' . json_encode($scripturl) . ';s.async=true;s.setAttribute("data-asyntai-id",' . json_encode($siteid) . ');document.head.appendChild(s)};if(document.readyState==="complete"){l()}else{window.addEventListener("load",l)}})();';
    $PAGE->requires->js_init_code($code);
}

