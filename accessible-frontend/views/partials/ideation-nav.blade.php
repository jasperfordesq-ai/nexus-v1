{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Ideation sub-navigation — a server-rendered GOV.UK tab strip (plain links, no JS).
    Links to the existing challenge list plus the new parity pages (campaigns,
    outcomes, and the admin "new challenge" form). Admin-only destinations are
    only shown when $ideationIsAdmin is true.
--}}
@php
    $ideationTabs = [
        'challenges' => [
            'label' => __('govuk_alpha_ideation.nav.challenges'),
            'href' => route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]),
        ],
    ];
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.ideation.campaigns')) {
        $ideationTabs['campaigns'] = [
            'label' => __('govuk_alpha_ideation.nav.campaigns'),
            'href' => route('govuk-alpha.ideation.campaigns', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.ideation.outcomes')) {
        $ideationTabs['outcomes'] = [
            'label' => __('govuk_alpha_ideation.nav.outcomes'),
            'href' => route('govuk-alpha.ideation.outcomes', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (($ideationIsAdmin ?? false) && \Illuminate\Support\Facades\Route::has('govuk-alpha.ideation.create')) {
        $ideationTabs['create'] = [
            'label' => __('govuk_alpha_ideation.nav.create'),
            'href' => route('govuk-alpha.ideation.create', ['tenantSlug' => $tenantSlug]),
        ];
    }
    $ideationActive = $ideationActiveTab ?? 'challenges';
@endphp
<div class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-4">
    <h2 class="govuk-tabs__title">{{ __('govuk_alpha_ideation.nav.heading') }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($ideationTabs as $tabKey => $tab)
            <li class="govuk-tabs__list-item{{ $ideationActive === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ $tab['href'] }}" @if ($ideationActive === $tabKey) aria-current="page" @endif>{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
</div>
