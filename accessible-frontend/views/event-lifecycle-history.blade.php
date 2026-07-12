{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('event_lifecycle_history.back_to_event') }}</a>

    <span class="govuk-caption-xl">{{ $event['title'] }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_lifecycle_history.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_lifecycle_history.description') }}</p>

    <div class="govuk-inset-text">{{ __('event_lifecycle_history.immutable_explanation') }}</div>

    @if (empty($entries))
        <h2 class="govuk-heading-m">{{ __('event_lifecycle_history.empty_title') }}</h2>
        <p class="govuk-body">{{ __('event_lifecycle_history.empty_description') }}</p>
    @else
        <ol class="govuk-list" aria-label="{{ __('event_lifecycle_history.list_label') }}">
            @foreach ($entries as $entry)
                <li class="govuk-summary-card govuk-!-margin-bottom-6">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">{{ __('event_lifecycle_history.version', ['version' => $entry['lifecycle_version']]) }}</h2>
                        <strong class="govuk-tag govuk-tag--green">{{ __('event_lifecycle_history.immutable') }}</strong>
                    </div>
                    <div class="govuk-summary-card__content">
                        <dl class="govuk-summary-list">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.recorded_at') }}</dt>
                                <dd class="govuk-summary-list__value">
                                    @if (!empty($entry['created_at']))
                                        <time datetime="{{ $entry['created_at'] }}">{{ \Carbon\CarbonImmutable::parse($entry['created_at'])->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                                    @else
                                        {{ __('event_lifecycle_history.timestamp_unknown') }}
                                    @endif
                                </dd>
                            </div>
                            @if (in_array('publication', $entry['evidence']['axes_changed'], true))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.publication_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ __('event_lifecycle_history.transition', [
                                        'from' => __('event_lifecycle_history.states.publication.' . $entry['publication']['from']),
                                        'to' => __('event_lifecycle_history.states.publication.' . $entry['publication']['to']),
                                    ]) }}</dd>
                                </div>
                            @endif
                            @if (in_array('operational', $entry['evidence']['axes_changed'], true))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.operational_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ __('event_lifecycle_history.transition', [
                                        'from' => __('event_lifecycle_history.states.operational.' . $entry['operational']['from']),
                                        'to' => __('event_lifecycle_history.states.operational.' . $entry['operational']['to']),
                                    ]) }}</dd>
                                </div>
                            @endif
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.actor_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $entry['actor']['display_name'] ?: __('event_lifecycle_history.unknown_actor', ['id' => $entry['actor']['id']]) }}</dd>
                            </div>
                            @if (!empty($entry['reason']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.reason_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $entry['reason'] }}</dd>
                                </div>
                            @endif
                        </dl>

                        @php
                            $cascade = array_filter(
                                $entry['evidence']['cascade'],
                                static fn ($count): bool => is_int($count) && $count > 0,
                            );
                        @endphp
                        @if ($cascade !== [] || !empty($entry['evidence']['series']) || !empty($entry['evidence']['notifications_suppressed']))
                            <h3 class="govuk-heading-s">{{ __('event_lifecycle_history.evidence_title') }}</h3>
                            <ul class="govuk-list govuk-list--bullet">
                                @foreach ($cascade as $key => $count)
                                    <li>{{ __('event_lifecycle_history.cascade.' . $key, ['count' => $count]) }}</li>
                                @endforeach
                                @if (!empty($entry['evidence']['series']))
                                    <li>{{ __('event_lifecycle_history.series.' . $entry['evidence']['series']['member_type'], ['id' => $entry['evidence']['series']['root_event_id']]) }}</li>
                                @endif
                                @if (!empty($entry['evidence']['notifications_suppressed']))
                                    <li>{{ __('event_lifecycle_history.notifications_suppressed') }}</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if (!empty($pagination['has_more']) && !empty($pagination['next_cursor']))
        <nav class="govuk-pagination govuk-pagination--block" aria-label="{{ __('event_lifecycle_history.pagination_label') }}">
            <div class="govuk-pagination__next">
                <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.lifecycle-history', [
                    'tenantSlug' => $tenantSlug,
                    'id' => $event['id'],
                    'cursor' => $pagination['next_cursor'],
                    'per_page' => $pagination['per_page'],
                ]) }}">
                    <span class="govuk-pagination__link-title">{{ __('event_lifecycle_history.load_more') }}</span>
                </a>
            </div>
        </nav>
    @endif
@endsection
