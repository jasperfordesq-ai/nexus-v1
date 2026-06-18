{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha_events.polls.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.polls.title') }}</h1>

    @if ($status === 'polls-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="polls-updated-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="polls-updated-title">{{ __('govuk_alpha_events.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_events.polls.updated') }}</p>
            </div>
        </div>
    @elseif ($status === 'polls-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha_events.polls.failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_events.polls.intro') }}</p>

    @if (empty($polls))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_events.polls.none_heading') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_events.polls.none_body') }}</p>
        </div>
    @else
        <form method="post" action="{{ route('govuk-alpha.events.polls.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
            @csrf
            <fieldset class="govuk-fieldset" aria-describedby="event-polls-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_events.polls.choose_legend') }}</h2>
                </legend>
                <div id="event-polls-hint" class="govuk-hint">{{ __('govuk_alpha_events.polls.choose_hint') }}</div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    @foreach ($polls as $poll)
                        @php
                            $pollId = (int) ($poll['id'] ?? 0);
                            $question = trim((string) ($poll['question'] ?? '')) ?: ('#' . $pollId);
                            $attached = (bool) ($poll['attached'] ?? false);
                            $attachedElsewhere = !$attached && (int) ($poll['event_id'] ?? 0) > 0;
                        @endphp
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="poll-{{ $pollId }}" name="poll_ids[]" type="checkbox" value="{{ $pollId }}" @checked($attached)>
                            <label class="govuk-label govuk-checkboxes__label" for="poll-{{ $pollId }}">
                                {{ $question }}
                                @if ($attached)
                                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_events.polls.attached_tag') }}</strong>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha_events.polls.save_button') }}</button>
        </form>
    @endif
@endsection
