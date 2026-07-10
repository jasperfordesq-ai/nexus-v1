{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $faqGroups = $faqGroups ?? [];
        $searchQuery = $searchQuery ?? '';
        $contactHref = route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.help.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.help.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.help.subtitle', ['name' => $communityName]) }}</p>

            <form method="get" action="{{ route('govuk-alpha.help', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.help.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.help.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="q-hint">
                </div>
                <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('govuk_alpha.help.search_button') }}</button>
            </form>

            @if (empty($faqGroups))
                <div class="govuk-inset-text">
                    {{ $searchQuery !== '' ? __('govuk_alpha.help.no_results') : __('govuk_alpha.help.empty') }}
                </div>
            @else
                @foreach ($faqGroups as $groupIndex => $group)
                    @if (! empty($group['faqs']))
                        <h2 class="govuk-heading-m">{{ $group['category'] ?? __('govuk_alpha.help.category_label') }}</h2>
                        <div class="govuk-accordion" data-module="govuk-accordion" id="help-accordion-{{ $groupIndex }}">
                            @foreach ($group['faqs'] as $faqIndex => $faq)
                                <div class="govuk-accordion__section">
                                    <div class="govuk-accordion__section-header">
                                        <h3 class="govuk-accordion__section-heading">
                                            <span class="govuk-accordion__section-button" id="help-{{ $groupIndex }}-{{ $faqIndex }}-heading">{{ $faq['question'] ?? '' }}</span>
                                        </h3>
                                    </div>
                                    <div id="help-{{ $groupIndex }}-{{ $faqIndex }}-content" class="govuk-accordion__section-content">
                                        {{-- FAQ answers are admin-authored rich HTML (managed in the admin
                                             panel, same trust model as blog/legal content). They are
                                             sanitized again at render time as defence-in-depth. --}}
                                        <div class="govuk-body">{!! \App\Helpers\HtmlSanitizer::sanitizeCms((string) ($faq['answer'] ?? '')) !!}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.help.contact_cta_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.help.contact_cta_body') }}</p>
            <a class="govuk-button" data-module="govuk-button" href="{{ $contactHref }}">{{ __('govuk_alpha.help.contact_cta_button') }}</a>
        </div>
    </div>
@endsection
