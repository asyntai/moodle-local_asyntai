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
 * External functions and services are defined here.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_asyntai_save_connection' => array(
        'classname'   => 'local_asyntai\\external',
        'methodname'  => 'save_connection',
        'classpath'   => '',
        'description' => 'Save Asyntai connection settings',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/asyntai:manage',
    ),
    'local_asyntai_get_course_content' => array(
        'classname'   => 'local_asyntai\external',
        'methodname'  => 'get_course_content',
        'classpath'   => '',
        'description' => 'Return the text of the courses the administrator ticked for Asyntai',
        'type'        => 'read',
        'ajax'        => false,
        'capabilities'=> 'local/asyntai:sync',
    ),
);

// A service of its own, holding exactly one function. The token Asyntai is
// given belongs to this service, so it cannot call anything else in Moodle.
$services = array(
    'Asyntai content sync' => array(
        'functions' => array('local_asyntai_get_course_content'),
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_asyntai_sync',
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ),
);
