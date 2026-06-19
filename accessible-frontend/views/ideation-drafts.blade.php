{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $challengeId = (int) ($challenge['id'] ?? 0);
        $challengeTitle = trim((string) ($challenge['title'] ?? ''));
        $successStates = ['draft-saved'];
        $errorStates = ['draft-invalid', 'draft-failed'];
        $dateFmt = function ($value): string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->isoFormat('D MMM YYYY');
            } catch (\Throwable $e) {
                return $value;
            }
        };
    @endphp

    <a href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $challengeId]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.drafts.back_to_challenge') }}</a>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="drafts-status-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="drafts-status-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.' . $status) }}</li></ul>
                </div>
            </div>
        </div>
    @endif

    @if ($challengeTitle !== '')
        <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.drafts.caption', ['challenge' => $challengeTitle]) }}</span>
    @endif
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.drafts.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_ideation.drafts.intro') }}</p>

    @if (empty($drafts))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.drafts.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($drafts as $draft)
                @php
                    $draftId = (int) ($draft['id'] ?? 0);
                    $draftTitle = trim((string) ($draft['title'] ?? ''));
                    $draftDescription = trim((string) ($draft['description'] ?? ''));
                    $updatedAt = $dateFmt($draft['updated_at'] ?? '');
                    $createdAt = $dateFmt($draft['created_at'] ?? '');
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $draftTitle !== '' ? $draftTitle : __('govuk_alpha_ideation.drafts.untitled') }}</h2>
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha_ideation.drafts.status') }}</strong>
                    </div>
                    @if ($updatedAt !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.drafts.saved_on', ['date' => $updatedAt]) }}</p>
                    @elseif ($createdAt !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.drafts.created_on', ['date' => $createdAt]) }}</p>
                    @endif

                    <form method="post" action="{{ route('govuk-alpha.ideation.drafts.update', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $draftId]) }}">
                        @csrf
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.drafts.edit_heading') }}</h3>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="draft-title-{{ $draftId }}">{{ __('govuk_alpha_ideation.drafts.title_label') }}</label>
                            <input class="govuk-input" id="draft-title-{{ $draftId }}" name="draft_title" type="text" maxlength="255" value="{{ $draftTitle }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="draft-description-{{ $draftId }}">{{ __('govuk_alpha_ideation.drafts.description_label') }}</label>
                            <div id="draft-description-hint-{{ $draftId }}" class="govuk-hint">{{ __('govuk_alpha_ideation.drafts.description_hint') }}</div>
                            <textarea class="govuk-textarea" id="draft-description-{{ $draftId }}" name="draft_description" rows="5" maxlength="5000" aria-describedby="draft-description-hint-{{ $draftId }}">{{ $draftDescription }}</textarea>
                        </div>
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_title') }}</span>
                                {{ __('govuk_alpha_ideation.drafts.publish_warning') }}
                            </strong>
                        </div>
                        <div class="govuk-button-group">
                            <button class="govuk-button govuk-button--secondary" data-module="govuk-button" name="draft_action" value="save">{{ __('govuk_alpha_ideation.drafts.save') }}</button>
                            <button class="govuk-button" data-module="govuk-button" name="draft_action" value="publish">{{ __('govuk_alpha_ideation.drafts.publish') }}</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
