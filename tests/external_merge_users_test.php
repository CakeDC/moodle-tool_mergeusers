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
 * Tests for merge_users external API.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_merge_users_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test successful merge of two users.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     */
    public function test_merge_users_success(): void {
        $this->setAdminUser();

        $usertoremove = $this->getDataGenerator()->create_user(['username' => 'removeuser']);
        $usertokeep = $this->getDataGenerator()->create_user(['username' => 'keepuser']);

        $result = merge_users::execute('username', 'removeuser', 'username', 'keepuser');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('mergerequestid', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('log', $result);
    }

    /**
     * Test merge fails when user cannot be found.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     * @covers \tool_mergeusers\external\merge_users::get_user
     */
    public function test_merge_users_cannot_find_user(): void {
        $this->setAdminUser();

        $usertokeep = $this->getDataGenerator()->create_user(['username' => 'keepuser']);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Cannot find the user with username nonexistent.');

        merge_users::execute('username', 'nonexistent', 'username', 'keepuser');
    }

    /**
     * Test merge fails when multiple users match criteria.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     * @covers \tool_mergeusers\external\merge_users::get_user
     */
    public function test_merge_users_too_many_users(): void {
        $this->setAdminUser();

        $this->getDataGenerator()->create_user(['email' => 'duplicate@test.com']);
        $this->getDataGenerator()->create_user(['email' => 'duplicate@test.com']);
        $usertokeep = $this->getDataGenerator()->create_user(['username' => 'keepuser']);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('More than one user found with email duplicate@test.com.');

        merge_users::execute('email', 'duplicate@test.com', 'username', 'keepuser');
    }

    /**
     * Test merge fails when trying to merge same user.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     */
    public function test_merge_users_same_user(): void {
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user(['username' => 'testuser']);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Trying to merge the same user');

        merge_users::execute('username', 'testuser', 'username', 'testuser');
    }

    /**
     * Test merge requires proper permissions.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     */
    public function test_merge_users_permission_required(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $usertoremove = $this->getDataGenerator()->create_user(['username' => 'removeuser']);
        $usertokeep = $this->getDataGenerator()->create_user(['username' => 'keepuser']);

        $this->expectException(moodle_exception::class);

        merge_users::execute('username', 'removeuser', 'username', 'keepuser');
    }

    /**
     * Test merge users identified by email.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     * @covers \tool_mergeusers\external\merge_users::get_user
     */
    public function test_merge_users_by_email(): void {
        $this->setAdminUser();

        $usertoremove = $this->getDataGenerator()->create_user(['email' => 'remove@test.com']);
        $usertokeep = $this->getDataGenerator()->create_user(['email' => 'keep@test.com']);

        $result = merge_users::execute('email', 'remove@test.com', 'email', 'keep@test.com');

        $this->assertTrue($result['success']);
    }

    /**
     * Test merge users identified by ID.
     *
     * @covers \tool_mergeusers\external\merge_users::execute
     * @covers \tool_mergeusers\external\merge_users::get_user
     */
    public function test_merge_users_by_id(): void {
        $this->setAdminUser();

        $usertoremove = $this->getDataGenerator()->create_user();
        $usertokeep = $this->getDataGenerator()->create_user();

        $result = merge_users::execute('id', (string)$usertoremove->id, 'id', (string)$usertokeep->id);

        $this->assertTrue($result['success']);
    }
}
