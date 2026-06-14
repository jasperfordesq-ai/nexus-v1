{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $typeTag = function ($t): string {
            return match ((string) $t) {
                'paid' => __('govuk_alpha.jobs.type_paid'),
                'timebank' => __('govuk_alpha.jobs.type_timebank'),
                default => __('govuk_alpha.jobs.type_volunteer'),
            };
        };
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.jobs.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.jobs.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $jobsQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($jobs))
        <p class="govuk-inset-text">{{ __('govuk_alpha.jobs.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($jobs as $j)
                @php
                    $jTitle = trim((string) ($j['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
                    $deadline = $dateFmt($j['deadline'] ?? ($j['closing_date'] ?? null));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $j['id']]) }}">{{ $jTitle }}</a></h2>
                        <strong class="govuk-tag govuk-tag--blue">{{ $typeTag($j['type'] ?? 'volunteer') }}</strong>
                    </div>
                    @if ($deadline)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha.jobs.deadline', ['date' => $deadline]) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
