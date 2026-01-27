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
 * Tests for merge_multiple_to_one external API.
 *
 * @package   tool_mergeusers
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_merge_multiple_to_one_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_merge_multiple_to_one_success(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $user3 = $this->getDataGenerator()->create_user(['username' => 'user3']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'user1'],
            ['userfield' => 'username', 'uservalue' => 'user2'],
            ['userfield' => 'username', 'uservalue' => 'user3'],
        ];

        $result = merge_multiple_to_one::execute('username', 'primary', $removeusers);

        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals($primary->id, $result['primaryuserid']);
        $this->assertCount(3, $result['results']);
        $this->assertTrue($result['results'][0]['success']);
        $this->assertTrue($result['results'][1]['success']);
        $this->assertTrue($result['results'][2]['success']);
    }

    public function test_merge_multiple_to_one_partial_failure(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'user1'],
            ['userfield' => 'username', 'uservalue' => 'nonexistent'],
        ];

        $result = merge_multiple_to_one::execute('username', 'primary', $removeusers);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['successful']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals($primary->id, $result['primaryuserid']);
        $this->assertCount(2, $result['results']);
        $this->assertTrue($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
    }

    public function test_merge_multiple_to_one_same_user_error(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'user1'],
            ['userfield' => 'username', 'uservalue' => 'primary'],
        ];

        $result = merge_multiple_to_one::execute('username', 'primary', $removeusers);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['successful']);
        $this->assertEquals(1, $result['failed']);
        $this->assertFalse($result['results'][1]['success']);
        $this->assertEquals('errorsameuser', $result['results'][1]['errorcode']);
    }

    public function test_merge_multiple_to_one_empty_array(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);

        $result = merge_multiple_to_one::execute('username', 'primary', []);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals($primary->id, $result['primaryuserid']);
        $this->assertCount(0, $result['results']);
    }

    public function test_merge_multiple_to_one_different_fields(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['email' => 'primary@test.com']);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'user2@test.com']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'user1'],
            ['userfield' => 'email', 'uservalue' => 'user2@test.com'],
        ];

        $result = merge_multiple_to_one::execute('email', 'primary@test.com', $removeusers);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['successful']);
        $this->assertEquals(0, $result['failed']);
    }

    public function test_merge_multiple_to_one_permission_required(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'user1'],
        ];

        $this->expectException(moodle_exception::class);

        merge_multiple_to_one::execute('username', 'primary', $removeusers);
    }

    public function test_merge_multiple_to_one_all_failure(): void {
        $this->setAdminUser();

        $primary = $this->getDataGenerator()->create_user(['username' => 'primary']);

        $removeusers = [
            ['userfield' => 'username', 'uservalue' => 'nonexistent1'],
            ['userfield' => 'username', 'uservalue' => 'nonexistent2'],
        ];

        $result = merge_multiple_to_one::execute('username', 'primary', $removeusers);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(0, $result['successful']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(2, $result['results']);
        $this->assertFalse($result['results'][0]['success']);
        $this->assertFalse($result['results'][1]['success']);
    }
}
