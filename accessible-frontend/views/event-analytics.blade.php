{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $rate = static fn (array $value): string => !empty($value['suppressed'])
            ? __('govuk_alpha.events.analytics.suppressed')
            : (($value['basis_points'] ?? null) === null
                ? '—'
                : __('govuk_alpha.events.analytics.percent', [
                    'value' => number_format(((int) $value['basis_points']) / 100, 1),
                ]));
        $privateCount = static fn (array $value): string => !empty($value['suppressed'])
            ? __('govuk_alpha.events.analytics.suppressed')
            : number_format((int) ($value['value'] ?? 0));
        $sections = [
            'registration' => [
                ['confirmed', $summary['registration']['confirmed']],
                ['pending', $summary['registration']['pending']],
                ['cancelled', $summary['registration']['cancelled']],
                ['capacity_remaining', $summary['registration']['remaining'] === null
                    ? __('govuk_alpha.events.analytics.not_limited')
                    : number_format((int) $summary['registration']['remaining'])],
            ],
            'acquisition' => [
                ['invitations_issued', $summary['invitation']['issued']],
                ['invitations_accepted', $summary['invitation']['accepted']],
                ['invitation_conversion', $rate($summary['invitation']['conversion'])],
                ['waitlist_joined', $summary['waitlist']['joined']],
                ['waitlist_accepted', $summary['waitlist']['accepted']],
                ['waitlist_conversion', $rate($summary['waitlist']['conversion'])],
            ],
            'attendance' => [
                ['checked_in', $summary['attendance']['checked_in']],
                ['attended', $summary['attendance']['attended']],
                ['no_show', $summary['attendance']['no_show']],
                ['attendance_rate', $rate($summary['attendance']['attendance_rate'])],
            ],
            'communications' => [
                ['delivered', $summary['communications']['delivered']],
                ['suppressed_deliveries', $summary['communications']['suppressed']],
                ['failed_deliveries', $summary['communications']['failed']],
                ['dead_lettered', $summary['communications']['dead_lettered']],
                ['delivery_rate', $rate($summary['communications']['delivery_rate'])],
            ],
            'funnel' => [
                ['event_views', $privateCount($summary['optional_funnel']['event_views'])],
                ['registration_starts', $privateCount($summary['optional_funnel']['registration_starts'])],
                ['start_conversion', $rate($summary['optional_funnel']['start_to_registration_conversion'])],
                ['guardian_consents', $privateCount($summary['safeguarding']['guardian_consents'])],
            ],
            'finance' => !empty($summary['tickets']['redacted'])
                ? [['finance', __('govuk_alpha.events.analytics.finance_redacted')]]
                : [
                    ['ticket_units', $summary['tickets']['confirmed_units'] ?? 0],
                    ['ticket_credit_value', $summary['tickets']['confirmed_credit_value'] ?? '0.00'],
                    ['completed_credit_claims', $summary['credits']['completed_claims']],
                    ['failed_credit_claims', $summary['credits']['failed_claims']],
                ],
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('govuk_alpha.events.analytics.back_to_event') }}</a>
    <span class="govuk-caption-l">{{ $summary['event_title'] }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.analytics.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.analytics.intro') }}</p>
    <p class="govuk-body-s">{{ __('govuk_alpha.events.analytics.generated_at', [
        'date' => \Illuminate\Support\Carbon::parse($summary['generated_at'])->translatedFormat('j F Y, H:i T'),
    ]) }}</p>

    <div class="govuk-inset-text">
        <strong>{{ __('govuk_alpha.events.analytics.privacy_title') }}</strong><br>
        {{ __('govuk_alpha.events.analytics.privacy_note', ['count' => $summary['privacy_threshold']]) }}
    </div>

    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.events.analytics.export', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('govuk_alpha.events.analytics.export') }}</a>

    @foreach ($sections as $section => $rows)
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.events.analytics.sections.' . $section) }}</h2>
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha.events.analytics.sections.' . $section) }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.analytics.columns.metric') }}</th>
                    <th class="govuk-table__header govuk-table__header--numeric" scope="col">{{ __('govuk_alpha.events.analytics.columns.value') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($rows as [$metric, $value])
                    <tr class="govuk-table__row">
                        <th class="govuk-table__header" scope="row">{{ __('govuk_alpha.events.analytics.metrics.' . $metric) }}</th>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ is_int($value) ? number_format($value) : $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endsection
