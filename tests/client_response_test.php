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

namespace block_dixeo_tutor;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_status;

/**
 * Tests for the browser-facing shaping of tutor API failures.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dixeo_tutor\client_response
 */
final class client_response_test extends \advanced_testcase {
    /**
     * A known API failure shows the translated local_dixeo message, with the tutor error code kept.
     */
    public function test_known_failure_uses_the_local_dixeo_string(): void {
        set_debugging(DEBUG_NONE);
        $exception = api_exception::from_response(['type' => 'rate_limit_exceeded', 'detail' => 'provider detail'], 429);

        $response = client_response::send_message_error($exception);

        $this->assertSame(get_string('error:busy', 'local_dixeo'), $response['errormessage']);
        $this->assertSame('api_error', $response['errorcode']);
        $this->assertStringNotContainsString('provider detail', json_encode($response));
    }

    /**
     * An unknown API failure shows the tutor's generic error everywhere.
     */
    public function test_unknown_failure_uses_the_tutor_string(): void {
        set_debugging(DEBUG_NONE);
        $exception = api_exception::from_response(['type' => 'internal_diagnostics', 'detail' => 'stack trace'], 500);
        $generic = get_string('error_apierror', 'block_dixeo_tutor');

        $this->assertSame($generic, client_response::finalize_practice_quiz_error($exception)['error']);
        $this->assertSame($generic, client_response::finalize_teach_lesson_error($exception)['error']);
        $this->assertSame($generic, client_response::cancellation_result('id', false, $exception)['message']);
        $status = client_response::job_status_error('id', $exception);
        $this->assertSame('api_error', $status['error']['type']);
        $this->assertSame($generic, $status['error']['detail']);
        $this->assertSame('', client_response::cancellation_result('id', true)['message']);
    }

    /**
     * A failed job keeps a known code with its message, falls back to the tutor string otherwise, and loses its namespace.
     */
    public function test_sanitize_job_status(): void {
        set_debugging(DEBUG_NONE);
        $build = static fn(string $type): job_status => job_status::from_array([
            'id' => '5f38d9aa-f40c-4992-9727-982f050ff9fd',
            'type' => 'module',
            'status' => 'failed',
            'createdAt' => '2026-01-01T00:00:00Z',
            'namespace' => 'tenant',
            'error' => ['type' => $type, 'detail' => 'provider sentence'],
        ]);

        $known = client_response::sanitize_job_status($build('rate_limited'));
        $this->assertSame(get_string('error:busy', 'local_dixeo'), $known['error']['detail']);
        $this->assertArrayNotHasKey('namespace', $known);

        $unknown = client_response::sanitize_job_status($build('weird_failure'));
        $this->assertSame('api_error', $unknown['error']['type']);
        $this->assertSame(get_string('error_apierror', 'block_dixeo_tutor'), $unknown['error']['title']);
        $this->assertStringNotContainsString('provider sentence', json_encode($unknown));
    }
}
