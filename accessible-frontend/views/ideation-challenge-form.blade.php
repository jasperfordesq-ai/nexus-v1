{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $isEdit = ($mode ?? 'create') === 'edit';
        $c = $challenge ?? [];
        $cId = (int) ($c['id'] ?? 0);
        $formAction = $isEdit
            ? route('govuk-alpha.ideation.update', ['tenantSlug' => $tenantSlug, 'id' => $cId])
            : route('govuk-alpha.ideation.store', ['tenantSlug' => $tenantSlug]);
        $backHref = $isEdit
            ? route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $cId])
            : route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]);
        $existingTags = is_array($c['tags'] ?? null) ? implode(', ', $c['tags']) : '';
        $currentCategoryId = (int) ($c['category_id'] ?? 0);
        $statusOptions = ['draft', 'open', 'voting', 'evaluating', 'closed', 'archived'];
        $currentStatus = (string) ($c['status'] ?? 'draft');
        $isInvalid = $status === 'challenge-invalid';
    @endphp

    <a href="{{ $backHref }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.form.cancel') }}</a>

    @if ($status === 'challenge-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.challenge-failed') }}</li></ul></div>
            </div>
        </div>
    @elseif ($isInvalid)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#title">{{ __('govuk_alpha_ideation.form.title_required') }}</a></li>
                        <li><a href="#description">{{ __('govuk_alpha_ideation.form.description_required') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.nav.heading') }}</span>
    <h1 class="govuk-heading-xl">{{ $isEdit ? __('govuk_alpha_ideation.form.edit_title') : __('govuk_alpha_ideation.form.create_title') }}</h1>
    <p class="govuk-body-l">{{ $isEdit ? __('govuk_alpha_ideation.form.edit_intro') : __('govuk_alpha_ideation.form.create_intro') }}</p>

    {{-- Template picker (create only) --}}
    @if (! $isEdit && ! empty($templates))
        <details class="govuk-details" data-module="govuk-details">
            <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_ideation.form.template_heading') }}</span></summary>
            <div class="govuk-details__text">
                <p class="govuk-body">{{ __('govuk_alpha_ideation.form.template_intro') }}</p>
                <ul class="govuk-list">
                    @foreach ($templates as $tpl)
                        <li class="govuk-!-margin-bottom-1"><strong>{{ trim((string) ($tpl['title'] ?? '')) }}</strong>
                            @if (trim((string) ($tpl['description'] ?? '')) !== '')<span class="govuk-body-s nexus-alpha-meta">— {{ \Illuminate\Support\Str::limit($tpl['description'], 120) }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>
    @endif

    <form method="post" action="{{ $formAction }}">
        @csrf
        <div class="govuk-form-group {{ $isInvalid ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="title">{{ __('govuk_alpha_ideation.form.title_label') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.title_hint') }}</div>
            @if ($isInvalid)
                <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.form.title_required') }}</p>
            @endif
            <input class="govuk-input" id="title" name="title" type="text" maxlength="255" value="{{ trim((string) ($c['title'] ?? '')) }}" aria-describedby="title-hint {{ $isInvalid ? 'title-error' : '' }}">
        </div>

        <div class="govuk-form-group {{ $isInvalid ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="description">{{ __('govuk_alpha_ideation.form.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.description_hint') }}</div>
            @if ($isInvalid)
                <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.form.description_required') }}</p>
            @endif
            <textarea class="govuk-textarea" id="description" name="description" rows="6" maxlength="5000" aria-describedby="description-hint {{ $isInvalid ? 'description-error' : '' }}">{{ trim((string) ($c['description'] ?? '')) }}</textarea>
        </div>

        @if (! empty($categories))
            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">{{ __('govuk_alpha_ideation.form.category_label') }}</label>
                <select class="govuk-select" id="category_id" name="category_id">
                    <option value="">{{ __('govuk_alpha_ideation.form.category_none') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ (int) ($cat['id'] ?? 0) }}"{{ $currentCategoryId === (int) ($cat['id'] ?? 0) ? ' selected' : '' }}>{{ trim((string) ($cat['name'] ?? '')) }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="govuk-form-group">
            <label class="govuk-label" for="category">{{ __('govuk_alpha_ideation.form.category_text_label') }}</label>
            <div id="category-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.category_text_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="category" name="category" type="text" maxlength="100" value="{{ trim((string) ($c['category'] ?? '')) }}" aria-describedby="category-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="prize_description">{{ __('govuk_alpha_ideation.form.prize_label') }}</label>
            <textarea class="govuk-textarea" id="prize_description" name="prize_description" rows="3" maxlength="2000">{{ trim((string) ($c['prize_description'] ?? '')) }}</textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="submission_deadline">{{ __('govuk_alpha_ideation.form.submission_deadline_label') }}</label>
            <div id="submission_deadline-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.deadline_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="submission_deadline" name="submission_deadline" type="text" value="{{ trim((string) ($c['submission_deadline'] ?? '')) }}" aria-describedby="submission_deadline-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="voting_deadline">{{ __('govuk_alpha_ideation.form.voting_deadline_label') }}</label>
            <div id="voting_deadline-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.deadline_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="voting_deadline" name="voting_deadline" type="text" value="{{ trim((string) ($c['voting_deadline'] ?? '')) }}" aria-describedby="voting_deadline-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="max_ideas_per_user">{{ __('govuk_alpha_ideation.form.max_ideas_label') }}</label>
            <div id="max_ideas_per_user-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.max_ideas_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="max_ideas_per_user" name="max_ideas_per_user" type="text" inputmode="numeric" value="{{ ($c['max_ideas_per_user'] ?? null) !== null ? (int) $c['max_ideas_per_user'] : '' }}" aria-describedby="max_ideas_per_user-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="cover_image">{{ __('govuk_alpha_ideation.form.cover_image_label') }}</label>
            <input class="govuk-input" id="cover_image" name="cover_image" type="url" maxlength="500" value="{{ trim((string) ($c['cover_image'] ?? '')) }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="tags">{{ __('govuk_alpha_ideation.form.tags_label') }}</label>
            <div id="tags-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.form.tags_hint') }}</div>
            <input class="govuk-input" id="tags" name="tags" type="text" value="{{ $existingTags }}" aria-describedby="tags-hint">
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.form.status_label') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach ($statusOptions as $opt)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="challenge-status-{{ $opt }}" name="challenge_status" type="radio" value="{{ $opt }}"{{ $currentStatus === $opt ? ' checked' : '' }}>
                            <label class="govuk-label govuk-radios__label" for="challenge-status-{{ $opt }}">{{ __('govuk_alpha_ideation.status.' . $opt) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-button-group">
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ $isEdit ? __('govuk_alpha_ideation.form.submit_edit') : __('govuk_alpha_ideation.form.submit_create') }}</button>
            <a class="govuk-link" href="{{ $backHref }}">{{ __('govuk_alpha_ideation.form.cancel') }}</a>
        </div>
    </form>
@endsection
