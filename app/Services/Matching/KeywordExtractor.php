<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Matching;

/**
 * KeywordExtractor — shared stop-word filtering + light stemming for match
 * signals. Extracted from SmartMatchingEngine so the batch context loader and
 * the pure scorer normalise text identically (the engine's public
 * extractKeywords() delegates here for backward compatibility).
 */
final class KeywordExtractor
{
    private const STOP_WORDS = [
        'the','a','an','and','or','but','in','on','at','to','for','of','with','by','from',
        'is','are','was','were','be','been','being','have','has','had','do','does','did',
        'will','would','could','should','may','might','must','shall','can','need','i','you',
        'he','she','it','we','they','my','your','his','her','its','our','their','this',
        'that','these','those','am','help','looking','need','want','offer','request',
    ];

    private const TWO_CHAR_DOMAIN_TERMS = [
        'ai','ml','ux','ui','go','vr','ar','it','hr','pr','qa','db','uk','eu','us','r',
    ];

    /** @return string[] unique stemmed keywords */
    public static function extract(string $text): array
    {
        $text = strtolower($text);

        preg_match_all('/\b[a-z]{3,}\b/', $text, $matches);
        $words = $matches[0] ?? [];

        preg_match_all('/\b[a-z]{1,2}\b/', $text, $shortMatches);
        foreach ($shortMatches[0] ?? [] as $short) {
            if (in_array($short, self::TWO_CHAR_DOMAIN_TERMS, true)) {
                $words[] = $short;
            }
        }

        $keywords = array_diff($words, self::STOP_WORDS);
        $keywords = array_map([self::class, 'stem'], $keywords);

        return array_values(array_unique($keywords));
    }

    public static function stem(string $word): string
    {
        $len = strlen($word);
        if ($len > 6 && substr($word, -3) === 'ing') return substr($word, 0, $len - 3);
        if ($len > 5 && substr($word, -2) === 'ed') return substr($word, 0, $len - 2);
        if ($len > 5 && substr($word, -2) === 'er') return substr($word, 0, $len - 2);
        if ($len > 4 && substr($word, -2) === 'es') return substr($word, 0, $len - 2);
        if ($len > 4 && substr($word, -1) === 's' && substr($word, -2) !== 'ss') return substr($word, 0, $len - 1);
        return $word;
    }
}
