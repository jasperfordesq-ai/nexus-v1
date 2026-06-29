<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

class PublicChangelogService
{
    /**
     * @return array{route_key: string, path: string, content_source: string, source_path: string, title: string, items: array<int, array<string, string>>}
     */
    public function summary(int $limit = 12): array
    {
        $sourcePath = base_path('CHANGELOG.md');
        $markdown = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';

        return [
            'route_key' => 'changelog',
            'path' => '/changelog',
            'content_source' => 'public_changelog_markdown',
            'source_path' => 'CHANGELOG.md',
            'title' => $this->titleFrom($markdown, 'CHANGELOG.md'),
            'items' => $this->itemsFrom($markdown, $limit),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function itemsFrom(string $markdown, int $limit): array
    {
        $items = [];
        $current = null;
        $lines = preg_split('/\R/', $markdown) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^##\s+\[?([^\]\n]+)\]?(?:\s+-\s+(.+))?$/', $line, $matches) === 1) {
                if (is_array($current)) {
                    $items[] = $current;
                }

                if (count($items) >= $limit) {
                    break;
                }

                $title = trim($matches[1]);
                $current = [
                    'id' => $this->slugFor($title),
                    'title' => $title,
                    'description' => '',
                ];

                if (isset($matches[2]) && is_string($matches[2])) {
                    $current['released_at'] = trim($matches[2]);
                }

                continue;
            }

            if (!is_array($current) || $current['description'] !== '') {
                continue;
            }

            if (preg_match('/^\s*-\s+(.+)$/', $line, $matches) === 1) {
                $current['description'] = $this->plainText($matches[1]);
            }
        }

        if (is_array($current) && count($items) < $limit) {
            $items[] = $current;
        }

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['id'] !== '' && $item['title'] !== '',
        ));
    }

    private function titleFrom(string $markdown, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) === 1) {
            return trim($matches[1]);
        }

        return $fallback;
    }

    private function slugFor(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    private function plainText(string $markdown): string
    {
        $text = preg_replace('/\[(.*?)\]\([^)]+\)/', '$1', $markdown) ?? $markdown;
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;

        return trim($text);
    }
}
