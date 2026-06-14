{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $stats = is_array($reviewStats ?? null) ? $reviewStats : [];
        $avg = (float) ($stats['average'] ?? 0);
        $total = (int) ($stats['total'] ?? 0);
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $otherName = function ($r, array $keys): string {
            foreach ($keys as $k) {
                $n = trim((string) ($r[$k]['name'] ?? ''));
                if ($n !== '') { return $n; }
            }
            return __('govuk_alpha.members.unknown_member');
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.reviews_page.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.reviews_page.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.reviews_page.description') }}</p>

    @if (($status ?? null) === 'review-submitted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="review-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="review-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.reviews_page.submit_success') }}</p>
            </div>
        </div>
    @elseif (in_array($status ?? null, ['review-invalid', 'review-duplicate', 'review-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">
                        @if (($status ?? null) === 'review-duplicate')
                            {{ __('govuk_alpha.reviews_page.submit_duplicate') }}
                        @elseif (($status ?? null) === 'review-invalid')
                            {{ __('govuk_alpha.reviews_page.submit_invalid') }}
                        @else
                            {{ __('govuk_alpha.reviews_page.submit_failed') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <dl class="govuk-summary-list govuk-!-margin-bottom-8">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.reviews_page.average_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $total > 0 ? number_format($avg, 1) . ' / 5' : '—' }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.reviews_page.total_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format($total) }}</dd>
        </div>
    </dl>

    {{-- Received --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.reviews_page.received_tab') }}</h2>
    @if (empty($reviewsReceived))
        <p class="govuk-inset-text">{{ __('govuk_alpha.reviews_page.received_empty') }}</p>
    @else
        @foreach ($reviewsReceived as $r)
            @php $name = ($r['is_anonymous'] ?? false) ? __('govuk_alpha.reviews_page.anonymous') : $otherName($r, ['reviewer', 'user']); @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.reviews_page.by_label', ['name' => $name]) }}</h3>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.reviews_page.rating_label', ['value' => (int) ($r['rating'] ?? 0)]) }}@if ($d = $dateFmt($r['created_at'] ?? null)) · {{ $d }}@endif</p>
                @if (trim((string) ($r['comment'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ $r['comment'] }}</p>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Given --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.reviews_page.given_tab') }}</h2>
    @if (empty($reviewsGiven))
        <p class="govuk-inset-text">{{ __('govuk_alpha.reviews_page.given_empty') }}</p>
    @else
        @foreach ($reviewsGiven as $r)
            @php $name = $otherName($r, ['receiver', 'reviewee', 'user']); @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.reviews_page.for_label', ['name' => $name]) }}</h3>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.reviews_page.rating_label', ['value' => (int) ($r['rating'] ?? 0)]) }}@if ($d = $dateFmt($r['created_at'] ?? null)) · {{ $d }}@endif</p>
                @if (trim((string) ($r['comment'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ $r['comment'] }}</p>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Pending --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.reviews_page.pending_tab') }}</h2>
    @if (empty($reviewsPending))
        <p class="govuk-inset-text">{{ __('govuk_alpha.reviews_page.pending_empty') }}</p>
    @else
        @foreach ($reviewsPending as $p)
            @php
                $name = trim((string) ($p['receiver_name'] ?? '')) !== '' ? $p['receiver_name'] : $otherName($p, ['other_user', 'partner', 'user', 'receiver']);
                $exId = (int) ($p['exchange_id'] ?? ($p['transaction_id'] ?? ($p['id'] ?? 0)));
                $receiverId = (int) ($p['receiver_id'] ?? 0);
                $transactionId = (int) ($p['transaction_id'] ?? $exId);
                $exTitle = trim((string) ($p['exchange_title'] ?? ''));
            @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.reviews_page.for_label', ['name' => $name]) }}</h3>
                @if ($exTitle !== '')
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">{{ $exTitle }}</p>
                @endif

                @if ($receiverId > 0)
                    <form method="post" action="{{ route('govuk-alpha.reviews.store', ['tenantSlug' => $tenantSlug]) }}">
                        @csrf
                        <input type="hidden" name="receiver_id" value="{{ $receiverId }}">
                        @if ($transactionId > 0)
                            <input type="hidden" name="transaction_id" value="{{ $transactionId }}">
                        @endif
                        <fieldset class="govuk-fieldset govuk-!-margin-bottom-2">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.reviews_page.rating_legend') }}</legend>
                            <div class="govuk-radios govuk-radios--inline govuk-radios--small" data-module="govuk-radios">
                                @for ($star = 5; $star >= 1; $star--)
                                    <div class="govuk-radios__item">
                                        <input class="govuk-radios__input" id="rating-{{ $exId }}-{{ $star }}" name="rating" type="radio" value="{{ $star }}" @if ($star === 5) checked @endif>
                                        <label class="govuk-label govuk-radios__label" for="rating-{{ $exId }}-{{ $star }}">{{ $star }}</label>
                                    </div>
                                @endfor
                            </div>
                        </fieldset>
                        <div class="govuk-form-group govuk-!-margin-bottom-2">
                            <label class="govuk-label govuk-label--s" for="comment-{{ $exId }}">{{ __('govuk_alpha.reviews_page.comment_label') }}</label>
                            <div id="comment-hint-{{ $exId }}" class="govuk-hint">{{ __('govuk_alpha.reviews_page.comment_hint') }}</div>
                            <textarea class="govuk-textarea" id="comment-{{ $exId }}" name="comment" rows="3" maxlength="2000" aria-describedby="comment-hint-{{ $exId }}"></textarea>
                        </div>
                        <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.reviews_page.write_review') }}</button>
                    </form>
                @elseif ($exId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.exchanges.show'))
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exId]) }}" role="button" data-module="govuk-button">{{ __('govuk_alpha.reviews_page.write_review') }}</a>
                @endif
            </div>
        @endforeach
    @endif
@endsection
