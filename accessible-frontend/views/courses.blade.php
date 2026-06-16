{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $costTag = function ($c): array {
            $cost = (float) ($c['credit_cost'] ?? 0);
            if ($cost > 0) {
                return ['govuk-tag--blue', rtrim(rtrim(number_format($cost, 2), '0'), '.') . ' ' . __('govuk_alpha.courses.credits_label')];
            }
            return ['govuk-tag--green', __('govuk_alpha.courses.free')];
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.courses.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.courses.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.courses.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.courses.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.courses.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $coursesQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($courses))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.courses.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($courses as $c)
                @php
                    $cTitle = trim((string) ($c['title'] ?? '')) ?: __('govuk_alpha.courses.title');
                    [$tagClass, $tagLabel] = $costTag($c);
                    $level = trim((string) ($c['level'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $c['id']]) }}">{{ $cTitle }}</a></h2>
                        <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
                    </div>
                    @if (trim((string) ($c['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit(strip_tags((string) $c['description']), 160) }}</p>
                    @endif
                    @if ($level !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha.courses.level_label') }}: {{ \Illuminate\Support\Facades\Lang::has('govuk_alpha.courses.levels.' . $level) ? __('govuk_alpha.courses.levels.' . $level) : ucfirst($level) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
