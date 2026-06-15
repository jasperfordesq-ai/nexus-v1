{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusTag = [
            'draft' => 'govuk-tag--grey', 'pending' => 'govuk-tag--yellow', 'approved' => 'govuk-tag--blue',
            'active' => 'govuk-tag--turquoise', 'completed' => 'govuk-tag--green', 'cancelled' => 'govuk-tag--red',
            // React also surfaces these workflow statuses — map them so they no
            // longer silently render as "draft".
            'pending_participants' => 'govuk-tag--yellow', 'pending_broker' => 'govuk-tag--yellow',
            'pending_confirmation' => 'govuk-tag--yellow', 'disputed' => 'govuk-tag--orange',
        ];
        $knownStatuses = array_keys($statusTag);
        $statusLabel = function (string $s): string {
            $k = 'govuk_alpha.group_exchanges.statuses.' . $s;
            return \Illuminate\Support\Facades\Lang::has($k) ? __($k) : \Illuminate\Support\Str::headline($s);
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.group_exchanges.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.group_exchanges.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.group_exchanges.description') }}</p>

    @if ($status === 'cancelled')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="ge-list-status">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="ge-list-status">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.group_exchanges.states.cancelled') }}</p>
            </div>
        </div>
    @endif

    <a class="govuk-button" href="{{ route('govuk-alpha.group-exchanges.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.create_button') }}</a>

    @if (empty($exchanges))
        <div class="govuk-inset-text">{{ __('govuk_alpha.group_exchanges.empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.group_exchanges.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.group_exchanges.form_title_label') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.group_exchanges.status_label') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.group_exchanges.total_hours_label') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($exchanges as $e)
                    @php
                        $st = in_array($e['status'] ?? '', $knownStatuses, true) ? $e['status'] : 'draft';
                        $exTitle = trim((string) ($e['title'] ?? '')) ?: __('govuk_alpha.group_exchanges.title');
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">
                            <a class="govuk-link" href="{{ route('govuk-alpha.group-exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $e['id']]) }}">{{ $exTitle }}</a>
                        </td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $statusTag[$st] }}">{{ $statusLabel($st) }}</strong></td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((float) ($e['total_hours'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
