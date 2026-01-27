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

namespace tool_mergeusers\external;

use advanced_testcase;
use core\exception\moodle_exception;

/**
 * Tests for merge_users_bulk external API.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_merge_users_bulk_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test successful bulk merge of users.
     *
     * @covers \tool_mergeusers\external\merge_users_bulk::execute
     */
    public function test_merge_users_bulk_success(): void {
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'remove1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'keep1']);
        $user3 = $this->getDataGenerator()->create_user(['username' => 'remove2']);
        $user4 = $this->getDataGenerator()->create_user(['username' => 'keep2']);

        $merges = [
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'remove1',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'remove2',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep2',
            ],
        ];

        $result = merge_users_bulk::execute($merges);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(2, $result['results']);
        $this->assertTrue($result['results'][0]['success']);
        $this->assertTrue($result['results'][1]['success']);
    }

    /**
     * Test bulk merge with partial failures.
     *
     * @covers \tool_mergeusers\external\merge_users_bulk::execute
     */
    public function test_merge_users_bulk_partial_failure(): void {
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'remove1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'keep1']);

        $merges = [
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'remove1',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'nonexistent',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
        ];

        $result = merge_users_bulk::execute($merges);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['successful']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(2, $result['results']);
        $this->assertTrue($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
    }

    /**
     * Test bulk merge with all failures.
     *
     * @covers \tool_mergeusers\external\merge_users_bulk::execute
     */
    public function test_merge_users_bulk_all_failure(): void {
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'keep1']);

        $merges = [
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'nonexistent1',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'nonexistent2',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
        ];

        $result = merge_users_bulk::execute($merges);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(0, $result['successful']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(2, $result['results']);
        $this->assertFalse($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
    }

    /**
     * Test bulk merge with empty array.
     *
     * @covers \tool_mergeusers\external\merge_users_bulk::execute
     */
    public function test_merge_users_bulk_empty_array(): void {
        $this->setAdminUser();

        $result = merge_users_bulk::execute([]);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(0, $result['results']);
    }

    /**
     * Test bulk merge requires proper permissions.
     *
     * @covers \tool_mergeusers\external\merge_users_bulk::execute
     */
    public function test_merge_users_bulk_permission_required(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $merges = [
            [
                'removeuserfield' => 'username',
                'removeuservalue' => 'remove1',
                'keepuserfield' => 'username',
                'keepuservalue' => 'keep1',
            ],
        ];

        $this->expectException(moodle_exception::class);

        merge_users_bulk::execute($merges);
    }
}
