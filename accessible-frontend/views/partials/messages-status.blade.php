{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Shared status banner for the messages group surfaces. Maps the whitelisted
    ?status flash to a success notification banner or an error summary.
    Expects: $status (string|null).
--}}
@php
    $successStatuses = [
        'group-created' => 'govuk_alpha_messages.status.group_created',
        'group-message-sent' => 'govuk_alpha_messages.status.group_message_sent',
        'group-member-added' => 'govuk_alpha_messages.status.group_member_added',
        'group-member-removed' => 'govuk_alpha_messages.status.group_member_removed',
        'group-left' => 'govuk_alpha_messages.status.group_left',
        'reaction-added' => 'govuk_alpha_messages.status.reaction_added',
        'reaction-removed' => 'govuk_alpha_messages.status.reaction_removed',
    ];
    $errorStatuses = [
        'group-disabled' => 'govuk_alpha_messages.status.group_disabled',
        'group-create-failed' => 'govuk_alpha_messages.status.group_create_failed',
        'group-message-empty' => 'govuk_alpha_messages.status.group_message_empty',
        'group-message-too-long' => 'govuk_alpha_messages.status.group_message_too_long',
        'group-message-failed' => 'govuk_alpha_messages.status.group_message_failed',
        'group-message-forbidden' => 'govuk_alpha_messages.status.group_message_forbidden',
        'group-member-invalid' => 'govuk_alpha_messages.status.group_member_invalid',
        'group-member-forbidden' => 'govuk_alpha_messages.status.group_member_forbidden',
        'group-member-not-found' => 'govuk_alpha_messages.status.group_member_not_found',
        'group-member-limit' => 'govuk_alpha_messages.status.group_member_limit',
        'group-member-failed' => 'govuk_alpha_messages.status.group_member_failed',
        'group-leave-failed' => 'govuk_alpha_messages.status.group_leave_failed',
        'group-vetting-required' => 'safeguarding.errors.vetting_required_title',
        'group-contact-restricted' => 'safeguarding.errors.contact_restricted_title',
        'group-policy-unavailable' => 'safeguarding.errors.policy_unavailable_title',
        'reaction-invalid' => 'govuk_alpha_messages.status.reaction_invalid',
        'reaction-forbidden' => 'govuk_alpha_messages.status.reaction_forbidden',
        'reaction-failed' => 'govuk_alpha_messages.status.reaction_failed',
    ];
    $statusValue = $status ?? null;
@endphp

@if ($statusValue && isset($successStatuses[$statusValue]))
    <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="messages-group-status-title">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="messages-group-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">{{ __($successStatuses[$statusValue]) }}</p>
        </div>
    </div>
@elseif ($statusValue && isset($errorStatuses[$statusValue]))
    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
        <div role="alert">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    <li>{{ __($errorStatuses[$statusValue]) }}</li>
                </ul>
            </div>
        </div>
    </div>
@endif
