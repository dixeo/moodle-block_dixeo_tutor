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
 * Sanitize webservice payloads before they reach the browser.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_status;
use local_dixeo\external\response_factory;

/**
 * Maps verbose API errors to controlled client-facing messages.
 */
class client_response {
    /**
     * Generic user-visible tutor error string.
     *
     * @return string
     */
    public static function generic_error_message(): string {
        return get_string('error_apierror', 'block_dixeo_tutor');
    }

    /**
     * Sanitize send_message success/error payload.
     *
     * @param array $payload Response from tutor service or response_factory.
     * @return array
     */
    public static function sanitize_send_message(array $payload): array {
        if (!empty($payload['errormessage'])) {
            debugging('Tutor send_message error: ' . $payload['errormessage'], DEBUG_DEVELOPER);
            $payload['errormessage'] = self::generic_error_message();
        }
        if (!empty($payload['errorcode'])) {
            // Keep a controlled code only; do not forward arbitrary provider codes.
            $payload['errorcode'] = 'api_error';
        }
        return $payload;
    }

    /**
     * Build a sanitized send_message error from an API exception.
     *
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function send_message_error(api_exception $exception): array {
        return [
            'completed' => false,
            'jobid' => '',
            'status' => 'failed',
            'progress' => 0,
            'errormessage' => self::error_message($exception),
            'errorcode' => 'api_error',
        ];
    }

    /**
     * Shape a job status for the browser.
     *
     * A known API error code keeps its translated local_dixeo message; any other failure shows the
     * tutor's generic error. The provider's own error text never reaches the browser.
     *
     * @param job_status $status Job status from local_dixeo.
     * @return array
     */
    public static function sanitize_job_status(job_status $status): array {
        return response_factory::client_job_status($status, 'error_apierror', 'block_dixeo_tutor');
    }

    /**
     * Build a sanitized get_job_status error from an API exception.
     *
     * @param string $jobid Job UUID.
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function job_status_error(string $jobid, api_exception $exception): array {
        $message = self::error_message($exception);

        return [
            'jobid' => $jobid,
            'type' => '',
            'status' => 'failed',
            'progress' => 0,
            'createdat' => 0,
            'error' => [
                'type' => 'api_error',
                'title' => $message,
                'status' => 500,
                'detail' => $message,
            ],
        ];
    }

    /**
     * Build a sanitized generation submit error (quiz / teach / flush).
     *
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function job_submit_error(api_exception $exception): array {
        return self::send_message_error($exception);
    }

    /**
     * Build a sanitized practice-quiz finalize error.
     *
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function finalize_practice_quiz_error(api_exception $exception): array {
        return [
            'success' => false,
            'error' => self::error_message($exception),
            'title' => '',
            'introhtml' => '',
            'questions' => '[]',
        ];
    }

    /**
     * Build a sanitized teach-lesson finalize error.
     *
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function finalize_teach_lesson_error(api_exception $exception): array {
        return [
            'success' => false,
            'error' => self::error_message($exception),
            'title' => '',
            'introhtml' => '',
            'contenthtml' => '',
        ];
    }

    /**
     * Build a sanitized cancellation result for clients.
     *
     * @param string $jobid Job UUID.
     * @param bool $success Whether cancel succeeded.
     * @param api_exception|null $exception Optional failure from local_dixeo.
     * @return array
     */
    public static function cancellation_result(string $jobid, bool $success, ?api_exception $exception = null): array {
        $message = '';
        if (!$success) {
            $message = $exception !== null ? self::error_message($exception) : self::generic_error_message();
        }

        return [
            'success' => $success,
            'jobid' => $jobid,
            'message' => $message,
            'errorcode' => $success ? '' : 'api_error',
        ];
    }

    /**
     * Client-safe message for an API failure: the local_dixeo string of a known code, else the tutor's generic error.
     *
     * The failure detail is written to the developer log by {@see response_factory::safe_message()}.
     *
     * @param api_exception $exception Exception from local_dixeo.
     * @return string
     */
    private static function error_message(api_exception $exception): string {
        return response_factory::safe_message($exception, 'error_apierror', 'block_dixeo_tutor');
    }
}
