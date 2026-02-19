<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

class IcsHelper
{
    public static function generate($summary, $description, $location, $start, $end)
    {
        $dtStart = gmdate("Ymd\THis\Z", strtotime($start));
        $dtEnd = gmdate("Ymd\THis\Z", strtotime($end));
        $now = gmdate("Ymd\THis\Z");
        $uid = uniqid() . "@nexus-timebank";

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Nexus//Timebank Platform//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:$uid\r\n";
        $ics .= "DTSTAMP:$now\r\n";
        $ics .= "DTSTART:$dtStart\r\n";
        $ics .= "DTEND:$dtEnd\r\n";
        $ics .= "SUMMARY:" . self::escape($summary) . "\r\n";
        $ics .= "DESCRIPTION:" . self::escape($description) . "\r\n";
        $ics .= "LOCATION:" . self::escape($location) . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR";

        return $ics;
    }

    private static function escape($str)
    {
        $str = str_replace(["\r", "\n", ";", ","], ["", "\\n", "\\;", "\\,"], $str);
        return $str;
    }
}
