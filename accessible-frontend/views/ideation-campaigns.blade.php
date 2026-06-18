{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $ideationActiveTab = 'campaigns';
        $ideationIsAdmin = $isAdmin ?? false;
        $successStates = ['campaign-created', 'campaign-updated', 'campaign-deleted'];
        $errorStates = ['campaign-invalid', 'campaign-failed'];
        $campaignStatusOptions = ['draft', 'active', 'completed', 'archived'];
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.campaigns.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_ideation.campaigns.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_ideation.campaigns.intro') }}</p>

    @include('accessible-frontend::partials.ideation-nav')

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="campaign-status-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="campaign-status-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>@if ($status === 'campaign-invalid')<a href="#campaign_title">{{ __('govuk_alpha_ideation.states.' . $status) }}</a>@else{{ __('govuk_alpha_ideation.states.' . $status) }}@endif</li></ul></div>
            </div>
        </div>
    @endif

    @if (empty($campaigns))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.campaigns.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($campaigns as $cam)
                @php
                    $camTitle = trim((string) ($cam['title'] ?? '')) ?: __('govuk_alpha_ideation.campaigns.title');
                    $camCount = (int) ($cam['challenge_count'] ?? 0);
                    $camStatus = (string) ($cam['status'] ?? 'draft');
                    $camCreator = trim((string) ($cam['creator']['name'] ?? ''));
                    [$camTagClass, $camTagLabel] = match ($camStatus) {
                        'active' => ['govuk-tag--green', __('govuk_alpha_ideation.campaigns.status_active')],
                        'completed' => ['govuk-tag--blue', __('govuk_alpha_ideation.campaigns.status_completed')],
                        'archived' => ['govuk-tag--grey', __('govuk_alpha_ideation.campaigns.status_archived')],
                        default => ['govuk-tag--grey', __('govuk_alpha_ideation.campaigns.status_draft')],
                    };
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.ideation.campaign', ['tenantSlug' => $tenantSlug, 'id' => (int) ($cam['id'] ?? 0)]) }}">{{ $camTitle }}</a></h2>
                        <strong class="govuk-tag {{ $camTagClass }}">{{ $camTagLabel }}</strong>
                    </div>
                    @if (trim((string) ($cam['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($cam['description'], 160) }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        {{ trans_choice('govuk_alpha_ideation.campaigns.challenge_count', $camCount, ['count' => $camCount]) }}
                        @if ($camCreator !== '') &middot; {{ __('govuk_alpha_ideation.campaigns.by', ['name' => $camCreator]) }} @endif
                    </p>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Create campaign (admin only) --}}
    @if ($ideationIsAdmin)
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="create">{{ __('govuk_alpha_ideation.campaigns.create_heading') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_ideation.campaigns.create_intro') }}</p>
        <form method="post" action="{{ route('govuk-alpha.ideation.campaigns.store', ['tenantSlug' => $tenantSlug]) }}">
            @csrf
            <div class="govuk-form-group {{ $status === 'campaign-invalid' ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="campaign_title">{{ __('govuk_alpha_ideation.campaigns.title_label') }}</label>
                @if ($status === 'campaign-invalid')
                    <p id="campaign_title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.campaigns.title_required') }}</p>
                @endif
                <input class="govuk-input" id="campaign_title" name="title" type="text" maxlength="255" {{ $status === 'campaign-invalid' ? 'aria-describedby=campaign_title-error' : '' }}>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_description">{{ __('govuk_alpha_ideation.campaigns.description_label') }}</label>
                <textarea class="govuk-textarea" id="campaign_description" name="description" rows="4" maxlength="5000"></textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_cover_image">{{ __('govuk_alpha_ideation.campaigns.cover_image_label') }}</label>
                <input class="govuk-input" id="campaign_cover_image" name="cover_image" type="url" maxlength="500">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_start_date">{{ __('govuk_alpha_ideation.campaigns.start_date_label') }}</label>
                <div id="campaign_start_date-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.campaigns.date_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="campaign_start_date" name="start_date" type="text" aria-describedby="campaign_start_date-hint">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_end_date">{{ __('govuk_alpha_ideation.campaigns.end_date_label') }}</label>
                <div id="campaign_end_date-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.campaigns.date_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="campaign_end_date" name="end_date" type="text" aria-describedby="campaign_end_date-hint">
            </div>
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.campaigns.status_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small govuk-radios--inline" data-module="govuk-radios">
                        @foreach ($campaignStatusOptions as $opt)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="campaign-status-{{ $opt }}" name="campaign_status" type="radio" value="{{ $opt }}"{{ $opt === 'draft' ? ' checked' : '' }}>
                                <label class="govuk-label govuk-radios__label" for="campaign-status-{{ $opt }}">{{ __('govuk_alpha_ideation.campaigns.status_' . $opt) }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_ideation.campaigns.submit_create') }}</button>
        </form>
    @endif
@endsection
