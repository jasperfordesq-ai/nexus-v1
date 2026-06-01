<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Exceptions;

/**
 * Thrown by CourseQuizService::submitAttempt when a learner has already used
 * all of a quiz's allowed attempts. The check runs inside a row-locked
 * transaction so the ceiling cannot be bypassed by concurrent submissions.
 */
class MaxAttemptsExceededException extends \RuntimeException
{
}
