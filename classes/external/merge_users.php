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
 * External API for merging users.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mergeusers\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use core\exception\moodle_exception;
use tool_mergeusers\local\logger;
use tool_mergeusers\local\user_merger;

/**
 * External API class for merging users.
 */
class merge_users extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'removeuserfield' => new external_value(PARAM_TEXT, 'Field to identify user to remove'),
            'removeuservalue' => new external_value(PARAM_RAW, 'Value for user to remove'),
            'keepuserfield' => new external_value(PARAM_TEXT, 'Field to identify user to keep'),
            'keepuservalue' => new external_value(PARAM_RAW, 'Value for user to keep'),
        ]);
    }

    /**
     * Merge two users immediately.
     *
     * @param string $removeuserfield Field to identify user to remove
     * @param string $removeuservalue Value for user to remove
     * @param string $keepuserfield Field to identify user to keep
     * @param string $keepuservalue Value for user to keep
     * @return array Result of merge operation
     * @throws moodle_exception
     */
    public static function execute(
        string $removeuserfield,
        string $removeuservalue,
        string $keepuserfield,
        string $keepuservalue
    ): array {
        self::validate_parameters(self::execute_parameters(), [
            'removeuserfield' => $removeuserfield,
            'removeuservalue' => $removeuservalue,
            'keepuserfield' => $keepuserfield,
            'keepuservalue' => $keepuservalue,
        ]);

        /** @var \context $systemcontext */
        $systemcontext = context_system::instance();
        require_capability('tool/mergeusers:mergeusers', $systemcontext);

        $removeuserid = self::get_user($removeuserfield, $removeuservalue);
        $keepuserid = self::get_user($keepuserfield, $keepuservalue);

        if ($removeuserid == $keepuserid) {
            throw new moodle_exception('errorsameuser', 'tool_mergeusers');
        }

        $merger = new user_merger();
        try {
            [$success, $logs, $logid] = $merger->merge($keepuserid, $removeuserid);

            return [
                'success' => $success,
                'mergerequestid' => $logid,
                'message' => $success
                    ? get_string('apimergesuccess', 'tool_mergeusers')
                    : get_string('apimergefailed', 'tool_mergeusers'),
                'log' => json_encode($logs),
                'timecompleted' => time(),
            ];
        } catch (\Exception $e) {
            $logger = new logger();
            $logid = $logger->log($keepuserid, $removeuserid, false, [$e->getMessage()]);

            return [
                'success' => false,
                'mergerequestid' => $logid,
                'errorcode' => 'mergefailed',
                'message' => $e->getMessage(),
                'details' => json_encode([
                    'exception' => get_class($e),
                ]),
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether merge was successful'),
            'mergerequestid' => new external_value(PARAM_INT, 'ID of merge record'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'log' => new external_value(PARAM_RAW, 'Merge log', VALUE_OPTIONAL),
            'timecompleted' => new external_value(PARAM_INT, 'Completion timestamp', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_TEXT, 'Error code if failed', VALUE_OPTIONAL),
            'details' => new external_value(PARAM_RAW, 'Error details', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Get user ID by field and value.
     *
     * @param string $userfield Field name
     * @param string $uservalue Field value
     * @return int User ID
     * @throws moodle_exception
     */
    public static function get_user(string $userfield, string $uservalue): int {
        global $DB;

        $users = $DB->get_records('user', [$userfield => $uservalue, 'deleted' => 0]);

        if (count($users) == 0) {
            throw new moodle_exception(
                'cannotfinduser',
                'tool_mergeusers',
                '',
                (object)['userfield' => $userfield, 'uservalue' => $uservalue]
            );
        }

        if (count($users) > 1) {
            throw new moodle_exception(
                'toomanyusers',
                'tool_mergeusers',
                '',
                (object)['userfield' => $userfield, 'uservalue' => $uservalue]
            );
        }

        $user = reset($users);
        return $user->id;
    }
}
