{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Status banner for blog/review comment + reaction outcomes.
    Required var: $status (string|null), $errorAnchor (string, default '#body').
--}}
@php
    $status = $status ?? null;
    $errorAnchor = $errorAnchor ?? '#body';
    $successStates = ['comment-added', 'reply-added', 'comment-updated', 'comment-deleted', 'reaction-added', 'reaction-removed'];
    $errorStates = ['comment-invalid', 'comment-empty', 'comment-failed', 'comment-update-failed', 'comment-delete-failed', 'reaction-failed'];
@endphp
@if (in_array($status, $successStates, true))
    <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="blogreviews-comment-status">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="blogreviews-comment-status">{{ __('govuk_alpha_blogreviews.states.success_title') }}</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_blogreviews.comment_states.' . $status) }}</p>
        </div>
    </div>
@elseif (in_array($status, $errorStates, true))
    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
        <div role="alert">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_blogreviews.states.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    <li><a href="{{ $errorAnchor }}">{{ __('govuk_alpha_blogreviews.comment_states.' . $status) }}</a></li>
                </ul>
            </div>
        </div>
    </div>
@endif
