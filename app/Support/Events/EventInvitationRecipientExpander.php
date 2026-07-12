<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventInvitationCampaignType;
use App\Exceptions\EventRegistrationFoundationException;
use Illuminate\Support\Facades\DB;

/**
 * Expands allow-listed invitation inputs into an immutable, encrypted snapshot.
 *
 * Audience criteria are deliberately declarative. They never contain column
 * names, operators, SQL fragments, or arbitrary query expressions supplied by
 * a caller.
 */
final class EventInvitationRecipientExpander
{
    private const MAX_RECIPIENTS = 10000;
    private const MAX_GROUPS = 25;
    private const MAX_EXCLUSIONS = 1000;
    private const SUPPORTED_LOCALES = [
        'ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt',
    ];

    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
    ) {
    }

    /**
     * @param array<string,mixed> $source
     * @return array{
     *   recipients:list<array{type:string,member_id:?int,email:?string}>,
     *   errors:list<array{row:int,code:string}>,
     *   preview_count:int,
     *   source_reference:?string,
     *   source_hash:string,
     *   snapshot:array<string,mixed>,
     *   criteria_summary:?array<string,mixed>
     * }
     */
    public function expand(
        int $tenantId,
        EventInvitationCampaignType $type,
        array $source,
    ): array {
        $reference = null;
        $candidates = [];
        $structuralErrors = [];
        $criteriaSnapshot = null;
        $criteriaSummary = null;

        if ($type === EventInvitationCampaignType::Group) {
            if (array_diff(array_keys($source), ['group_id']) !== []) {
                throw new EventRegistrationFoundationException('event_invitation_source_fields_unknown');
            }
            $groupId = filter_var($source['group_id'] ?? null, FILTER_VALIDATE_INT);
            if ($groupId === false || $groupId <= 0
                || ! DB::table('groups')->where('tenant_id', $tenantId)->where('id', $groupId)->exists()) {
                throw new EventRegistrationFoundationException('event_invitation_group_not_found');
            }
            $reference = 'group:' . $groupId;
            $candidates = DB::table('group_members')
                ->where('tenant_id', $tenantId)
                ->where('group_id', $groupId)
                ->where('status', 'active')
                ->orderBy('id')
                ->pluck('user_id')
                ->map(static fn (mixed $id): array => ['member_id' => (int) $id])
                ->all();
        } elseif ($type === EventInvitationCampaignType::Member) {
            $allowed = ['member_id', 'member_ids'];
            if (array_diff(array_keys($source), $allowed) !== []) {
                throw new EventRegistrationFoundationException('event_invitation_source_fields_unknown');
            }
            if (array_key_exists('member_id', $source) && array_key_exists('member_ids', $source)) {
                throw new EventRegistrationFoundationException('event_invitation_source_alias_conflict');
            }
            $ids = array_key_exists('member_id', $source)
                ? [$source['member_id']]
                : ($source['member_ids'] ?? []);
            if (! is_array($ids) || ! array_is_list($ids)) {
                throw new EventRegistrationFoundationException('event_invitation_member_source_invalid');
            }
            foreach ($ids as $id) {
                $candidates[] = ['member_id' => $id];
            }
        } elseif ($type === EventInvitationCampaignType::Audience) {
            [$candidates, $criteriaSnapshot, $criteriaSummary, $reference] =
                $this->audienceCandidates($tenantId, $source);
        } elseif ($type === EventInvitationCampaignType::Email) {
            if (array_diff(array_keys($source), ['emails']) !== []) {
                throw new EventRegistrationFoundationException('event_invitation_source_fields_unknown');
            }
            $emails = $source['emails'] ?? [];
            if (! is_array($emails) || ! array_is_list($emails)) {
                throw new EventRegistrationFoundationException('event_invitation_email_source_invalid');
            }
            foreach ($emails as $email) {
                $candidates[] = ['email' => $email];
            }
        } else {
            if (array_diff(array_keys($source), ['csv']) !== []) {
                throw new EventRegistrationFoundationException('event_invitation_source_fields_unknown');
            }
            $csv = $source['csv'] ?? null;
            if (! is_string($csv) || $csv === '' || strlen($csv) > 5_000_000) {
                throw new EventRegistrationFoundationException('event_invitation_csv_invalid');
            }
            [$candidates, $structuralErrors] = $this->csvCandidates($csv);
        }

        if (count($candidates) > self::MAX_RECIPIENTS) {
            throw new EventRegistrationFoundationException('event_invitation_recipient_limit_exceeded');
        }

        $recipients = [];
        $errors = $structuralErrors;
        $seen = [];
        $memberCandidateIds = [];
        foreach ($candidates as $candidate) {
            if (array_key_exists('member_id', $candidate)
                && filter_var($candidate['member_id'], FILTER_VALIDATE_INT) !== false
                && (int) $candidate['member_id'] > 0) {
                $memberCandidateIds[] = (int) $candidate['member_id'];
            }
        }
        $validMemberIds = $memberCandidateIds === [] ? [] : DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($memberCandidateIds)))
            ->where('status', 'active')
            ->pluck('id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
            ->all();

        foreach ($candidates as $offset => $candidate) {
            $row = isset($candidate['row']) ? (int) $candidate['row'] : $offset + 1;
            if (array_key_exists('member_id', $candidate)) {
                $id = filter_var($candidate['member_id'], FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0 || ! isset($validMemberIds[(int) $id])) {
                    $errors[] = ['row' => $row, 'code' => 'member_not_found'];
                    continue;
                }
                $dedupe = 'member:' . (int) $id;
                if (isset($seen[$dedupe])) {
                    $errors[] = ['row' => $row, 'code' => 'duplicate_target'];
                    continue;
                }
                $seen[$dedupe] = true;
                $recipients[] = ['type' => 'member', 'member_id' => (int) $id, 'email' => null];
                continue;
            }

            try {
                if (! is_string($candidate['email'] ?? null)) {
                    throw new EventRegistrationFoundationException('event_invitation_email_invalid');
                }
                $email = $this->support->normalizeEmail($candidate['email']);
            } catch (EventRegistrationFoundationException) {
                $errors[] = ['row' => $row, 'code' => 'email_invalid'];
                continue;
            }
            $dedupe = 'email:' . $this->support->emailBlindHash($tenantId, $email);
            if (isset($seen[$dedupe])) {
                $errors[] = ['row' => $row, 'code' => 'duplicate_target'];
                continue;
            }
            $seen[$dedupe] = true;
            $recipients[] = ['type' => 'email', 'member_id' => null, 'email' => $email];
        }

        $previewCount = count($candidates) + count($structuralErrors);

        $snapshot = [
            'schema_version' => 1,
            'campaign_type' => $type->value,
            'recipients' => $recipients,
            'errors' => $errors,
            'preview_count' => $previewCount,
            'source_reference' => $reference,
        ];
        if ($criteriaSnapshot !== null) {
            $snapshot['criteria'] = $criteriaSnapshot;
        }

        return [
            'recipients' => $recipients,
            'errors' => $errors,
            'preview_count' => $previewCount,
            'source_reference' => $reference,
            'source_hash' => $this->support->requestHash($snapshot),
            'snapshot' => $snapshot,
            'criteria_summary' => $criteriaSummary,
        ];
    }

    /**
     * Restore only a campaign snapshot produced by expand(). This is the sole
     * issuance input for scheduled and immediate campaigns, so target drift
     * between preview and send cannot silently change the audience.
     *
     * @return array{
     *   recipients:list<array{type:string,member_id:?int,email:?string}>,
     *   errors:list<array{row:int,code:string}>,
     *   preview_count:int,
     *   source_reference:?string,
     *   source_hash:string,
     *   snapshot:array<string,mixed>,
     *   criteria_summary:null
     * }
     */
    public function restoreSnapshot(
        string $ciphertext,
        EventInvitationCampaignType $expectedType,
    ): array {
        $decoded = json_decode($this->support->decrypt($ciphertext), true);
        if (! is_array($decoded)
            || (int) ($decoded['schema_version'] ?? 0) !== 1
            || ($decoded['campaign_type'] ?? null) !== $expectedType->value
            || ! is_array($decoded['recipients'] ?? null)
            || ! array_is_list($decoded['recipients'])
            || ! is_array($decoded['errors'] ?? null)
            || ! array_is_list($decoded['errors'])
            || ! is_int($decoded['preview_count'] ?? null)
            || ($decoded['source_reference'] ?? null) !== null
                && ! is_string($decoded['source_reference'])) {
            throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
        }

        $recipients = [];
        $seen = [];
        foreach ($decoded['recipients'] as $recipient) {
            if (! is_array($recipient) || ! in_array($recipient['type'] ?? null, ['member', 'email'], true)) {
                throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
            }
            if ($recipient['type'] === 'member') {
                $memberId = filter_var($recipient['member_id'] ?? null, FILTER_VALIDATE_INT);
                if ($memberId === false || $memberId <= 0 || ($recipient['email'] ?? null) !== null) {
                    throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
                }
                $key = 'member:' . $memberId;
                $normalized = ['type' => 'member', 'member_id' => (int) $memberId, 'email' => null];
            } else {
                if (($recipient['member_id'] ?? null) !== null || ! is_string($recipient['email'] ?? null)) {
                    throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
                }
                $email = $this->support->normalizeEmail($recipient['email']);
                $key = 'email:' . $email;
                $normalized = ['type' => 'email', 'member_id' => null, 'email' => $email];
            }
            if (isset($seen[$key])) {
                throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
            }
            $seen[$key] = true;
            $recipients[] = $normalized;
        }
        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
        }

        $errors = [];
        foreach ($decoded['errors'] as $error) {
            if (! is_array($error)
                || filter_var($error['row'] ?? null, FILTER_VALIDATE_INT) === false
                || (int) $error['row'] <= 0
                || ! is_string($error['code'] ?? null)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $error['code']) !== 1) {
                throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
            }
            $errors[] = ['row' => (int) $error['row'], 'code' => $error['code']];
        }
        if ($decoded['preview_count'] !== count($recipients) + count($errors)) {
            throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
        }

        $snapshot = [
            'schema_version' => 1,
            'campaign_type' => $expectedType->value,
            'recipients' => $recipients,
            'errors' => $errors,
            'preview_count' => $decoded['preview_count'],
            'source_reference' => $decoded['source_reference'],
        ];
        if (array_key_exists('criteria', $decoded)) {
            if (! is_array($decoded['criteria'])) {
                throw new EventRegistrationFoundationException('event_invitation_campaign_snapshot_invalid');
            }
            $snapshot['criteria'] = $decoded['criteria'];
        }

        return [
            'recipients' => $recipients,
            'errors' => $errors,
            'preview_count' => $decoded['preview_count'],
            'source_reference' => $decoded['source_reference'],
            'source_hash' => $this->support->requestHash($snapshot),
            'snapshot' => $snapshot,
            'criteria_summary' => null,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array{0:list<array{member_id:mixed}>,1:array<string,mixed>,2:array<string,mixed>,3:string}
     */
    private function audienceCandidates(int $tenantId, array $source): array
    {
        if (array_keys($source) === ['member_ids']) {
            $ids = $source['member_ids'];
            if (! is_array($ids) || ! array_is_list($ids)) {
                throw new EventRegistrationFoundationException('event_invitation_member_source_invalid');
            }
            $criteria = ['member_ids' => $ids];
            $summary = [
                'kind' => 'explicit_selection',
                'selected_count' => count($ids),
            ];
            $reference = 'audience:' . substr($this->support->requestHash($criteria), 0, 24);

            return [
                array_map(static fn (mixed $id): array => ['member_id' => $id], $ids),
                $criteria,
                $summary,
                $reference,
            ];
        }
        if (array_keys($source) !== ['criteria'] || ! is_array($source['criteria'])) {
            throw new EventRegistrationFoundationException('event_invitation_audience_criteria_invalid');
        }

        $raw = $source['criteria'];
        $allowed = [
            'all_active', 'approved', 'exclude_member_ids', 'group_ids',
            'group_match', 'has_email', 'joined_after', 'joined_before',
            'preferred_languages', 'roles',
        ];
        if (array_diff(array_keys($raw), $allowed) !== []) {
            throw new EventRegistrationFoundationException('event_invitation_audience_criteria_unknown');
        }
        if ($raw === []) {
            throw new EventRegistrationFoundationException('event_invitation_audience_criteria_empty');
        }

        $criteria = [];
        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('deleted_at');

        if (array_key_exists('all_active', $raw)) {
            $allActive = $this->boolean($raw['all_active'], 'event_invitation_audience_all_active_invalid');
            if (! $allActive) {
                throw new EventRegistrationFoundationException('event_invitation_audience_all_active_invalid');
            }
            $criteria['all_active'] = true;
        }
        if (array_key_exists('approved', $raw)) {
            $criteria['approved'] = $this->boolean(
                $raw['approved'],
                'event_invitation_audience_approved_invalid',
            );
            $query->where('is_approved', $criteria['approved'] ? 1 : 0);
        }
        if (array_key_exists('has_email', $raw)) {
            $criteria['has_email'] = $this->boolean(
                $raw['has_email'],
                'event_invitation_audience_has_email_invalid',
            );
            $query->where(static function ($email) use ($criteria): void {
                if ($criteria['has_email']) {
                    $email->whereNotNull('email')->where('email', '!=', '');
                } else {
                    $email->whereNull('email')->orWhere('email', '');
                }
            });
        }

        $roles = $this->stringList($raw, 'roles', 20, 64, '/^[A-Za-z][A-Za-z0-9_-]*$/');
        if ($roles !== null) {
            $criteria['roles'] = $roles;
            $query->whereIn('role', $roles);
        }
        $languages = $this->stringList($raw, 'preferred_languages', count(self::SUPPORTED_LOCALES), 15, '/^[a-z]{2}$/');
        if ($languages !== null) {
            $languages = array_map('strtolower', $languages);
            if (array_diff($languages, self::SUPPORTED_LOCALES) !== []) {
                throw new EventRegistrationFoundationException('event_invitation_audience_preferred_languages_invalid');
            }
            $criteria['preferred_languages'] = $languages;
            $query->whereIn('preferred_language', $languages);
        }

        foreach (['joined_after' => '>=', 'joined_before' => '<='] as $field => $operator) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }
            if (! is_string($raw[$field])
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw[$field]) !== 1
                || ! $this->isValidCalendarDate($raw[$field])) {
                throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
            }
            $criteria[$field] = $raw[$field];
            $query->where('created_at', $operator, $field === 'joined_before'
                ? $raw[$field] . ' 23:59:59'
                : $raw[$field] . ' 00:00:00');
        }
        if (isset($criteria['joined_after'], $criteria['joined_before'])
            && strcmp($criteria['joined_after'], $criteria['joined_before']) > 0) {
            throw new EventRegistrationFoundationException('event_invitation_audience_joined_range_invalid');
        }

        $groupIds = $this->positiveIntegerList($raw, 'group_ids', self::MAX_GROUPS);
        if ($groupIds !== null) {
            $validGroupCount = DB::table('groups')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $groupIds)
                ->distinct()
                ->count('id');
            if ($validGroupCount !== count($groupIds)) {
                throw new EventRegistrationFoundationException('event_invitation_audience_group_not_found');
            }
            $match = strtolower(trim((string) ($raw['group_match'] ?? 'any')));
            if (! in_array($match, ['any', 'all'], true)) {
                throw new EventRegistrationFoundationException('event_invitation_audience_group_match_invalid');
            }
            $criteria['group_ids'] = $groupIds;
            $criteria['group_match'] = $match;
            if ($match === 'any') {
                $query->whereExists(static function ($membership) use ($tenantId, $groupIds): void {
                    $membership->selectRaw('1')
                        ->from('group_members as audience_membership')
                        ->whereColumn('audience_membership.user_id', 'users.id')
                        ->where('audience_membership.tenant_id', $tenantId)
                        ->whereIn('audience_membership.group_id', $groupIds)
                        ->where('audience_membership.status', 'active');
                });
            } else {
                foreach ($groupIds as $groupId) {
                    $query->whereExists(static function ($membership) use ($tenantId, $groupId): void {
                        $membership->selectRaw('1')
                            ->from('group_members as audience_membership')
                            ->whereColumn('audience_membership.user_id', 'users.id')
                            ->where('audience_membership.tenant_id', $tenantId)
                            ->where('audience_membership.group_id', $groupId)
                            ->where('audience_membership.status', 'active');
                    });
                }
            }
        } elseif (array_key_exists('group_match', $raw)) {
            throw new EventRegistrationFoundationException('event_invitation_audience_group_match_invalid');
        }

        $excluded = $this->positiveIntegerList($raw, 'exclude_member_ids', self::MAX_EXCLUSIONS);
        if ($excluded !== null) {
            $criteria['exclude_member_ids'] = $excluded;
            $query->whereNotIn('id', $excluded);
        }

        $ids = $query->orderBy('id')->limit(self::MAX_RECIPIENTS + 1)->pluck('id');
        if ($ids->count() > self::MAX_RECIPIENTS) {
            throw new EventRegistrationFoundationException('event_invitation_recipient_limit_exceeded');
        }
        $summary = [
            'kind' => 'criteria',
            'criteria' => array_values(array_keys($criteria)),
            'role_count' => count($roles ?? []),
            'language_count' => count($languages ?? []),
            'group_count' => count($groupIds ?? []),
            'excluded_count' => count($excluded ?? []),
            'matched_count' => $ids->count(),
        ];
        $reference = 'audience:' . substr($this->support->requestHash($criteria), 0, 24);

        return [
            $ids->map(static fn (mixed $id): array => ['member_id' => (int) $id])->all(),
            $criteria,
            $summary,
            $reference,
        ];
    }

    private function boolean(mixed $value, string $reason): bool
    {
        if (! is_bool($value)) {
            throw new EventRegistrationFoundationException($reason);
        }

        return $value;
    }

    private function isValidCalendarDate(string $value): bool
    {
        return checkdate(
            (int) substr($value, 5, 2),
            (int) substr($value, 8, 2),
            (int) substr($value, 0, 4),
        );
    }

    /** @return list<string>|null */
    private function stringList(
        array $source,
        string $field,
        int $maximum,
        int $maxLength,
        string $pattern,
    ): ?array {
        if (! array_key_exists($field, $source)) {
            return null;
        }
        $values = $source[$field];
        if (! is_array($values) || ! array_is_list($values) || $values === [] || count($values) > $maximum) {
            throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
        }
        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
            }
            $value = trim($value);
            if ($value === '' || mb_strlen($value) > $maxLength || preg_match($pattern, $value) !== 1) {
                throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
            }
            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /** @return list<int>|null */
    private function positiveIntegerList(array $source, string $field, int $maximum): ?array
    {
        if (! array_key_exists($field, $source)) {
            return null;
        }
        $values = $source[$field];
        if (! is_array($values) || ! array_is_list($values) || $values === [] || count($values) > $maximum) {
            throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
        }
        $normalized = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new EventRegistrationFoundationException("event_invitation_audience_{$field}_invalid");
            }
            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }

    /** @return array{0:list<array{email:mixed}>,1:list<array{row:int,code:string}>} */
    private function csvCandidates(string $csv): array
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new EventRegistrationFoundationException('event_invitation_csv_unavailable');
        }
        try {
            fwrite($stream, $csv);
            rewind($stream);
            $header = fgetcsv($stream, 0, ',', '"', '\\');
            if ($header === false) {
                return [[], [['row' => 1, 'code' => 'csv_header_missing']]];
            }
            $header = array_map(static fn (mixed $value): string => mb_strtolower(trim((string) $value)), $header);
            $emailIndex = array_search('email', $header, true);
            if ($emailIndex === false) {
                return [[], [['row' => 1, 'code' => 'csv_email_column_missing']]];
            }
            $candidates = [];
            $row = 1;
            while (($columns = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                $row++;
                if ($columns === [null] || $columns === []) {
                    continue;
                }
                $candidates[] = ['email' => $columns[$emailIndex] ?? null, 'row' => $row];
                if (count($candidates) > self::MAX_RECIPIENTS) {
                    throw new EventRegistrationFoundationException('event_invitation_recipient_limit_exceeded');
                }
            }

            return [$candidates, []];
        } finally {
            fclose($stream);
        }
    }
}
