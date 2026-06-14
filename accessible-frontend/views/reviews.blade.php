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
                $name = $otherName($p, ['other_user', 'partner', 'user', 'receiver']);
                $exId = (int) ($p['exchange_id'] ?? ($p['transaction_id'] ?? ($p['id'] ?? 0)));
            @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha.reviews_page.for_label', ['name' => $name]) }}</h3>
                @if ($exId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.exchanges.show'))
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exId]) }}" role="button" data-module="govuk-button">{{ __('govuk_alpha.reviews_page.write_review') }}</a>
                @endif
            </div>
        @endforeach
    @endif
@endsection
