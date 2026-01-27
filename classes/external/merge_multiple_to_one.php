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
 * External API for merging multiple users into one.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mergeusers\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use core\exception\moodle_exception;
use tool_mergeusers\local\user_merger;

defined('MOODLE_INTERNAL') || die();

/**
 * External API class for merging multiple users into one.
 */
class merge_multiple_to_one extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'keepuserfield' => new external_value(PARAM_TEXT, 'Field to identify primary user to keep'),
            'keepuservalue' => new external_value(PARAM_RAW, 'Value for primary user to keep'),
            'removeusers' => new external_multiple_structure(
                new external_single_structure([
                    'userfield' => new external_value(PARAM_TEXT, 'Field to identify user to remove'),
                    'uservalue' => new external_value(PARAM_RAW, 'Value for user to remove'),
                ])
            ),
        ]);
    }

    /**
     * Merge multiple users into one primary user.
     *
     * @param string $keepuserfield Field to identify primary user
     * @param string $keepuservalue Value for primary user
     * @param array $removeusers Array of users to merge into primary
     * @return array Results of merge operations
     * @throws moodle_exception
     */
    public static function execute(
        string $keepuserfield,
        string $keepuservalue,
        array $removeusers
    ): array {
        self::validate_parameters(self::execute_parameters(), [
            'keepuserfield' => $keepuserfield,
            'keepuservalue' => $keepuservalue,
            'removeusers' => $removeusers,
        ]);

        /** @var \context $systemcontext */
        $systemcontext = context_system::instance();
        require_capability('tool/mergeusers:mergeusers', $systemcontext);

        $keepuserid = merge_users::get_user($keepuserfield, $keepuservalue);

        $results = [];
        $successful = 0;
        $failed = 0;
        $merger = new user_merger();

        foreach ($removeusers as $index => $removeuser) {
            try {
                $removeuserid = merge_users::get_user(
                    $removeuser['userfield'],
                    $removeuser['uservalue']
                );

                if ($removeuserid == $keepuserid) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'mergerequestid' => 0,
                        'message' => get_string('errorsameuser', 'tool_mergeusers'),
                        'errorcode' => 'errorsameuser',
                    ];
                    $failed++;
                    continue;
                }

                [$success, $logs, $logid] = $merger->merge($keepuserid, $removeuserid);

                $results[] = [
                    'index' => $index,
                    'success' => $success,
                    'mergerequestid' => $logid,
                    'message' => $success
                        ? get_string('apimergesuccess', 'tool_mergeusers')
                        : get_string('apimergefailed', 'tool_mergeusers'),
                    'log' => json_encode($logs),
                ];

                if ($success) {
                    $successful++;
                } else {
                    $failed++;
                    $results[count($results) - 1]['errorcode'] = 'mergefailed';
                }
            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'mergerequestid' => 0,
                    'message' => $e->getMessage(),
                    'errorcode' => 'exception',
                    'details' => json_encode(['exception' => get_class($e)]),
                ];
            }
        }

        return [
            'total' => count($removeusers),
            'successful' => $successful,
            'failed' => $failed,
            'primaryuserid' => $keepuserid,
            'results' => $results,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total number of users to merge'),
            'successful' => new external_value(PARAM_INT, 'Number of successful merges'),
            'failed' => new external_value(PARAM_INT, 'Number of failed merges'),
            'primaryuserid' => new external_value(PARAM_INT, 'ID of primary user'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, 'Index in the input array'),
                    'success' => new external_value(PARAM_BOOL, 'Whether merge was successful'),
                    'mergerequestid' => new external_value(PARAM_INT, 'ID of merge record'),
                    'message' => new external_value(PARAM_TEXT, 'Result message'),
                    'log' => new external_value(PARAM_RAW, 'Merge log', VALUE_OPTIONAL),
                    'errorcode' => new external_value(PARAM_TEXT, 'Error code if failed', VALUE_OPTIONAL),
                    'details' => new external_value(PARAM_RAW, 'Error details', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }
}
