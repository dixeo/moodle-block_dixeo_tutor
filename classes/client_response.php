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
        debugging(
            'Tutor send_message failed: ' . $exception->get_error_code() . ' ' . $exception->getMessage(),
            DEBUG_DEVELOPER
        );

        return [
            'completed' => false,
            'jobid' => '',
            'status' => 'failed',
            'progress' => 0,
            'errormessage' => self::generic_error_message(),
            'errorcode' => 'api_error',
        ];
    }

    /**
     * Sanitize get_job_status payload (success or error).
     *
     * @param array $payload Job status array.
     * @return array
     */
    public static function sanitize_job_status(array $payload): array {
        unset($payload['namespace']);

        if (!empty($payload['error']) && is_array($payload['error'])) {
            $type = (string) ($payload['error']['type'] ?? '');
            $detail = (string) ($payload['error']['detail'] ?? '');
            $title = (string) ($payload['error']['title'] ?? '');
            debugging(
                'Tutor get_job_status error: type=' . $type . ' title=' . $title . ' detail=' . $detail,
                DEBUG_DEVELOPER
            );

            $generic = self::generic_error_message();
            $payload['error'] = [
                'type' => 'api_error',
                'title' => $generic,
                'status' => (int) ($payload['error']['status'] ?? 500),
                'detail' => $generic,
            ];
        }

        return $payload;
    }

    /**
     * Build a sanitized get_job_status error from an API exception.
     *
     * @param string $jobid Job UUID.
     * @param api_exception $exception Exception from local_dixeo.
     * @return array
     */
    public static function job_status_error(string $jobid, api_exception $exception): array {
        debugging(
            'Tutor get_job_status failed: job=' . $jobid
                . ' code=' . $exception->get_error_code()
                . ' ' . $exception->getMessage(),
            DEBUG_DEVELOPER
        );

        $generic = self::generic_error_message();

        return [
            'jobid' => $jobid,
            'type' => '',
            'status' => 'failed',
            'progress' => 0,
            'createdat' => 0,
            'error' => [
                'type' => 'api_error',
                'title' => $generic,
                'status' => 500,
                'detail' => $generic,
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
        debugging(
            'Tutor finalize_practice_quiz failed: ' . $exception->get_error_code() . ' ' . $exception->getMessage(),
            DEBUG_DEVELOPER
        );

        return [
            'success' => false,
            'error' => self::generic_error_message(),
            'title' => '',
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
        debugging(
            'Tutor finalize_teach_lesson failed: ' . $exception->get_error_code() . ' ' . $exception->getMessage(),
            DEBUG_DEVELOPER
        );

        return [
            'success' => false,
            'error' => self::generic_error_message(),
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
        if ($exception !== null) {
            debugging(
                'Tutor cancel_generation_job failed: job=' . $jobid
                    . ' code=' . $exception->get_error_code()
                    . ' ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        return [
            'success' => $success,
            'jobid' => $jobid,
            'message' => $success ? '' : self::generic_error_message(),
            'errorcode' => $success ? '' : 'api_error',
        ];
    }
}
