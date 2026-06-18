{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Courses sub-navigation — a server-rendered GOV.UK tab strip (plain links,
    no JS). Each destination is rendered only once its route exists. Set
    $coursesActiveTab in the parent view to highlight the current tab.
--}}
@php
    $coursesTabs = [
        'browse' => [
            'label' => __('govuk_alpha_commerce.courses_nav.browse'),
            'href' => route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]),
        ],
    ];
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.courses.mine')) {
        $coursesTabs['learning'] = [
            'label' => __('govuk_alpha_commerce.courses_nav.learning'),
            'href' => route('govuk-alpha.courses.mine', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.courses.instructor')) {
        $coursesTabs['teaching'] = [
            'label' => __('govuk_alpha_commerce.courses_nav.teaching'),
            'href' => route('govuk-alpha.courses.instructor', ['tenantSlug' => $tenantSlug]),
        ];
    }
    $coursesActive = $coursesActiveTab ?? 'browse';
@endphp
<nav class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_commerce.courses_nav.heading') }}">
    <h2 class="govuk-tabs__title">{{ __('govuk_alpha_commerce.courses_nav.heading') }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($coursesTabs as $tabKey => $tab)
            <li class="govuk-tabs__list-item{{ $coursesActive === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ $tab['href'] }}" @if ($coursesActive === $tabKey) aria-current="page" @endif>{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
