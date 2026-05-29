<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseQuiz;
use App\Models\CourseQuizAttempt;
use Carbon\Carbon;

/**
 * CourseQuizService — quiz delivery and grading.
 * Phase 1 auto-grades objective questions (mcq, multi, truefalse). Subjective
 * questions (short, essay) are flagged pending_review for instructor grading
 * in Phase 2.
 */
class CourseQuizService
{
    private const OBJECTIVE_TYPES = ['mcq', 'multi', 'truefalse'];

    /**
     * Quiz payload for a learner — questions WITHOUT correct answers/explanations.
     */
    public static function forLearner(int $quizId): ?array
    {
        $quiz = CourseQuiz::with('questions')->find($quizId);
        if (!$quiz) {
            return null;
        }

        $questions = $quiz->questions->map(static function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'prompt' => $q->prompt,
                'options' => $q->options,
                'points' => $q->points,
                'position' => $q->position,
            ];
        })->values()->all();

        return [
            'id' => $quiz->id,
            'course_id' => $quiz->course_id,
            'lesson_id' => $quiz->lesson_id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'pass_mark_percent' => $quiz->pass_mark_percent,
            'max_attempts' => $quiz->max_attempts,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'questions' => $questions,
        ];
    }

    public static function attemptsUsed(int $quizId, int $userId): int
    {
        return CourseQuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Grade and persist a quiz attempt.
     *
     * @param array<int|string,mixed> $answers map of question_id => answer(s)
     * @return array{attempt:CourseQuizAttempt,score_percent:float,passed:bool,needs_review:bool}
     */
    public static function submitAttempt(int $quizId, int $userId, array $answers, ?int $enrollmentId = null): array
    {
        $quiz = CourseQuiz::with('questions')->findOrFail($quizId);

        $totalPoints = 0;
        $earnedPoints = 0;
        $needsReview = false;

        foreach ($quiz->questions as $question) {
            $points = max(1, (int) $question->points);
            $totalPoints += $points;

            $given = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;

            if (!in_array($question->type, self::OBJECTIVE_TYPES, true)) {
                // Subjective — defer to instructor grading.
                $needsReview = true;
                continue;
            }

            if (self::isCorrect($question->correct ?? [], $given)) {
                $earnedPoints += $points;
            }
        }

        $scorePercent = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
        $passed = !$needsReview && $scorePercent >= (int) $quiz->pass_mark_percent;

        $attempt = CourseQuizAttempt::create([
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'enrollment_id' => $enrollmentId,
            'answers' => $answers,
            'score_percent' => $scorePercent,
            'passed' => $passed,
            'grading_status' => $needsReview ? 'pending_review' : 'auto',
            'submitted_at' => Carbon::now(),
        ]);

        return [
            'attempt' => $attempt,
            'score_percent' => $scorePercent,
            'passed' => $passed,
            'needs_review' => $needsReview,
        ];
    }

    /**
     * Attempts awaiting instructor review for a course (subjective questions).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pendingReviewForCourse(int $courseId): array
    {
        $quizIds = CourseQuiz::where('course_id', $courseId)->pluck('id')->all();
        if (!$quizIds) {
            return [];
        }

        return CourseQuizAttempt::whereIn('quiz_id', $quizIds)
            ->where('grading_status', 'pending_review')
            ->with(['quiz:id,title', 'user:id,name,avatar_url'])
            ->orderBy('submitted_at')
            ->get()
            ->toArray();
    }

    /**
     * Apply an instructor grade to an attempt.
     */
    public static function gradeAttempt(int $attemptId, float $scorePercent, bool $passed, ?string $feedback, int $gradedBy): ?CourseQuizAttempt
    {
        $attempt = CourseQuizAttempt::find($attemptId);
        if (!$attempt) {
            return null;
        }

        $attempt->score_percent = max(0, min(100, $scorePercent));
        $attempt->passed = $passed;
        $attempt->feedback = $feedback;
        $attempt->grading_status = 'graded';
        $attempt->graded_by = $gradedBy;
        $attempt->save();

        return $attempt;
    }

    /**
     * The course id that owns a given attempt (via its quiz), or null.
     */
    public static function courseIdForAttempt(int $attemptId): ?int
    {
        $attempt = CourseQuizAttempt::find($attemptId);
        if (!$attempt) {
            return null;
        }
        $quiz = CourseQuiz::find($attempt->quiz_id);
        return $quiz?->course_id;
    }

    /**
     * Compare a learner's answer against the stored correct answer(s).
     * Handles single (mcq/truefalse) and multi-select (order-independent).
     */
    private static function isCorrect($correct, $given): bool
    {
        $correctArr = array_map('strval', (array) $correct);
        $givenArr = array_map('strval', (array) $given);

        sort($correctArr);
        sort($givenArr);

        return $correctArr === $givenArr && $correctArr !== [];
    }
}
