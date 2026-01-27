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
 * External API for bulk merging users.
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
use tool_mergeusers\local\logger;
use tool_mergeusers\local\user_merger;

/**
 * External API class for bulk merging users.
 */
class merge_users_bulk extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'merges' => new external_multiple_structure(
                new external_single_structure([
                    'removeuserfield' => new external_value(PARAM_TEXT, 'Field to identify user to remove'),
                    'removeuservalue' => new external_value(PARAM_RAW, 'Value for user to remove'),
                    'keepuserfield' => new external_value(PARAM_TEXT, 'Field to identify user to keep'),
                    'keepuservalue' => new external_value(PARAM_RAW, 'Value for user to keep'),
                ])
            ),
        ]);
    }

    /**
     * Merge multiple pairs of users immediately.
     *
     * @param array $merges Array of merge requests
     * @return array Results of all merge operations
     * @throws moodle_exception
     */
    public static function execute(array $merges): array {
        self::validate_parameters(self::execute_parameters(), [
            'merges' => $merges,
        ]);

        /** @var \context $systemcontext */
        $systemcontext = context_system::instance();
        require_capability('tool/mergeusers:mergeusers', $systemcontext);

        $results = [];
        $successful = 0;
        $failed = 0;
        $total = count($merges);

        foreach ($merges as $index => $merge) {
            try {
                $result = self::process_single_merge(
                    $merge['removeuserfield'],
                    $merge['removeuservalue'],
                    $merge['keepuserfield'],
                    $merge['keepuservalue']
                );

                $results[] = [
                    'index' => $index,
                    'success' => $result['success'],
                    'mergerequestid' => $result['mergerequestid'],
                    'message' => $result['message'],
                    'log' => $result['log'] ?? null,
                ];

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                    $results[count($results) - 1]['errorcode'] = $result['errorcode'] ?? 'mergefailed';
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
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
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
            'total' => new external_value(PARAM_INT, 'Total number of merge requests'),
            'successful' => new external_value(PARAM_INT, 'Number of successful merges'),
            'failed' => new external_value(PARAM_INT, 'Number of failed merges'),
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

    /**
     * Process a single merge operation.
     *
     * @param string $removeuserfield Field to identify user to remove
     * @param string $removeuservalue Value for user to remove
     * @param string $keepuserfield Field to identify user to keep
     * @param string $keepuservalue Value for user to keep
     * @return array Result of merge operation
     * @throws moodle_exception
     */
    private static function process_single_merge(
        string $removeuserfield,
        string $removeuservalue,
        string $keepuserfield,
        string $keepuservalue
    ): array {
        $removeuserid = merge_users::get_user($removeuserfield, $removeuservalue);
        $keepuserid = merge_users::get_user($keepuserfield, $keepuservalue);

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
            ];
        } catch (\Exception $e) {
            $logger = new logger();
            $logid = $logger->log($keepuserid, $removeuserid, false, [$e->getMessage()]);

            return [
                'success' => false,
                'mergerequestid' => $logid,
                'errorcode' => 'mergefailed',
                'message' => $e->getMessage(),
            ];
        }
    }
}
