<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * ApiResponseSentException — thrown in TESTING mode by BaseApiController::jsonResponse()
 * to halt controller execution after a response has been sent (mimics the exit call
 * that runs in production).
 *
 * Tests that use makeApiRequest() catch \Throwable, so this exception causes the
 * output-buffered JSON to be captured and the HTTP status code (set before the throw)
 * to be preserved — giving the test the first response that the controller sent.
 */
/**
 *  Use AppCoreApiResponseSentException instead. This class is maintained for backward compatibility only.
 */
/**
 * @deprecated Use AppCoreApiResponseSentException instead. Maintained for backward compatibility.
 */
class ApiResponseSentException extends \RuntimeException
{
    public function __construct(int $httpStatus = 200)
    {
        parent::__construct('API response sent with HTTP ' . $httpStatus, $httpStatus);
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
