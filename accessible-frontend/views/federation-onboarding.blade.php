{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $step = (string) ($step ?? 'welcome');
        $stepNumber = (int) ($stepNumber ?? 1);
        $totalSteps = (int) ($totalSteps ?? 4);
        $settings = $settings ?? [];
        $partners = $partners ?? [];
        $statusKey = (string) ($status ?? '');
        $checked = function (string $k) use ($settings): bool {
            return (bool) ($settings[$k] ?? false);
        };
        $reach = (string) ($settings['service_reach'] ?? 'local_only');
        $travelRadius = (int) ($settings['travel_radius_km'] ?? 25);
        $communityName = $tenant['name'] ?? $tenantSlug;
        $progressValue = $totalSteps > 0 ? (int) round(($stepNumber / $totalSteps) * 100) : 0;
        $stepLabels = [
            'welcome' => __('govuk_alpha_federation.onboarding.step_welcome'),
            'privacy' => __('govuk_alpha_federation.onboarding.step_privacy'),
            'communication' => __('govuk_alpha_federation.onboarding.step_communication'),
            'confirm' => __('govuk_alpha_federation.onboarding.step_confirm'),
        ];
        $currentStepLabel = $stepLabels[$step] ?? '';
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_federation.onboarding.back_link') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha_federation.onboarding.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_federation.onboarding.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha_federation.onboarding.subtitle') }}</p>

            @include('accessible-frontend::partials.federation-nav')

            @if ($statusKey === 'unavailable' || $statusKey === 'optin-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_federation.onboarding.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>
                                    <a href="#fed-onboarding-form">
                                        @if ($statusKey === 'unavailable')
                                            {{ __('govuk_alpha_federation.onboarding.unavailable') }}
                                        @else
                                            {{ __('govuk_alpha_federation.onboarding.optin_failed') }}
                                        @endif
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Progress indicator (native <progress>, never colour-only) --}}
            <div class="govuk-!-margin-bottom-6">
                <p class="govuk-body-s govuk-!-margin-bottom-1">
                    <strong>{{ __('govuk_alpha_federation.onboarding.step_progress', ['current' => $stepNumber, 'total' => $totalSteps]) }}</strong>
                    @if ($currentStepLabel !== '')
                        <span class="nexus-alpha-meta">&mdash; {{ $currentStepLabel }}</span>
                    @endif
                </p>
                <progress class="nexus-alpha-progress" value="{{ $progressValue }}" max="100" aria-label="{{ __('govuk_alpha_federation.onboarding.progress_aria', ['current' => $stepNumber, 'total' => $totalSteps]) }}">{{ $progressValue }}%</progress>
            </div>

            {{-- ─── Step 1: Welcome ─── --}}
            @if ($step === 'welcome')
                <h2 class="govuk-heading-l">{{ __('govuk_alpha_federation.onboarding.welcome_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha_federation.onboarding.welcome_description') }}</p>

                <div class="govuk-grid-row govuk-!-margin-top-4 govuk-!-margin-bottom-4">
                    <div class="govuk-grid-column-one-third">
                        <div class="nexus-alpha-card">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_federation.onboarding.benefit_discover_title') }}</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha_federation.onboarding.benefit_discover_body') }}</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-third">
                        <div class="nexus-alpha-card">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_federation.onboarding.benefit_meet_title') }}</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha_federation.onboarding.benefit_meet_body') }}</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-third">
                        <div class="nexus-alpha-card">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_federation.onboarding.benefit_exchange_title') }}</h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha_federation.onboarding.benefit_exchange_body') }}</p>
                        </div>
                    </div>
                </div>

                <form id="fed-onboarding-form" method="post" action="{{ route('govuk-alpha.federation.onboarding.store', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="step" value="welcome">
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_federation.onboarding.get_started') }}</button>
                        <a class="govuk-link" href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_federation.onboarding.do_this_later') }}</a>
                    </div>
                </form>

            {{-- ─── Step 2: Privacy ─── --}}
            @elseif ($step === 'privacy')
                <form id="fed-onboarding-form" method="post" action="{{ route('govuk-alpha.federation.onboarding.store', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="step" value="privacy">

                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-4" aria-describedby="privacy-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_federation.onboarding.privacy_heading') }}</h2>
                        </legend>
                        <div id="privacy-hint" class="govuk-hint">{{ __('govuk_alpha_federation.onboarding.privacy_description') }}</div>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="profile_visible_federated" name="profile_visible_federated" type="checkbox" value="1" @checked($checked('profile_visible_federated')) aria-describedby="profile_visible_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="profile_visible_federated">{{ __('govuk_alpha_federation.onboarding.toggle_profile_visible') }}</label>
                                <div id="profile_visible_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_profile_visible_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="appear_in_federated_search" name="appear_in_federated_search" type="checkbox" value="1" @checked($checked('appear_in_federated_search')) aria-describedby="appear_in_federated_search-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="appear_in_federated_search">{{ __('govuk_alpha_federation.onboarding.toggle_search_visible') }}</label>
                                <div id="appear_in_federated_search-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_search_visible_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_skills_federated" name="show_skills_federated" type="checkbox" value="1" @checked($checked('show_skills_federated')) aria-describedby="show_skills_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="show_skills_federated">{{ __('govuk_alpha_federation.onboarding.toggle_skills_shared') }}</label>
                                <div id="show_skills_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_skills_shared_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_location_federated" name="show_location_federated" type="checkbox" value="1" @checked($checked('show_location_federated')) aria-describedby="show_location_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="show_location_federated">{{ __('govuk_alpha_federation.onboarding.toggle_location_shared') }}</label>
                                <div id="show_location_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_location_shared_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_reviews_federated" name="show_reviews_federated" type="checkbox" value="1" @checked($checked('show_reviews_federated')) aria-describedby="show_reviews_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="show_reviews_federated">{{ __('govuk_alpha_federation.onboarding.toggle_reviews_visible') }}</label>
                                <div id="show_reviews_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_reviews_visible_desc') }}</div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="govuk-button-group">
                        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.federation.onboarding', ['tenantSlug' => $tenantSlug, 'step' => 'welcome']) }}">{{ __('govuk_alpha_federation.onboarding.back') }}</a>
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_federation.onboarding.next') }}</button>
                    </div>
                </form>

            {{-- ─── Step 3: Communication ─── --}}
            @elseif ($step === 'communication')
                <form id="fed-onboarding-form" method="post" action="{{ route('govuk-alpha.federation.onboarding.store', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="step" value="communication">

                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-4" aria-describedby="communication-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_federation.onboarding.communication_heading') }}</h2>
                        </legend>
                        <div id="communication-hint" class="govuk-hint">{{ __('govuk_alpha_federation.onboarding.communication_description') }}</div>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="messaging_enabled_federated" name="messaging_enabled_federated" type="checkbox" value="1" @checked($checked('messaging_enabled_federated')) aria-describedby="messaging_enabled_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="messaging_enabled_federated">{{ __('govuk_alpha_federation.onboarding.toggle_messaging') }}</label>
                                <div id="messaging_enabled_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_messaging_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="transactions_enabled_federated" name="transactions_enabled_federated" type="checkbox" value="1" @checked($checked('transactions_enabled_federated')) aria-describedby="transactions_enabled_federated-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="transactions_enabled_federated">{{ __('govuk_alpha_federation.onboarding.toggle_transactions') }}</label>
                                <div id="transactions_enabled_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_transactions_desc') }}</div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="email_notifications" name="email_notifications" type="checkbox" value="1" @checked($checked('email_notifications')) aria-describedby="email_notifications-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="email_notifications">{{ __('govuk_alpha_federation.onboarding.toggle_email_notifications') }}</label>
                                <div id="email_notifications-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_federation.onboarding.toggle_email_notifications_desc') }}</div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-4" aria-describedby="reach-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_federation.onboarding.reach_heading') }}</h2>
                        </legend>
                        <div id="reach-hint" class="govuk-hint">{{ __('govuk_alpha_federation.onboarding.reach_description') }}</div>

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="service_reach">{{ __('govuk_alpha_federation.onboarding.reach_label') }}</label>
                            <select class="govuk-select" id="service_reach" name="service_reach">
                                <option value="local_only" @selected($reach === 'local_only')>{{ __('govuk_alpha_federation.onboarding.reach_local_only') }}</option>
                                <option value="remote_ok" @selected($reach === 'remote_ok')>{{ __('govuk_alpha_federation.onboarding.reach_remote_ok') }}</option>
                                <option value="travel_ok" @selected($reach === 'travel_ok')>{{ __('govuk_alpha_federation.onboarding.reach_travel_ok') }}</option>
                            </select>
                        </div>

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="travel_radius_km">{{ __('govuk_alpha_federation.onboarding.travel_radius_label') }}</label>
                            <div id="travel_radius_km-hint" class="govuk-hint">{{ __('govuk_alpha_federation.onboarding.travel_radius_hint') }}</div>
                            <div class="govuk-input__wrapper">
                                <input class="govuk-input govuk-input--width-5" id="travel_radius_km" name="travel_radius_km" type="number" min="0" max="500" step="1" value="{{ $travelRadius }}" aria-describedby="travel_radius_km-hint" spellcheck="false">
                                <div class="govuk-input__suffix" aria-hidden="true">{{ __('govuk_alpha_federation.onboarding.travel_radius_suffix') }}</div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="govuk-button-group">
                        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.federation.onboarding', ['tenantSlug' => $tenantSlug, 'step' => 'privacy']) }}">{{ __('govuk_alpha_federation.onboarding.back') }}</a>
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_federation.onboarding.next') }}</button>
                    </div>
                </form>

            {{-- ─── Step 4: Confirm ─── --}}
            @else
                <h2 class="govuk-heading-l">{{ __('govuk_alpha_federation.onboarding.confirm_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha_federation.onboarding.confirm_description') }}</p>

                {{-- Privacy summary --}}
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_federation.onboarding.summary_privacy') }}</h3>
                <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                    @php
                        $privacySummary = [
                            'summary_profile_visible' => 'profile_visible_federated',
                            'summary_in_search' => 'appear_in_federated_search',
                            'summary_skills_shared' => 'show_skills_federated',
                            'summary_location_shared' => 'show_location_federated',
                            'summary_reviews_visible' => 'show_reviews_federated',
                        ];
                    @endphp
                    @foreach ($privacySummary as $labelKey => $settingKey)
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_federation.onboarding.' . $labelKey) }}</dt>
                            <dd class="govuk-summary-list__value">
                                @if ($checked($settingKey))
                                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_federation.onboarding.summary_on') }}</strong>
                                @else
                                    <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_federation.onboarding.summary_off') }}</strong>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Communication summary --}}
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_federation.onboarding.summary_communication') }}</h3>
                <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                    @php
                        $commSummary = [
                            'summary_messaging' => 'messaging_enabled_federated',
                            'summary_transactions' => 'transactions_enabled_federated',
                            'summary_email_alerts' => 'email_notifications',
                        ];
                    @endphp
                    @foreach ($commSummary as $labelKey => $settingKey)
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_federation.onboarding.' . $labelKey) }}</dt>
                            <dd class="govuk-summary-list__value">
                                @if ($checked($settingKey))
                                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_federation.onboarding.summary_on') }}</strong>
                                @else
                                    <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_federation.onboarding.summary_off') }}</strong>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Service reach summary --}}
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_federation.onboarding.summary_reach') }}</h3>
                <p class="govuk-body govuk-!-margin-bottom-6">
                    @if ($reach === 'travel_ok')
                        {{ __('govuk_alpha_federation.onboarding.reach_summary_travel_ok', ['km' => $travelRadius]) }}
                    @elseif ($reach === 'remote_ok')
                        {{ __('govuk_alpha_federation.onboarding.reach_summary_remote_ok') }}
                    @else
                        {{ __('govuk_alpha_federation.onboarding.reach_summary_local_only') }}
                    @endif
                </p>

                {{-- Partner preview --}}
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_federation.onboarding.partners_heading') }}</h3>
                @if (empty($partners))
                    <div class="govuk-inset-text"><p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_federation.onboarding.partners_empty') }}</p></div>
                @else
                    <p class="govuk-body">{{ __('govuk_alpha_federation.onboarding.partners_available') }}</p>
                    <div class="nexus-alpha-card-list govuk-!-margin-bottom-4">
                        @foreach (array_slice($partners, 0, 5) as $partner)
                            @php
                                $pName = trim((string) ($partner['name'] ?? '')) ?: $tenantSlug;
                                $pLoc = trim((string) ($partner['location'] ?? ''));
                                $pCount = (int) ($partner['member_count'] ?? 0);
                            @endphp
                            <article class="nexus-alpha-card">
                                <h4 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $pName }}</h4>
                                @if ($pLoc !== '')
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_federation.onboarding.partners_location_label') }}: {{ $pLoc }}</p>
                                @endif
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha_federation.onboarding.partners_members_label') }}: {{ $pCount }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif

                {{-- Credit-moving / data-sharing confirm warning --}}
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                        {{ __('govuk_alpha_federation.onboarding.confirm_warning') }}
                    </strong>
                </div>

                <form id="fed-onboarding-form" method="post" action="{{ route('govuk-alpha.federation.onboarding.store', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="step" value="confirm">
                    <div class="govuk-button-group">
                        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.federation.onboarding', ['tenantSlug' => $tenantSlug, 'step' => 'communication']) }}">{{ __('govuk_alpha_federation.onboarding.back') }}</a>
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_federation.onboarding.enable_federation') }}</button>
                        <a class="govuk-link" href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_federation.onboarding.do_this_later') }}</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
