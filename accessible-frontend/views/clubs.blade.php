{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://']) ? $p : 'https://' . ltrim($p, '/'));
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.clubs.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.clubs.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.clubs.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.clubs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.clubs.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.clubs.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $clubsQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($clubs))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.clubs.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($clubs as $club)
                @php
                    $name = trim((string) ($club['name'] ?? '')) ?: __('govuk_alpha.clubs.title');
                    $members = (int) ($club['member_count'] ?? 0);
                    $schedule = trim((string) ($club['meeting_schedule'] ?? ''));
                    $email = trim((string) ($club['contact_email'] ?? ''));
                    $website = trim((string) ($club['website'] ?? ''));
                    $logo = trim((string) ($club['logo_url'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        @if ($logo !== '')
                            <img class="nexus-alpha-avatar" src="{{ $logo }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                        @endif
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $name }}</h2>
                        <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.clubs.members_count', ['count' => $members]) }}</strong>
                    </div>
                    @if (trim((string) ($club['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($club['description'], 200) }}</p>
                    @endif
                    <dl class="nexus-alpha-inline-list">
                        @if ($schedule !== '')
                            <div><dt>{{ __('govuk_alpha.clubs.schedule_label') }}</dt><dd>{{ $schedule }}</dd></div>
                        @endif
                        @if ($email !== '')
                            <div><dt>{{ __('govuk_alpha.clubs.contact_label') }}</dt><dd><a class="govuk-link" href="mailto:{{ $email }}">{{ $email }}</a></dd></div>
                        @endif
                    </dl>
                    @if ($website !== '')
                        <p class="govuk-body govuk-!-margin-bottom-0"><a class="govuk-link" href="{{ $asUrl($website) }}" rel="noopener noreferrer" target="_blank">{{ __('govuk_alpha.clubs.visit_website') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a></p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
