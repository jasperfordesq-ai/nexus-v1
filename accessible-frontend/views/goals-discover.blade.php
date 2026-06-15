{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.goals.back_to_goals') }}</a>

    @if ($status === 'buddy-joined')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="disc-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="disc-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.buddy-joined') }}</p></div>
        </div>
    @elseif ($status === 'buddy-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#discover-goals">{{ __('govuk_alpha.goals.states.buddy-failed') }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl" id="discover-goals">{{ __('govuk_alpha.polish_gamify.goals_discover_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.polish_gamify.goals_discover_description') }}</p>

    @if (empty($discoverGoals))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polish_gamify.goals_discover_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($discoverGoals as $g)
                @php
                    $gTitle = trim((string) ($g['title'] ?? '')) ?: __('govuk_alpha.goals.title');
                    $gDesc = trim((string) ($g['description'] ?? ''));
                    $cur = (float) ($g['current_value'] ?? 0);
                    $tgt = (float) ($g['target_value'] ?? 0);
                    $pct = $tgt > 0 ? (int) round(min(100, ($cur / $tgt) * 100)) : 0;
                    $gId = (int) ($g['id'] ?? 0);
                    $user = is_array($g['user'] ?? null) ? $g['user'] : [];
                    $ownerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    if ($ownerName === '') {
                        $ownerName = __('govuk_alpha.goals.a_member');
                    }
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                        <a class="govuk-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ $gTitle }}</a>
                    </h2>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.owned_by', ['name' => $ownerName]) }}</p>
                    @if ($gDesc !== '')
                        <p class="govuk-body-s govuk-!-margin-bottom-1">{{ $gDesc }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.progress_label', ['current' => rtrim(rtrim(number_format($cur, 2), '0'), '.'), 'target' => rtrim(rtrim(number_format($tgt, 2), '0'), '.')]) }}</p>
                    <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%">{{ $pct }}%</progress>
                    @if ($gId > 0)
                        <form method="post" action="{{ route('govuk-alpha.goals.buddy', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" class="govuk-!-margin-top-2">
                            @csrf
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('govuk_alpha.polish_gamify.goals_discover_become_buddy') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
