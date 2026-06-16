{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $typeLabel = function (?string $t): string {
            return match ((string) $t) {
                'paid' => __('govuk_alpha.jobs.type_paid'),
                'timebank' => __('govuk_alpha.jobs.type_timebank'),
                'volunteer' => __('govuk_alpha.jobs.type_volunteer'),
                default => '',
            };
        };
        $commitmentLabel = function (?string $c): string {
            return match ((string) $c) {
                'full_time' => __('govuk_alpha.jobs_t2.commitment_full_time'),
                'part_time' => __('govuk_alpha.jobs_t2.commitment_part_time'),
                'flexible' => __('govuk_alpha.jobs_t2.commitment_flexible'),
                'one_off' => __('govuk_alpha.jobs_t2.commitment_one_off'),
                default => '',
            };
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs_t4.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs_t4.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs_t4.description') }}</p>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'alerts'])

    @php $st = $status ?? null; @endphp
    @if (in_array($st, ['alert-created', 'alert-paused', 'alert-resumed', 'alert-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="alert-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="alert-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t4.states.' . $st) }}</p></div>
        </div>
    @elseif ($st === 'alert-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.jobs_t4.states.alert-failed') }}</li></ul></div></div>
        </div>
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.jobs_t4.create_heading') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.jobs.alerts.subscribe', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-8">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label" for="keywords">{{ __('govuk_alpha.jobs_t4.label_keywords') }}</label>
            <div id="keywords-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t4.hint_keywords') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="keywords" name="keywords" type="text" maxlength="500" aria-describedby="keywords-hint">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label" for="categories">{{ __('govuk_alpha.jobs_t4.label_categories') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="categories" name="categories" type="text" maxlength="500">
        </div>
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="type">{{ __('govuk_alpha.jobs_t4.label_type') }}</label>
                    <select class="govuk-select" id="type" name="type">
                        <option value="">{{ __('govuk_alpha.jobs_t4.type_any') }}</option>
                        <option value="volunteer">{{ __('govuk_alpha.jobs.type_volunteer') }}</option>
                        <option value="paid">{{ __('govuk_alpha.jobs.type_paid') }}</option>
                        <option value="timebank">{{ __('govuk_alpha.jobs.type_timebank') }}</option>
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="commitment">{{ __('govuk_alpha.jobs_t4.label_commitment') }}</label>
                    <select class="govuk-select" id="commitment" name="commitment">
                        <option value="">{{ __('govuk_alpha.jobs_t4.commitment_any') }}</option>
                        <option value="full_time">{{ __('govuk_alpha.jobs_t2.commitment_full_time') }}</option>
                        <option value="part_time">{{ __('govuk_alpha.jobs_t2.commitment_part_time') }}</option>
                        <option value="flexible">{{ __('govuk_alpha.jobs_t2.commitment_flexible') }}</option>
                        <option value="one_off">{{ __('govuk_alpha.jobs_t2.commitment_one_off') }}</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label" for="location">{{ __('govuk_alpha.jobs_t4.label_location') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="location" name="location" type="text" maxlength="255">
        </div>
        <div class="govuk-form-group">
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="is_remote_only" name="is_remote_only" type="checkbox" value="1">
                    <label class="govuk-label govuk-checkboxes__label" for="is_remote_only">{{ __('govuk_alpha.jobs_t4.label_remote_only') }}</label>
                </div>
            </div>
        </div>
        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.jobs_t4.subscribe_button') }}</button>
    </form>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.jobs_t4.your_alerts_heading') }}</h2>
    @if (empty($alerts))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.jobs_t4.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($alerts as $alert)
                @php
                    $aId = (int) ($alert['id'] ?? 0);
                    $isActive = (bool) ($alert['is_active'] ?? false);
                    $aKeywords = trim((string) ($alert['keywords'] ?? ''));
                    $aType = $typeLabel($alert['type'] ?? null);
                    $aCommitment = $commitmentLabel($alert['commitment'] ?? null);
                    $aLocation = trim((string) ($alert['location'] ?? ''));
                    $aRemote = (bool) ($alert['is_remote_only'] ?? false);
                    $criteria = [];
                    if ($aKeywords !== '') { $criteria[] = __('govuk_alpha.jobs_t4.criteria_keywords', ['value' => $aKeywords]); }
                    if ($aType !== '') { $criteria[] = __('govuk_alpha.jobs_t4.criteria_type', ['value' => $aType]); }
                    if ($aCommitment !== '') { $criteria[] = __('govuk_alpha.jobs_t4.criteria_commitment', ['value' => $aCommitment]); }
                    if ($aLocation !== '') { $criteria[] = __('govuk_alpha.jobs_t4.criteria_location', ['value' => $aLocation]); }
                    if ($aRemote) { $criteria[] = __('govuk_alpha.jobs_t4.criteria_remote'); }
                    if (empty($criteria)) { $criteria[] = __('govuk_alpha.jobs_t4.criteria_any'); }
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $criteria[0] }}</h3>
                        <strong class="govuk-tag {{ $isActive ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $isActive ? __('govuk_alpha.jobs_t4.active_tag') : __('govuk_alpha.jobs_t4.paused_tag') }}</strong>
                    </div>
                    <ul class="govuk-list govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                        @foreach (array_slice($criteria, 1) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <div class="nexus-alpha-actions">
                        @if ($isActive)
                            <form method="post" action="{{ route('govuk-alpha.jobs.alerts.pause', ['tenantSlug' => $tenantSlug, 'alertId' => $aId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t4.pause_button') }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('govuk-alpha.jobs.alerts.resume', ['tenantSlug' => $tenantSlug, 'alertId' => $aId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t4.resume_button') }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.jobs.alerts.delete', ['tenantSlug' => $tenantSlug, 'alertId' => $aId]) }}" class="nexus-alpha-linkform" onsubmit="return confirm('{{ __('govuk_alpha.jobs_t4.delete_confirm') }}');">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t4.delete_button') }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
