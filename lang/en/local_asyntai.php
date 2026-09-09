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
 * Language strings for Asyntai - AI Chatbot Moodle plugin.
 *
 * @package     local_asyntai
 * @copyright   2025 Asyntai <hello@asyntai.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Asyntai AI Chatbot';
$string['privacy:metadata'] = 'The Asyntai AI Chatbot plugin stores only site configuration (site ID, script URL, account email) in Moodle config and does not store personal user data.';
$string['asyntai:manage'] = 'Manage Asyntai plugin';

// UI strings.
$string['status'] = 'Status';
$string['connected'] = 'Connected';
$string['notconnected'] = 'Not connected';
$string['reset'] = 'Reset';
$string['as'] = 'as';
$string['enabledtitle'] = 'Asyntai is now enabled';
$string['enableddesc'] = 'Set up your AI chatbot, review chat logs and more:';
$string['openpanel'] = 'Open Asyntai Panel';
$string['tip'] = 'Tip:';
$string['tipdesc'] = 'If you want to change how the AI answers, please ';
$string['gohere'] = 'go here';
$string['connectprompt'] = 'Create a free Asyntai account or sign in to enable the chatbot';
$string['getstarted'] = 'Get started';
$string['fallbacktext'] = "If it doesn't work,";
$string['openconnectwindow'] = 'open the connect window';

// JS feedback strings.
$string['popupblocked'] = 'Popup blocked. Please allow popups or use the link below.';
$string['saving'] = 'Asyntai connected. Saving…';
$string['enabled'] = 'Asyntai connected. Chatbot enabled on all pages.';
$string['savefailed'] = 'Save failed';
$string['couldnotsave'] = 'Could not save settings: {$a}';

// Content sync strings.
$string['asyntai:sync'] = 'Read the ticked course material for Asyntai';
$string['synctitle'] = 'Course content';
$string['syncdesc'] = 'Choose which courses Asyntai may read. The chatbot answers questions from the material in those courses. Nothing is read until you tick a course and save.';
$string['syncenable'] = 'Let Asyntai read the ticked courses';
$string['synccourses'] = 'Courses';
$string['synctypes'] = 'Activity types';
$string['syncsave'] = 'Save content settings';
$string['syncsaved'] = 'Content settings saved.';
$string['nocourses'] = 'This site has no courses yet.';
$string['coursehidden'] = '(hidden)';
$string['syncnote'] = 'Only the course material is read: descriptions, pages, book chapters and attached files. Student data is never read. That includes grades, submissions, forum posts and quiz answers.';

// Privacy strings.
$string['privacy:metadata:asyntai'] = 'The chatbot runs on the Asyntai service, so some data leaves this site.';
$string['privacy:metadata:asyntai:message'] = 'The message a visitor types into the chat window, so the AI can answer it.';
$string['privacy:metadata:asyntai:language'] = 'The language of the page, so the AI answers in the right one.';
$string['privacy:metadata:asyntai:pageurl'] = 'The address of the page the visitor is on, so the answer fits the page.';
$string['privacy:metadata:asyntai:coursecontent'] = 'The material in the courses the administrator ticked, so the AI can answer questions about them. Student data is never sent.';

// Handing the token over to Asyntai.
$string['synctokenfailed'] = 'Could not create the read key. Check that the Asyntai plugin finished installing, then try again.';
$string['syncstagefailed'] = 'Saved, but Asyntai could not be reached. The read key was removed again. Check the server can reach asyntai.com, then press Save once more.';
$string['claimtitle'] = 'One more step';
$string['claimdesc'] = 'Sign in to Asyntai to finish. Until you do, the ticked courses are not read.';
$string['claimbutton'] = 'Finish in Asyntai';

