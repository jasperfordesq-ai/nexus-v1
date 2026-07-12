{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $translation = 'govuk_alpha.events.communications.';
        $successMessages = [
            'created' => __($translation . 'success.created'),
            'scheduled' => __($translation . 'success.scheduled'),
            'cancelled' => __($translation . 'success.cancelled'),
            'retried' => __($translation . 'success.retried'),
        ];
        $dateLabel = static function (mixed $value) use ($translation): string {
            if (!is_string($value) || trim($value) === '') {
                return __($translation . 'not_recorded');
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, H:i T');
            } catch (\Throwable) {
                return __($translation . 'not_recorded');
            }
        };
        $totalPages = max(1, (int) ceil(((int) $pagination['total']) / max(1, (int) $pagination['per_page'])));
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __($translation . 'back_to_event') }}</a>

    @if ($errors->has('communication'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('communication') }}</p></div>
            </div>
        </div>
    @elseif (isset($successMessages[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $successMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __($translation . 'title') }}</h1>
    <p class="govuk-body-l">{{ __($translation . 'intro') }}</p>

    <div class="govuk-inset-text">
        <strong>{{ __($translation . 'privacy_title') }}</strong><br>
        {{ __($translation . 'privacy_description') }}
    </div>

    <h2 class="govuk-heading-l">{{ __($translation . 'compose_title') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.events.communications.preview', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="communication-variant">{{ __($translation . 'variant_label') }}</label>
            <select class="govuk-select" id="communication-variant" name="variant" required>
                @foreach (['announcement', 'follow_up', 'review_request'] as $variant)
                    <option value="{{ $variant }}" @selected($draft['variant'] === $variant)>{{ __($translation . 'variants.' . $variant) }}</option>
                @endforeach
            </select>
        </div>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __($translation . 'segments_label') }}</legend>
            <div class="govuk-hint">{{ __($translation . 'segments_hint') }}</div>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                @foreach (['registration_confirmed', 'waitlist_active', 'attendance_attended', 'attendance_no_show'] as $segment)
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="communication-segment-{{ $segment }}" name="segments[]" type="checkbox" value="{{ $segment }}" @checked(in_array($segment, $draft['segments'], true))>
                        <label class="govuk-label govuk-checkboxes__label" for="communication-segment-{{ $segment }}">{{ __($translation . 'segments.' . $segment) }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __($translation . 'channels_label') }}</legend>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                @foreach (['email', 'in_app', 'push'] as $channel)
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="communication-channel-{{ $channel }}" name="channels[]" type="checkbox" value="{{ $channel }}" @checked(in_array($channel, $draft['channels'], true))>
                        <label class="govuk-label govuk-checkboxes__label" for="communication-channel-{{ $channel }}">{{ __($translation . 'channels.' . $channel) }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="communication-body">{{ __($translation . 'body_label') }}</label>
            <div id="communication-body-hint" class="govuk-hint">{{ __($translation . 'body_hint') }}</div>
            <textarea class="govuk-textarea" id="communication-body" name="body" rows="8" maxlength="20000" aria-describedby="communication-body-hint" required>{{ $draft['body'] }}</textarea>
        </div>

        <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __($translation . 'preview_button') }}</button>
    </form>

    @if (is_array($preview))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="communication-preview-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="communication-preview-title">{{ __($translation . 'preview_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($translation . 'preview_summary', [
                    'recipients' => number_format((int) $preview['recipient_count']),
                    'deliveries' => number_format((int) $preview['delivery_count']),
                ]) }}</p>
                <p class="govuk-body">{{ __($translation . 'preview_notice') }}</p>
                <form method="post" action="{{ route('govuk-alpha.events.communications.create', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <input type="hidden" name="preview_confirmed" value="1">
                    <input type="hidden" name="variant" value="{{ $draft['variant'] }}">
                    @foreach ($draft['segments'] as $segment)
                        <input type="hidden" name="segments[]" value="{{ $segment }}">
                    @endforeach
                    @foreach ($draft['channels'] as $channel)
                        <input type="hidden" name="channels[]" value="{{ $channel }}">
                    @endforeach
                    <textarea name="body" hidden>{{ $draft['body'] }}</textarea>
                    <button class="govuk-button" data-module="govuk-button" type="submit">{{ __($translation . 'save_draft_button') }}</button>
                </form>
            </div>
        </div>
    @endif

    <h2 class="govuk-heading-l">{{ __($translation . 'history_list_title') }}</h2>
    @if (empty($broadcasts))
        <p class="govuk-body">{{ __($translation . 'empty') }}</p>
    @else
        @foreach ($broadcasts as $broadcast)
            <section class="govuk-!-margin-bottom-9" aria-labelledby="broadcast-{{ $broadcast['id'] }}-title">
                <h3 class="govuk-heading-m" id="broadcast-{{ $broadcast['id'] }}-title">{{ __($translation . 'variants.' . $broadcast['variant']) }}</h3>
                <strong class="govuk-tag{{ in_array($broadcast['status'], ['cancelled', 'failed'], true) ? ' govuk-tag--red' : ($broadcast['status'] === 'sent' ? ' govuk-tag--green' : '') }}">{{ __($translation . 'statuses.' . $broadcast['status']) }}</strong>
                <dl class="govuk-summary-list govuk-!-margin-top-4">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __($translation . 'version') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) $broadcast['version']) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __($translation . 'audience') }}</dt>
                        <dd class="govuk-summary-list__value">
                            {{ implode(', ', array_map(static fn (string $segment): string => __($translation . 'segments.' . $segment), $broadcast['audience']['segments'])) }}
                            <br>{{ __($translation . 'recipient_count', ['count' => number_format((int) $broadcast['audience']['recipient_count'])]) }}
                        </dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __($translation . 'channels_label') }}</dt>
                        <dd class="govuk-summary-list__value">{{ implode(', ', array_map(static fn (string $channel): string => __($translation . 'channels.' . $channel), $broadcast['channels'])) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __($translation . 'delivery') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __($translation . 'delivery_summary', [
                            'delivered' => number_format((int) $broadcast['delivery']['delivered']),
                            'total' => number_format((int) $broadcast['delivery']['total']),
                            'suppressed' => number_format((int) $broadcast['delivery']['suppressed']),
                            'dead' => number_format((int) $broadcast['delivery']['dead_lettered']),
                        ]) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __($translation . 'scheduled_for') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $dateLabel($broadcast['scheduled_at']) }}</dd>
                    </div>
                </dl>

                <div class="govuk-button-group">
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.events.communications.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'broadcast_id' => $broadcast['id']]) }}">{{ __($translation . 'view_audit') }}</a>
                </div>

                @if (!empty($broadcast['capabilities']['schedule']))
                    <form method="post" action="{{ route('govuk-alpha.events.communications.schedule', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'broadcastId' => $broadcast['id']]) }}" class="govuk-!-margin-bottom-6">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $broadcast['version'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="schedule-{{ $broadcast['id'] }}">{{ __($translation . 'schedule_label') }}</label>
                            <div id="schedule-{{ $broadcast['id'] }}-hint" class="govuk-hint">{{ __($translation . 'schedule_hint') }}</div>
                            <input class="govuk-input govuk-input--width-20" id="schedule-{{ $broadcast['id'] }}" name="scheduled_at" type="datetime-local" aria-describedby="schedule-{{ $broadcast['id'] }}-hint">
                        </div>
                        <button class="govuk-button" data-module="govuk-button" type="submit">{{ __($translation . 'schedule_button') }}</button>
                    </form>
                @endif

                @if (!empty($broadcast['capabilities']['cancel']))
                    <form method="post" action="{{ route('govuk-alpha.events.communications.cancel', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'broadcastId' => $broadcast['id']]) }}" class="govuk-!-margin-bottom-6">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $broadcast['version'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="cancel-{{ $broadcast['id'] }}">{{ __($translation . 'cancel_reason_label') }}</label>
                            <textarea class="govuk-textarea" id="cancel-{{ $broadcast['id'] }}" name="reason" rows="2" maxlength="500" required></textarea>
                        </div>
                        <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">{{ __($translation . 'cancel_button') }}</button>
                    </form>
                @endif

                @if (!empty($broadcast['capabilities']['retry']))
                    <form method="post" action="{{ route('govuk-alpha.events.communications.retry', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'broadcastId' => $broadcast['id']]) }}">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $broadcast['version'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <button class="govuk-button" data-module="govuk-button" type="submit">{{ __($translation . 'retry_button') }}</button>
                    </form>
                @endif
            </section>
        @endforeach
    @endif

    @if ($totalPages > 1)
        <nav class="govuk-pagination" aria-label="{{ __($translation . 'pagination') }}">
            @if ((int) $pagination['page'] > 1)
                <div class="govuk-pagination__prev"><a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.events.communications.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'page' => ((int) $pagination['page']) - 1]) }}">{{ __($translation . 'previous') }}</a></div>
            @endif
            @if ((int) $pagination['page'] < $totalPages)
                <div class="govuk-pagination__next"><a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.events.communications.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'page' => ((int) $pagination['page']) + 1]) }}">{{ __($translation . 'next') }}</a></div>
            @endif
        </nav>
    @endif

    @if (is_array($detail))
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">
        <h2 class="govuk-heading-l">{{ __($translation . 'audit_title') }}</h2>
        <p class="govuk-body">{{ __($translation . 'audit_description') }}</p>
        <ol class="govuk-list govuk-list--number">
            @foreach ($detail['history'] as $entry)
                <li>
                    <strong>{{ __($translation . 'history_actions.' . $entry['action']) }}</strong>
                    — {{ __($translation . 'statuses.' . $entry['to_status']) }},
                    {{ __($translation . 'audit_meta', ['version' => $entry['version'], 'date' => $dateLabel($entry['created_at'])]) }}
                </li>
            @endforeach
        </ol>
        @php
            $historyMeta = $detail['history_meta'];
            $historyPage = (int) $historyMeta['current_page'];
            $historyTotalPages = (int) $historyMeta['total_pages'];
            $historyRoute = static fn (int $targetPage): string => route('govuk-alpha.events.communications.index', [
                'tenantSlug' => $tenantSlug,
                'id' => $eventId,
                'page' => (int) $pagination['page'],
                'broadcast_id' => $detail['broadcast']['id'],
                'history_page' => $targetPage,
            ]);
        @endphp
        @if ($historyTotalPages > 1)
            <nav class="govuk-pagination" aria-label="{{ __($translation . 'audit_title') }}">
                @if ($historyPage > 1)
                    <div class="govuk-pagination__prev"><a class="govuk-link govuk-pagination__link" href="{{ $historyRoute($historyPage - 1) }}">{{ __($translation . 'previous') }}</a></div>
                @endif
                @if ($historyPage < $historyTotalPages)
                    <div class="govuk-pagination__next"><a class="govuk-link govuk-pagination__link" href="{{ $historyRoute($historyPage + 1) }}">{{ __($translation . 'next') }}</a></div>
                @endif
            </nav>
        @endif
    @endif
@endsection
