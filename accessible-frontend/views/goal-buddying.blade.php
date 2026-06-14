{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.goals.back_to_goals') }}</a>

    @php
        $ownerName = function ($g): string {
            $u = $g['user'] ?? null;
            if (is_array($u)) {
                $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
            return __('govuk_alpha.goals.a_member');
        };
        $pct = function ($g): int {
            $cur = (float) ($g['current_value'] ?? 0);
            $tgt = (float) ($g['target_value'] ?? 0);
            return $tgt > 0 ? (int) round(min(100, ($cur / $tgt) * 100)) : 0;
        };
    @endphp

    @if ($status === 'buddy-joined')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="buddy-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="buddy-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.buddy-joined') }}</p></div>
        </div>
    @elseif ($status === 'buddy-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#available-goals">{{ __('govuk_alpha.goals.states.buddy-failed') }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.buddying_caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.goals.buddying_title') }}</h1>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.goals.buddying_yours_heading') }}</h2>
    @if (empty($buddying))
        <p class="govuk-inset-text">{{ __('govuk_alpha.goals.buddying_yours_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-8">
            @foreach ($buddying as $g)
                @php
                    $gTitle = trim((string) ($g['title'] ?? '')) ?: __('govuk_alpha.goals.title');
                    $p = $pct($g);
                    $done = in_array($g['status'] ?? 'active', ['completed', 'achieved'], true);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $g['id']]) }}">{{ $gTitle }}</a></h3>
                        <strong class="govuk-tag {{ $done ? 'govuk-tag--green' : 'govuk-tag--blue' }}">{{ $done ? __('govuk_alpha.goals.status_completed') : __('govuk_alpha.goals.status_active') }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.owned_by', ['name' => $ownerName($g)]) }}</p>
                    <progress max="100" value="{{ $p }}" aria-label="{{ $p }}%">{{ $p }}%</progress>
                </article>
            @endforeach
        </div>
    @endif

    <h2 class="govuk-heading-l" id="available-goals">{{ __('govuk_alpha.goals.buddying_available_heading') }}</h2>
    @if (empty($available))
        <p class="govuk-inset-text">{{ __('govuk_alpha.goals.buddying_available_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($available as $g)
                @php
                    $gTitle = trim((string) ($g['title'] ?? '')) ?: __('govuk_alpha.goals.title');
                    $p = $pct($g);
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $g['id']]) }}">{{ $gTitle }}</a></h3>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.owned_by', ['name' => $ownerName($g)]) }}</p>
                    <progress max="100" value="{{ $p }}" aria-label="{{ $p }}%">{{ $p }}%</progress>
                    <form method="post" action="{{ route('govuk-alpha.goals.buddy', ['tenantSlug' => $tenantSlug, 'id' => $g['id']]) }}" class="govuk-!-margin-top-2">
                        @csrf
                        <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.become_buddy_button') }}</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
