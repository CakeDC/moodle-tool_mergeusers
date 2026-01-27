<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web service definitions for tool_mergeusers.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_mergeusers_merge_users' => [
        'classname' => 'tool_mergeusers\external\merge_users',
        'methodname' => 'execute',
        'description' => 'Merge two users immediately via API',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'tool/mergeusers:mergeusers',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_mergeusers_merge_users_bulk' => [
        'classname' => 'tool_mergeusers\external\merge_users_bulk',
        'methodname' => 'execute',
        'description' => 'Merge multiple pairs of users immediately via API',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'tool/mergeusers:mergeusers',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_mergeusers_merge_multiple_to_one' => [
        'classname' => 'tool_mergeusers\external\merge_multiple_to_one',
        'methodname' => 'execute',
        'description' => 'Merge multiple users into one primary user',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'tool/mergeusers:mergeusers',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
