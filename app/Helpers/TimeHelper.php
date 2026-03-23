<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

/**
 * TimeHelper - Time formatting utilities
 *
 * Provides human-readable time formatting functions.
 */
class TimeHelper
{
    /**
     * Convert a datetime to a human-readable "time ago" string
     *
     * @param string|int $datetime DateTime string or Unix timestamp
     * @return string Human-readable time difference (e.g., "2 hours ago")
     */
    public static function timeAgo($datetime): string
    {
        if (is_numeric($datetime)) {
            $timestamp = (int) $datetime;
        } else {
            $timestamp = strtotime($datetime);
        }

        if ($timestamp === false) {
            return 'Unknown';
        }

        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 0) {
            return 'Just now';
        }

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes') . ' ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' ' . ($hours === 1 ? 'hour' : 'hours') . ' ago';
        }

        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' ' . ($days === 1 ? 'day' : 'days') . ' ago';
        }

        if ($diff < 2592000) {
            $weeks = (int) floor($diff / 604800);
            return $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks') . ' ago';
        }

        if ($diff < 31536000) {
            $months = (int) floor($diff / 2592000);
            return $months . ' ' . ($months === 1 ? 'month' : 'months') . ' ago';
        }

        $years = (int) floor($diff / 31536000);
        return $years . ' ' . ($years === 1 ? 'year' : 'years') . ' ago';
    }

    /**
     * Format a datetime for display
     *
     * @param string|int $datetime DateTime string or Unix timestamp
     * @param string $format PHP date format (default: 'M j, Y')
     * @return string Formatted date
     */
    public static function format($datetime, string $format = 'M j, Y'): string
    {
        if (is_numeric($datetime)) {
            $timestamp = (int) $datetime;
        } else {
            $timestamp = strtotime($datetime);
        }

        if ($timestamp === false) {
            return 'Unknown';
        }

        return date($format, $timestamp);
    }

    /**
     * Format a datetime with time
     *
     * @param string|int $datetime DateTime string or Unix timestamp
     * @return string Formatted date and time (e.g., "Jan 15, 2026 at 3:30 PM")
     */
    public static function formatWithTime($datetime): string
    {
        if (is_numeric($datetime)) {
            $timestamp = (int) $datetime;
        } else {
            $timestamp = strtotime($datetime);
        }

        if ($timestamp === false) {
            return 'Unknown';
        }

        return date('M j, Y', $timestamp) . ' at ' . date('g:i A', $timestamp);
    }
}
