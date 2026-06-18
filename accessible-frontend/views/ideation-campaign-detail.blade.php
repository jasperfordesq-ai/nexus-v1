{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $camId = (int) ($campaign['id'] ?? 0);
        $camTitle = trim((string) ($campaign['title'] ?? '')) ?: __('govuk_alpha_ideation.campaigns.title');
        $camStatus = (string) ($campaign['status'] ?? 'draft');
        $camCreator = trim((string) ($campaign['creator']['name'] ?? ''));
        $linked = is_array($campaign['challenges'] ?? null) ? $campaign['challenges'] : [];
        $ideationIsAdmin = $isAdmin ?? false;
        $campaignStatusOptions = ['draft', 'active', 'completed', 'archived'];
        [$camTagClass, $camTagLabel] = match ($camStatus) {
            'active' => ['govuk-tag--green', __('govuk_alpha_ideation.campaigns.status_active')],
            'completed' => ['govuk-tag--blue', __('govuk_alpha_ideation.campaigns.status_completed')],
            'archived' => ['govuk-tag--grey', __('govuk_alpha_ideation.campaigns.status_archived')],
            default => ['govuk-tag--grey', __('govuk_alpha_ideation.campaigns.status_draft')],
        };
        $successStates = ['campaign-updated', 'campaign-created', 'challenge-unlinked'];
        $errorStates = ['campaign-invalid', 'campaign-failed'];
    @endphp

    <a href="{{ route('govuk-alpha.ideation.campaigns', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.campaigns.title') }}</a>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="campaign-detail-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="campaign-detail-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.' . $status) }}</li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.campaigns.title') }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $camTitle }}</h1>
        <strong class="govuk-tag {{ $camTagClass }}">{{ $camTagLabel }}</strong>
    </div>
    @if ($camCreator !== '')
        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_ideation.campaigns.by', ['name' => $camCreator]) }}</p>
    @endif
    @if (trim((string) ($campaign['description'] ?? '')) !== '')
        <div class="govuk-body-l">{!! nl2br(e($campaign['description'])) !!}</div>
    @endif

    {{-- Linked challenges --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="challenges">{{ __('govuk_alpha_ideation.campaigns.linked_heading') }}</h2>
    @if (empty($linked))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.campaigns.linked_empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($linked as $ch)
                @php
                    $chTitle = trim((string) ($ch['title'] ?? '')) ?: __('govuk_alpha_ideation.nav.challenges');
                    $chId = (int) ($ch['id'] ?? 0);
                    $chIdeas = (int) ($ch['ideas_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $chId]) }}">{{ $chTitle }}</a></h3>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ trans_choice('govuk_alpha.ideation.ideas_count', $chIdeas, ['count' => $chIdeas]) }}</p>
                    @if ($ideationIsAdmin)
                        <form method="post" action="{{ route('govuk-alpha.ideation.campaign.unlink', ['tenantSlug' => $tenantSlug, 'id' => $camId, 'challengeId' => $chId]) }}">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-label="{{ __('govuk_alpha_ideation.campaigns.unlink_aria', ['title' => $chTitle]) }}">{{ __('govuk_alpha_ideation.campaigns.unlink') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    {{-- Admin: edit + delete --}}
    @if ($ideationIsAdmin)
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="edit">{{ __('govuk_alpha_ideation.campaigns.edit_heading') }}</h2>
        <form method="post" action="{{ route('govuk-alpha.ideation.campaign.update', ['tenantSlug' => $tenantSlug, 'id' => $camId]) }}">
            @csrf
            <div class="govuk-form-group {{ $status === 'campaign-invalid' ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="campaign_title">{{ __('govuk_alpha_ideation.campaigns.title_label') }}</label>
                @if ($status === 'campaign-invalid')
                    <p id="campaign_title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.campaigns.title_required') }}</p>
                @endif
                <input class="govuk-input" id="campaign_title" name="title" type="text" maxlength="255" value="{{ trim((string) ($campaign['title'] ?? '')) }}" {{ $status === 'campaign-invalid' ? 'aria-describedby=campaign_title-error' : '' }}>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_description">{{ __('govuk_alpha_ideation.campaigns.description_label') }}</label>
                <textarea class="govuk-textarea" id="campaign_description" name="description" rows="4" maxlength="5000">{{ trim((string) ($campaign['description'] ?? '')) }}</textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_start_date">{{ __('govuk_alpha_ideation.campaigns.start_date_label') }}</label>
                <input class="govuk-input govuk-input--width-10" id="campaign_start_date" name="start_date" type="text" value="{{ trim((string) ($campaign['start_date'] ?? '')) }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_end_date">{{ __('govuk_alpha_ideation.campaigns.end_date_label') }}</label>
                <input class="govuk-input govuk-input--width-10" id="campaign_end_date" name="end_date" type="text" value="{{ trim((string) ($campaign['end_date'] ?? '')) }}">
            </div>
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.campaigns.status_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small govuk-radios--inline" data-module="govuk-radios">
                        @foreach ($campaignStatusOptions as $opt)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="edit-campaign-status-{{ $opt }}" name="campaign_status" type="radio" value="{{ $opt }}"{{ $camStatus === $opt ? ' checked' : '' }}>
                                <label class="govuk-label govuk-radios__label" for="edit-campaign-status-{{ $opt }}">{{ __('govuk_alpha_ideation.campaigns.status_' . $opt) }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_ideation.campaigns.submit_edit') }}</button>
        </form>

        <details class="govuk-details govuk-!-margin-top-4" data-module="govuk-details">
            <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_ideation.campaigns.delete') }}</span></summary>
            <div class="govuk-details__text">
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span>{{ __('govuk_alpha_ideation.campaigns.delete_warning') }}</strong>
                </div>
                <form method="post" action="{{ route('govuk-alpha.ideation.campaign.delete', ['tenantSlug' => $tenantSlug, 'id' => $camId]) }}">
                    @csrf
                    <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_ideation.campaigns.delete_confirm') }}</button>
                </form>
            </div>
        </details>
    @endif
@endsection
