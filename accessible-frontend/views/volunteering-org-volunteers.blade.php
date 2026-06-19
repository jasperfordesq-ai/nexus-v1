{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $volunteers = is_array($volunteers ?? null) ? $volunteers : [];
        $meta = is_array($meta ?? null) ? $meta : ['has_more' => false, 'cursor' => null];
        $fmtDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $isoDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.org_volunteers.back_link') }}</a>

    <span class="govuk-caption-xl">{{ $orgName !== '' ? $orgName : ($tenant['name'] ?? $tenantSlug) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.org_volunteers.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.org_volunteers.description') }}</p>

    @if (empty($volunteers))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_volunteering.org_volunteers.empty') }}</p>
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.org_volunteers.empty_hint') }}</p>
        </div>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--m govuk-visually-hidden">{{ __('govuk_alpha_volunteering.org_volunteers.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_volunteers.table_name') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_volunteers.table_email') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_volunteering.org_volunteers.table_hours') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_volunteering.org_volunteers.table_applications') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_volunteers.table_applied_date') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($volunteers as $v)
                    @php
                        $vId = (int) ($v['id'] ?? 0);
                        $vName = trim((string) ($v['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                        $vEmail = trim((string) ($v['email'] ?? ''));
                        $vHours = (float) ($v['total_hours'] ?? 0);
                        $vApps = (int) ($v['applications_count'] ?? 0);
                        $vWhen = $fmtDate($v['applied_at'] ?? null);
                        $vIso = $isoDate($v['applied_at'] ?? null);
                    @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header">
                            @if ($vId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $vId]) }}">{{ $vName }}</a>@else{{ $vName }}@endif
                        </th>
                        <td class="govuk-table__cell">
                            @if ($vEmail !== '')<a class="govuk-link" href="mailto:{{ $vEmail }}">{{ $vEmail }}</a>@endif
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format($vHours, 2) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $vApps }}</td>
                        <td class="govuk-table__cell">@if ($vWhen)<time datetime="{{ $vIso }}">{{ $vWhen }}</time>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <p class="govuk-body govuk-!-margin-top-4">
                <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.org.volunteers', ['tenantSlug' => $tenantSlug, 'id' => $orgId, 'cursor' => $meta['cursor']]) }}">{{ __('govuk_alpha_volunteering.org_volunteers.load_more') }}</a>
            </p>
        @endif
    @endif
@endsection
