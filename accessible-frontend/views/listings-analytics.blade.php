{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $a = is_array($analytics ?? null) ? $analytics : [];
        $summary = is_array($a['summary'] ?? null) ? $a['summary'] : [];
        $viewsOverTime = is_array($a['views_over_time'] ?? null) ? $a['views_over_time'] : [];
        $contactsOverTime = is_array($a['contacts_over_time'] ?? null) ? $a['contacts_over_time'] : [];
        $contactTypes = is_array($a['contact_types'] ?? null) ? $a['contact_types'] : [];
        $hasData = $summary !== [] || $viewsOverTime !== [] || $contactsOverTime !== [];

        $trend = (float) ($summary['views_trend_percent'] ?? 0);

        $maxViews = 1;
        foreach ($viewsOverTime as $d) { $maxViews = max($maxViews, (int) ($d['count'] ?? 0)); }
        $maxContacts = 1;
        foreach ($contactsOverTime as $d) { $maxContacts = max($maxContacts, (int) ($d['count'] ?? 0)); }

        $formatDate = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : '';
        $contactTypeLabel = function (string $type): string {
            $key = 'govuk_alpha_listings.analytics.contact_type_' . $type;
            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : \Illuminate\Support\Str::headline($type);
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ __('govuk_alpha_listings.analytics.back_to_listing') }}</a>

    <span class="govuk-caption-xl">{{ $listingTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_listings.analytics.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_listings.analytics.description') }}</p>

    <ul class="govuk-list nexus-alpha-actions govuk-!-margin-bottom-6">
        <li><a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ __('govuk_alpha_listings.analytics.view_listing') }}</a></li>
        <li><a class="govuk-link" href="{{ route('govuk-alpha.listings.edit', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ __('govuk_alpha_listings.analytics.edit_listing') }}</a></li>
    </ul>

    {{-- Time-period selector (no-JS GET form). --}}
    <form method="get" action="{{ route('govuk-alpha.listings.analytics', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset" aria-describedby="days-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_listings.analytics.period_legend') }}</legend>
                <div id="days-hint" class="govuk-hint">{{ __('govuk_alpha_listings.analytics.period_hint') }}</div>
                <div class="govuk-radios govuk-radios--small govuk-radios--inline" data-module="govuk-radios">
                    @foreach (['7', '14', '30', '60', '90'] as $option)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="{{ $loop->first ? 'days' : 'days-' . $option }}" name="days" type="radio" value="{{ $option }}" @checked((int) $days === (int) $option)>
                            <label class="govuk-label govuk-radios__label" for="{{ $loop->first ? 'days' : 'days-' . $option }}">{{ __('govuk_alpha_listings.analytics.period_days', ['count' => (int) $option]) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_listings.analytics.period_submit') }}</button>
    </form>

    @unless ($hasData)
        <div class="govuk-inset-text">{{ __('govuk_alpha_listings.analytics.no_data') }}</div>
    @else
        {{-- Key metrics --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_listings.analytics.key_metrics_heading') }}</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.total_views') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format((int) ($summary['total_views'] ?? 0)) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.unique_viewers') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format((int) ($summary['unique_viewers'] ?? 0)) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.total_contacts') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format((int) ($summary['total_contacts'] ?? 0)) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.total_saves') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format((int) ($summary['total_saves'] ?? 0)) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.contact_rate') }}</dt>
                <dd class="govuk-summary-list__value">{{ (float) ($summary['contact_rate'] ?? 0) }}%</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.save_rate') }}</dt>
                <dd class="govuk-summary-list__value">{{ (float) ($summary['save_rate'] ?? 0) }}%</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.views_trend') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if ($trend > 0)
                        {{ __('govuk_alpha_listings.analytics.trend_up', ['percent' => abs($trend)]) }}
                    @elseif ($trend < 0)
                        {{ __('govuk_alpha_listings.analytics.trend_down', ['percent' => abs($trend)]) }}
                    @else
                        {{ __('govuk_alpha_listings.analytics.trend_flat') }}
                    @endif
                </dd>
            </div>
            @if (!empty($a['created_at']))
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.listing_created') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $formatDate($a['created_at']) }}</dd>
                </div>
            @endif
            @if (!empty($a['expires_at']))
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_listings.analytics.listing_expires') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $formatDate($a['expires_at']) }}</dd>
                </div>
            @endif
        </dl>

        {{-- Views over time --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_listings.analytics.views_over_time') }}</h2>
        @if (empty($viewsOverTime))
            <p class="govuk-body">{{ __('govuk_alpha_listings.analytics.no_views_yet') }}</p>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_listings.analytics.views_over_time') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_listings.analytics.views_over_time') }}</th>
                        <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_listings.analytics.count_column') }}</span></th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_listings.analytics.count_column') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($viewsOverTime as $d)
                        @php
                            $dCount = (int) ($d['count'] ?? 0);
                            $dDate = !empty($d['date']) ? \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('j M') : '';
                        @endphp
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $dDate }}</th>
                            <td class="govuk-table__cell">
                                <progress value="{{ $dCount }}" max="{{ $maxViews }}" aria-label="{{ __('govuk_alpha_listings.analytics.views_on_date', ['date' => $dDate]) }}">{{ $dCount }}</progress>
                            </td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ $dCount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Contacts over time --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_listings.analytics.contacts_over_time') }}</h2>
        @if (empty($contactsOverTime))
            <p class="govuk-body">{{ __('govuk_alpha_listings.analytics.no_contacts_yet') }}</p>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_listings.analytics.contacts_over_time') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_listings.analytics.contacts_over_time') }}</th>
                        <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_listings.analytics.count_column') }}</span></th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_listings.analytics.count_column') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($contactsOverTime as $d)
                        @php
                            $dCount = (int) ($d['count'] ?? 0);
                            $dDate = !empty($d['date']) ? \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('j M') : '';
                        @endphp
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $dDate }}</th>
                            <td class="govuk-table__cell">
                                <progress value="{{ $dCount }}" max="{{ $maxContacts }}" aria-label="{{ __('govuk_alpha_listings.analytics.contacts_on_date', ['date' => $dDate]) }}">{{ $dCount }}</progress>
                            </td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ $dCount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Contact types breakdown --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_listings.analytics.contact_types_heading') }}</h2>
        @if (empty($contactTypes))
            <p class="govuk-body">{{ __('govuk_alpha_listings.analytics.no_contact_types') }}</p>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_listings.analytics.contact_types_heading') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_listings.analytics.contact_type_label') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_listings.analytics.count_column') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($contactTypes as $ct)
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $contactTypeLabel((string) ($ct['contact_type'] ?? '')) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($ct['count'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endunless
@endsection
