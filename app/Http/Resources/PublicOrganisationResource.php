<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Resources;

use Illuminate\Support\Carbon;

final class PublicOrganisationResource
{
    /**
     * @param array<string, mixed> $organisation
     * @return array<string, mixed>
     */
    public static function augment(array $organisation): array
    {
        if (!self::shouldIncludePublicContract()) {
            return $organisation;
        }

        $organisation['public_contract'] = self::fromArray($organisation);

        return $organisation;
    }

    /**
     * @param array<string, mixed> $organisation
     * @return array<string, mixed>
     */
    public static function fromArray(array $organisation): array
    {
        $id = self::nullableInt($organisation['id'] ?? null) ?? 0;
        $name = self::stringValue($organisation['name'] ?? '');
        $description = self::stringValue($organisation['description'] ?? '');

        return [
            'id' => $id,
            'slug' => self::nullableString($organisation['slug'] ?? null) ?? (string) $id,
            'name' => $name,
            'description' => $description,
            'excerpt' => self::excerpt($description),
            'logo_image' => self::logoImage($organisation, $name),
            'website' => self::nullableString($organisation['website'] ?? null),
            'contact_email' => self::nullableString($organisation['contact_email'] ?? null),
            'location' => [
                'label' => self::nullableString($organisation['location'] ?? null),
            ],
            'owner' => self::owner($organisation),
            'stats' => [
                'opportunity_count' => self::nullableInt(
                    $organisation['opportunity_count'] ?? $organisation['opportunities_count'] ?? null
                ) ?? 0,
                'volunteer_count' => self::nullableInt(
                    $organisation['volunteer_count'] ?? $organisation['total_volunteers'] ?? null
                ) ?? 0,
                'total_hours' => self::nullableFloat($organisation['total_hours'] ?? null) ?? 0.0,
                'review_count' => self::nullableInt($organisation['review_count'] ?? null) ?? 0,
                'average_rating' => self::nullableFloat($organisation['average_rating'] ?? null) ?? 0.0,
            ],
            'org_type' => self::nullableString($organisation['org_type'] ?? null) ?? 'organisation',
            'created_at' => self::dateString($organisation['created_at'] ?? null),
            'updated_at' => self::dateString($organisation['updated_at'] ?? null),
            'status' => self::nullableString($organisation['status'] ?? null) ?? 'active',
        ];
    }

    /**
     * @param array<string, mixed> $organisation
     * @return array{url: string, alt_text: string}|null
     */
    private static function logoImage(array $organisation, string $name): ?array
    {
        $url = self::nullableString($organisation['logo_url'] ?? null);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'alt_text' => $name,
        ];
    }

    /**
     * @param array<string, mixed> $organisation
     * @return array{id: int|null, display_name: string|null, avatar_url: string|null}
     */
    private static function owner(array $organisation): array
    {
        $owner = is_array($organisation['owner'] ?? null) ? $organisation['owner'] : [];
        $firstName = self::nullableString($owner['first_name'] ?? null);
        $lastName = self::nullableString($owner['last_name'] ?? null);
        $displayName = self::nullableString($owner['name'] ?? null)
            ?? trim((string) $firstName . ' ' . (string) $lastName);

        return [
            'id' => self::nullableInt($owner['id'] ?? $organisation['user_id'] ?? null),
            'display_name' => $displayName !== '' ? $displayName : null,
            'avatar_url' => self::nullableString($owner['avatar_url'] ?? null),
        ];
    }

    private static function shouldIncludePublicContract(): bool
    {
        $header = request()->headers->get('X-Public-Contract');
        if ($header !== null && in_array(strtolower($header), ['1', 'true', 'yes'], true)) {
            return true;
        }

        $include = request()->query('include');
        if ($include === null) {
            return false;
        }

        $values = is_array($include) ? $include : explode(',', (string) $include);

        return in_array('public_contract', array_map(static fn ($value): string => trim((string) $value), $values), true);
    }

    private static function excerpt(string $value): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))) ?? '');

        if (mb_strlen($plain) <= 180) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, 177)) . '...';
    }

    private static function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return Carbon::parse((string) $value, 'UTC')->toIso8601String();
    }

    private static function stringValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return trim((string) $value);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::stringValue($value);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
