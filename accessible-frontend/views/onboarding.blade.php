{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $total = count($steps);
        $current = $stepIndex + 1;
        $postAction = route('govuk-alpha.onboarding.step.post', ['tenantSlug' => $tenantSlug, 'step' => $step]);
        $stepRequired = collect($steps)->firstWhere('slug', $step)['required'] ?? false;
        $bagInterests = (array) ($bag['interests'] ?? []);
        $bagOffers = (array) ($bag['offers'] ?? []);
        $bagNeeds = (array) ($bag['needs'] ?? []);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.onboarding.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }} — {{ __('govuk_alpha.onboarding.progress', ['current' => $current, 'total' => $total]) }}</span>

            @if (in_array($status, ['bio-too-short', 'avatar-required', 'safeguarding-failed', 'complete-failed', 'avatar-failed'], true))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.onboarding.states.' . $status) }}</li></ul></div></div>
                </div>
            @elseif ($status === 'avatar-saved')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="ob-status">
                    <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="ob-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
                    <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.onboarding.states.avatar-saved') }}</p></div>
                </div>
            @endif

            {{-- ===== WELCOME ===== --}}
            @if ($step === 'welcome')
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.welcome.title') }}</h1>
                <p class="govuk-body-l">{{ __('govuk_alpha.onboarding.welcome.body') }}</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>{{ __('govuk_alpha.onboarding.welcome.benefit_earn') }}</li>
                    <li>{{ __('govuk_alpha.onboarding.welcome.benefit_community') }}</li>
                    <li>{{ __('govuk_alpha.onboarding.welcome.benefit_skills') }}</li>
                </ul>
                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.welcome.start') }}</button>
                </form>

            {{-- ===== PROFILE (avatar + bio) ===== --}}
            @elseif ($step === 'profile')
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.profile.title') }}</h1>
                <p class="govuk-body">{{ __('govuk_alpha.onboarding.profile.hint') }}</p>

                {{-- Avatar upload is its own multipart form (PRG back to this step). --}}
                <form method="post" action="{{ route('govuk-alpha.onboarding.avatar', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" class="govuk-!-margin-bottom-6">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="avatar">{{ __('govuk_alpha.onboarding.profile.avatar_label') }}</label>
                        <div id="avatar-hint" class="govuk-hint">{{ __('govuk_alpha.onboarding.profile.avatar_hint') }}</div>
                        @if (!empty($onboardingUser->avatar_url))
                            <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.onboarding.profile.current_photo') }}</p>
                            <img class="nexus-alpha-avatar nexus-alpha-avatar--large govuk-!-margin-bottom-2" src="{{ $onboardingUser->avatar_url }}" alt="" width="96" height="96">
                        @endif
                        <input class="govuk-file-upload" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="avatar-hint">
                    </div>
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.onboarding.profile.upload_button') }}</button>
                </form>

                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <div class="govuk-form-group {{ $status === 'bio-too-short' ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="bio">{{ __('govuk_alpha.onboarding.profile.bio_label') }}</label>
                        <div id="bio-hint" class="govuk-hint">{{ __('govuk_alpha.onboarding.profile.bio_hint') }}</div>
                        @if ($status === 'bio-too-short')
                            <p id="bio-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha.onboarding.states.bio-too-short') }}</p>
                        @endif
                        <textarea class="govuk-textarea" id="bio" name="bio" rows="4" maxlength="2000" aria-describedby="bio-hint">{{ $onboardingUser->bio ?? '' }}</textarea>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.profile.save_continue') }}</button>
                </form>

            {{-- ===== INTERESTS ===== --}}
            @elseif ($step === 'interests')
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.interests.title') }}</h1>
                <p class="govuk-body">{{ __('govuk_alpha.onboarding.interests.hint') }}</p>
                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha.onboarding.interests.title') }}</legend>
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                @foreach ($categories as $cat)
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="interest-{{ $cat['id'] }}" name="interests[]" type="checkbox" value="{{ $cat['id'] }}" @checked(in_array((int) $cat['id'], array_map('intval', $bagInterests), true))>
                                        <label class="govuk-label govuk-checkboxes__label" for="interest-{{ $cat['id'] }}">{{ $cat['name'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.continue') }}</button>
                </form>

            {{-- ===== SKILLS ===== --}}
            @elseif ($step === 'skills')
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.skills.title') }}</h1>
                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.onboarding.skills.offers_label') }}</legend>
                            <div class="govuk-hint">{{ __('govuk_alpha.onboarding.skills.offers_hint') }}</div>
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                @foreach ($categories as $cat)
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="offer-{{ $cat['id'] }}" name="offers[]" type="checkbox" value="{{ $cat['id'] }}" @checked(in_array((int) $cat['id'], array_map('intval', $bagOffers), true))>
                                        <label class="govuk-label govuk-checkboxes__label" for="offer-{{ $cat['id'] }}">{{ $cat['name'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.onboarding.skills.needs_label') }}</legend>
                            <div class="govuk-hint">{{ __('govuk_alpha.onboarding.skills.needs_hint') }}</div>
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                @foreach ($categories as $cat)
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="need-{{ $cat['id'] }}" name="needs[]" type="checkbox" value="{{ $cat['id'] }}" @checked(in_array((int) $cat['id'], array_map('intval', $bagNeeds), true))>
                                        <label class="govuk-label govuk-checkboxes__label" for="need-{{ $cat['id'] }}">{{ $cat['name'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.continue') }}</button>
                </form>

            {{-- ===== SAFEGUARDING (opt-in, GDPR — never pre-ticked) ===== --}}
            @elseif ($step === 'safeguarding')
                @php
                    $realOptions = collect($safeguardingOptions)->reject(fn ($o) => ($o['option_key'] ?? '') === 'none_apply')->values();
                    $noneOption = collect($safeguardingOptions)->firstWhere('option_key', 'none_apply');
                @endphp
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.safeguarding.title') }}</h1>
                <p class="govuk-body">{{ __('govuk_alpha.onboarding.safeguarding.intro') }}</p>
                <div class="govuk-inset-text">{{ __('govuk_alpha.onboarding.safeguarding.gdpr_notice') }}</div>

                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        @foreach ($realOptions as $opt)
                            @php $oid = (int) ($opt['id'] ?? 0); $otype = (string) ($opt['option_type'] ?? 'checkbox'); @endphp
                            @if ($otype === 'info')
                                <div class="govuk-inset-text govuk-!-margin-top-2">
                                    <p class="govuk-body govuk-!-margin-bottom-0">{{ $opt['label'] ?? '' }}@if (!empty($opt['description'])) — {{ $opt['description'] }}@endif</p>
                                </div>
                            @elseif ($otype === 'select')
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="sg-{{ $oid }}">{{ $opt['label'] ?? '' }}</label>
                                    @if (!empty($opt['description']))<div class="govuk-hint">{{ $opt['description'] }}</div>@endif
                                    <select class="govuk-select" id="sg-{{ $oid }}" name="safeguarding[{{ $oid }}]">
                                        <option value="">{{ __('govuk_alpha.onboarding.skip') }}</option>
                                        @foreach ((array) ($opt['select_options'] ?? []) as $ovKey => $ovLabel)
                                            <option value="{{ is_int($ovKey) ? $ovLabel : $ovKey }}">{{ $ovLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="sg-{{ $oid }}" name="safeguarding[{{ $oid }}]" type="checkbox" value="1">
                                    <label class="govuk-label govuk-checkboxes__label" for="sg-{{ $oid }}">{{ $opt['label'] ?? '' }}</label>
                                    @if (!empty($opt['description']))<div class="govuk-hint">{{ $opt['description'] }}</div>@endif
                                    @if (!empty($opt['help_url']))<div class="govuk-hint"><a class="govuk-link" href="{{ $opt['help_url'] }}" rel="noopener">{{ __('govuk_alpha.help.title') }}</a></div>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if ($noneOption)
                        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--m">
                        <p class="govuk-body govuk-!-font-weight-bold">{{ __('govuk_alpha.onboarding.safeguarding.none_separator') }}</p>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="sg-{{ (int) $noneOption['id'] }}" name="safeguarding[{{ (int) $noneOption['id'] }}]" type="checkbox" value="1">
                                <label class="govuk-label govuk-checkboxes__label" for="sg-{{ (int) $noneOption['id'] }}">{{ $noneOption['label'] ?? __('govuk_alpha.onboarding.safeguarding.none_label') }}</label>
                            </div>
                        </div>
                    @endif

                    <div class="govuk-button-group govuk-!-margin-top-4">
                        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.continue') }}</button>
                        @unless ($stepRequired)
                            <button class="govuk-button govuk-button--secondary" name="skip" value="1" data-module="govuk-button">{{ __('govuk_alpha.onboarding.skip') }}</button>
                        @endunless
                    </div>
                </form>

            {{-- ===== CONFIRM ===== --}}
            @else
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.onboarding.confirm.title') }}</h1>
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.onboarding.confirm.photo_row') }}</dt>
                        <dd class="govuk-summary-list__value">{{ !empty($onboardingUser->avatar_url) ? __('govuk_alpha.onboarding.confirm.photo_added') : __('govuk_alpha.onboarding.confirm.photo_missing') }}</dd>
                        <dd class="govuk-summary-list__actions"><a class="govuk-link" href="{{ route('govuk-alpha.onboarding.step', ['tenantSlug' => $tenantSlug, 'step' => 'profile']) }}">{{ __('govuk_alpha.onboarding.confirm.change') }}</a></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.onboarding.confirm.bio_row') }}</dt>
                        <dd class="govuk-summary-list__value">{{ \Illuminate\Support\Str::limit($onboardingUser->bio ?? '', 120) }}</dd>
                        <dd class="govuk-summary-list__actions"><a class="govuk-link" href="{{ route('govuk-alpha.onboarding.step', ['tenantSlug' => $tenantSlug, 'step' => 'profile']) }}">{{ __('govuk_alpha.onboarding.confirm.change') }}</a></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.onboarding.confirm.interests_row') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.onboarding.count_selected', ['count' => count($bagInterests)]) }}</dd>
                        <dd class="govuk-summary-list__actions"><a class="govuk-link" href="{{ route('govuk-alpha.onboarding.step', ['tenantSlug' => $tenantSlug, 'step' => 'interests']) }}">{{ __('govuk_alpha.onboarding.confirm.change') }}</a></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.onboarding.confirm.skills_offers_row') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.onboarding.count_selected', ['count' => count($bagOffers)]) }}</dd>
                        <dd class="govuk-summary-list__actions"><a class="govuk-link" href="{{ route('govuk-alpha.onboarding.step', ['tenantSlug' => $tenantSlug, 'step' => 'skills']) }}">{{ __('govuk_alpha.onboarding.confirm.change') }}</a></dd>
                    </div>
                </dl>
                <p class="govuk-body">{{ __('govuk_alpha.onboarding.confirm.finish_note') }}</p>
                <form method="post" action="{{ $postAction }}">
                    @csrf
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.onboarding.confirm.complete_button') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
