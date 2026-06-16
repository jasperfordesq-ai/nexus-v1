{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? ''));
        $successStates = ['discussion-created', 'reply-posted'];
        $failStates = ['discussion-failed', 'reply-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.back_to_group') }}</a>

    <span class="govuk-caption-l">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.discussions.title') }}</h1>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="disc-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="disc-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $failStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div></div>
        </div>
    @endif

    @if (!$isMember)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.groups.pending_member') }}</p></div>
    @else
        <p class="govuk-body">
            <a class="govuk-button" role="button" draggable="false" data-module="govuk-button"
               href="{{ route('govuk-alpha.groups.discussions.create', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.discussions.new_link') }}</a>
        </p>

        @if (empty($discussions))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.groups.discussions.empty') }}</p></div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($discussions as $d)
                    @php
                        $dId = (int) ($d['id'] ?? 0);
                        $dTitle = trim((string) ($d['title'] ?? ''));
                        $dAuthor = trim((string) ($d['author']['name'] ?? ''));
                        $dReplies = (int) ($d['reply_count'] ?? 0);
                        $dPinned = (bool) ($d['is_pinned'] ?? false);
                    @endphp
                    @if ($dId > 0)
                        <article class="nexus-alpha-card">
                            <div class="nexus-alpha-module-row">
                                <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                                    <a class="govuk-link" href="{{ route('govuk-alpha.groups.discussions.show', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'discussionId' => $dId]) }}">{{ $dTitle !== '' ? $dTitle : __('govuk_alpha.groups.discussions.view') }}</a>
                                </h2>
                                @if ($dPinned)
                                    <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.groups.discussions.pinned') }}</strong>
                                @endif
                            </div>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                                @if ($dAuthor !== ''){{ __('govuk_alpha.groups.discussions.started_by', ['name' => $dAuthor]) }} · @endif{{ __('govuk_alpha.groups.discussions.replies_count', ['count' => $dReplies]) }}
                            </p>
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
    @endif
@endsection
