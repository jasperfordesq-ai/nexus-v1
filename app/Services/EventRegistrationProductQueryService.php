<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventRegistrationFoundationException;
use App\Models\EventInvitationCampaign;
use App\Models\EventRegistrationFormVersion;
use App\Models\EventRegistrationSettings;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\EventRegistrationPolicy;
use App\Support\Events\EventRegistrationFoundationSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Private product projections; decrypted answers remain in the audited submission service. */
final class EventRegistrationProductQueryService
{
    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
        private readonly EventPolicy $eventPolicy = new EventPolicy(),
        private readonly EventRegistrationPolicy $registrationPolicy = new EventRegistrationPolicy(),
    ) {
    }

    /** @return array<string,mixed> */
    public function organizerOverview(int $eventId, User|int $actor): array
    {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $event = $this->support->concreteEvent($tenantId, $eventId, false);
        $persistedActor = $this->support->actor($tenantId, $actor, false);
        if (! $this->registrationPolicy->reviewAnswers($persistedActor, $event)) {
            throw new EventRegistrationFoundationException('event_registration_overview_denied');
        }
        $canViewRoster = $this->eventPolicy->viewRoster($persistedActor, $event);
        $canViewSensitive = $this->registrationPolicy->viewSensitiveAnswers($persistedActor, $event);
        $settings = EventRegistrationSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();
        $forms = EventRegistrationFormVersion::withoutGlobalScopes()
            ->with('questions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderByDesc('version_number')
            ->get();
        $submissions = DB::table('event_registration_form_submissions as submission')
            ->join('users as member', function ($join): void {
                $join->on('member.id', '=', 'submission.user_id')
                    ->on('member.tenant_id', '=', 'submission.tenant_id');
            })
            ->where('submission.tenant_id', $tenantId)
            ->where('submission.event_id', $eventId)
            ->orderByDesc('submission.updated_at')
            ->limit(1000)
            ->get([
                'submission.id', 'submission.registration_id', 'submission.form_version_id',
                'submission.user_id', 'submission.status', 'submission.revision',
                'submission.attempt_number', 'submission.effective_slot',
                'submission.supersedes_submission_id', 'submission.lineage_root_submission_id',
                'submission.superseded_at',
                'submission.submitted_at', 'submission.withdrawn_at', 'submission.updated_at',
                'member.name as member_name',
            ])
            ->map(static function (object $row) use ($canViewRoster): array {
                $result = (array) $row;
                if (! $canViewRoster) {
                    unset($result['member_name']);
                }

                return $result;
            })
            ->all();
        $campaigns = EventInvitationCampaign::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->withCount('invitations')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
        $campaignDeliveryCounts = DB::table('event_invitation_delivery_evidence')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('campaign_id, status, COUNT(*) AS aggregate_count')
            ->groupBy('campaign_id', 'status')
            ->get()
            ->groupBy('campaign_id')
            ->map(static fn ($rows): array => $rows->mapWithKeys(
                static fn (object $row): array => [(string) $row->status => (int) $row->aggregate_count],
            )->all())
            ->all();
        $guests = DB::table('event_registration_guests as guest')
            ->leftJoin('event_registration_guest_attendance as attendance', function ($join): void {
                $join->on('attendance.tenant_id', '=', 'guest.tenant_id')
                    ->on('attendance.event_id', '=', 'guest.event_id')
                    ->on('attendance.guest_id', '=', 'guest.id');
            })
            ->where('guest.tenant_id', $tenantId)
            ->where('guest.event_id', $eventId)
            ->orderBy('guest.registration_id')
            ->orderBy('guest.guest_number')
            ->limit(5000)
            ->get([
                'guest.id', 'guest.registration_id', 'guest.ticket_entitlement_id',
                'guest.guest_number', 'guest.revision', 'guest.status',
                'guest.display_name_ciphertext', 'guest.email_ciphertext', 'guest.phone_ciphertext',
                'guest.preferred_locale', 'guest.notification_consent', 'guest.retention_due_at',
                'guest.withdrawn_at', 'guest.anonymised_at',
                'attendance.id as attendance_id', 'attendance.attendance_status',
                'attendance.attendance_version', 'attendance.checked_in_at', 'attendance.checked_out_at',
                'attendance.no_show_at',
            ])
            ->map(function (object $guest) use ($canViewRoster, $canViewSensitive): array {
                $row = [
                    'id' => (int) $guest->id,
                    'registration_id' => (int) $guest->registration_id,
                    'ticket_entitlement_id' => $guest->ticket_entitlement_id === null
                        ? null
                        : (int) $guest->ticket_entitlement_id,
                    'guest_number' => (int) $guest->guest_number,
                    'revision' => (int) $guest->revision,
                    'status' => (string) $guest->status,
                    'preferred_locale' => $guest->preferred_locale,
                    'notification_consent' => (bool) $guest->notification_consent,
                    'retention_due_at' => $guest->retention_due_at,
                    'withdrawn_at' => $guest->withdrawn_at,
                    'anonymised_at' => $guest->anonymised_at,
                    'attendance' => $guest->attendance_id === null ? null : [
                        'id' => (int) $guest->attendance_id,
                        'status' => (string) $guest->attendance_status,
                        'version' => (int) $guest->attendance_version,
                        'checked_in_at' => $guest->checked_in_at,
                        'checked_out_at' => $guest->checked_out_at,
                        'no_show_at' => $guest->no_show_at,
                    ],
                ];
                if ($canViewRoster && is_string($guest->display_name_ciphertext)) {
                    $row['display_name'] = $this->support->decrypt($guest->display_name_ciphertext);
                }
                if ($canViewSensitive) {
                    $row['email'] = is_string($guest->email_ciphertext)
                        ? $this->support->decrypt($guest->email_ciphertext)
                        : null;
                    $row['phone'] = is_string($guest->phone_ciphertext)
                        ? $this->support->decrypt($guest->phone_ciphertext)
                        : null;
                }

                return $row;
            })
            ->all();

        return [
            'settings' => $settings,
            'forms' => $forms,
            'submissions' => $submissions,
            'campaigns' => $campaigns->map(static function (EventInvitationCampaign $campaign) use ($campaignDeliveryCounts): array {
                $row = $campaign->toArray();
                $row['delivery_counts'] = $campaignDeliveryCounts[(int) $campaign->id] ?? [];

                return $row;
            })->all(),
            'guests' => $guests,
            'permissions' => [
                'view_roster' => $canViewRoster,
                'view_sensitive_answers' => $canViewSensitive,
                'export_answers' => $this->registrationPolicy->exportAnswers($persistedActor, $event),
                'manage_retention' => $this->registrationPolicy->manageRetention($persistedActor, $event),
                'manage_attendance' => $this->eventPolicy->manageAttendance($persistedActor, $event),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function attendeeState(int $eventId, User|int $actor): array
    {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $event = $this->support->concreteEvent($tenantId, $eventId, false);
        $persistedActor = $this->support->actor($tenantId, $actor, false);
        if (! $this->eventPolicy->view($persistedActor, $event)) {
            throw new EventRegistrationFoundationException('event_registration_attendee_view_denied');
        }
        $settings = EventRegistrationSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->first();
        $form = null;
        if ($settings !== null && $settings->published_form_version !== null) {
            $form = EventRegistrationFormVersion::withoutGlobalScopes()
                ->with('questions')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('version_number', (int) $settings->published_form_version)
                ->where('status', 'published')
                ->first();
        }
        $registrations = DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', (int) $persistedActor->id)
            ->orderBy('id')
            ->get([
                'id', 'registration_state', 'registration_version', 'party_size',
                'state_changed_at', 'invited_at', 'pending_at', 'confirmed_at',
                'declined_at', 'cancelled_at',
            ]);
        $registrationIds = $registrations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $submissions = $registrationIds === [] ? collect() : DB::table('event_registration_form_submissions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', (int) $persistedActor->id)
            ->whereIn('registration_id', $registrationIds)
            ->orderByDesc('id')
            ->get([
                'id', 'registration_id', 'form_version_id',
                'supersedes_submission_id', 'lineage_root_submission_id',
                'attempt_number', 'effective_slot', 'revision', 'status',
                'submitted_at', 'withdrawn_at', 'anonymised_at', 'superseded_at',
                'created_at', 'updated_at',
            ]);
        $guests = $registrationIds === [] ? collect() : DB::table('event_registration_guests')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('registration_id', $registrationIds)
            ->orderBy('registration_id')
            ->orderBy('guest_number')
            ->get()
            ->map(function (object $guest): array {
                return [
                    'id' => (int) $guest->id,
                    'registration_id' => (int) $guest->registration_id,
                    'guest_number' => (int) $guest->guest_number,
                    'revision' => (int) $guest->revision,
                    'status' => (string) $guest->status,
                    'display_name' => is_string($guest->display_name_ciphertext)
                        ? $this->support->decrypt($guest->display_name_ciphertext)
                        : null,
                    'email' => is_string($guest->email_ciphertext)
                        ? $this->support->decrypt($guest->email_ciphertext)
                        : null,
                    'preferred_locale' => $guest->preferred_locale,
                    'notification_consent' => (bool) $guest->notification_consent,
                    'ticket_entitlement_id' => $guest->ticket_entitlement_id === null
                        ? null
                        : (int) $guest->ticket_entitlement_id,
                ];
            });
        $invitations = DB::table('event_invitations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('target_type', 'member')
            ->where('member_user_id', (int) $persistedActor->id)
            ->orderByDesc('id')
            ->get([
                'id', 'campaign_id', 'status', 'invitation_version',
                'token_expires_at', 'accepted_at', 'revoked_at', 'expired_at',
            ]);

        return [
            'settings' => $settings,
            'form' => $form,
            'registrations' => $registrations,
            'submissions' => $submissions,
            'guests' => $guests,
            'invitations' => $invitations,
        ];
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_registration_settings', 'event_registration_form_versions',
            'event_registration_form_questions', 'event_registration_form_submissions',
            'event_invitation_campaigns', 'event_invitations',
            'event_invitation_delivery_evidence', 'event_registration_guests',
            'event_registration_guest_attendance',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventRegistrationFoundationException('event_registration_product_schema_unavailable');
            }
        }
    }
}
