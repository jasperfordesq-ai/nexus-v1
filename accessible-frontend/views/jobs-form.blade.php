{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $mode = $formMode ?? 'create';
        $jf = $jobForm ?? [];
        $val = fn (string $key, string $default = '') => (string) old($key, $jf[$key] ?? $default);
        $remoteDefault = !empty($jf['is_remote']) ? '1' : '0';
        $negotiableDefault = !empty($jf['salary_negotiable']) ? '1' : '0';
        $deadlineVal = (string) old('deadline', isset($jf['deadline']) ? substr((string) $jf['deadline'], 0, 10) : '');
        $errors = $jobFormErrors ?? [];
    @endphp

    <a href="{{ route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.jobs_t3.nav_mine') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs_t3.create_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ $mode === 'edit' ? __('govuk_alpha.jobs_t3.edit_title') : __('govuk_alpha.jobs_t3.create_title') }}</h1>
    <p class="govuk-body-l">{{ $mode === 'edit' ? __('govuk_alpha.jobs_t3.edit_description') : __('govuk_alpha.jobs_t3.create_description') }}</p>

    @if (!empty($errors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors as $err)
                            <li><a href="#title">{{ $err }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="post" action="{{ $formAction }}">
        @csrf

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="title">{{ __('govuk_alpha.jobs_t3.label_title') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t3.hint_title') }}</div>
            <input class="govuk-input" id="title" name="title" type="text" value="{{ $val('title') }}" maxlength="255" aria-describedby="title-hint" required>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.jobs_t3.label_description') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t3.hint_description') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="6" maxlength="5000" aria-describedby="description-hint">{{ $val('description') }}</textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="type">{{ __('govuk_alpha.jobs_t3.label_type') }}</label>
            <select class="govuk-select" id="type" name="type">
                @foreach (['volunteer' => __('govuk_alpha.jobs.type_volunteer'), 'paid' => __('govuk_alpha.jobs.type_paid'), 'timebank' => __('govuk_alpha.jobs.type_timebank')] as $tv => $tl)
                    <option value="{{ $tv }}" @selected($val('type', 'volunteer') === $tv)>{{ $tl }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="commitment">{{ __('govuk_alpha.jobs_t3.label_commitment') }}</label>
            <select class="govuk-select" id="commitment" name="commitment">
                @foreach (['flexible' => __('govuk_alpha.jobs_t2.commitment_flexible'), 'full_time' => __('govuk_alpha.jobs_t2.commitment_full_time'), 'part_time' => __('govuk_alpha.jobs_t2.commitment_part_time'), 'one_off' => __('govuk_alpha.jobs_t2.commitment_one_off')] as $cv => $cl)
                    <option value="{{ $cv }}" @selected($val('commitment', 'flexible') === $cv)>{{ $cl }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="category">{{ __('govuk_alpha.jobs_t3.label_category') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="category" name="category" type="text" value="{{ $val('category') }}" maxlength="100">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="location">{{ __('govuk_alpha.jobs_t3.label_location') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="location" name="location" type="text" value="{{ $val('location') }}" maxlength="255">
        </div>

        <div class="govuk-form-group">
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input type="hidden" name="is_remote" value="0">
                    <input class="govuk-checkboxes__input" id="is_remote" name="is_remote" type="checkbox" value="1" @checked(old('is_remote', $remoteDefault) === '1')>
                    <label class="govuk-label govuk-checkboxes__label" for="is_remote">{{ __('govuk_alpha.jobs_t3.label_remote') }}</label>
                </div>
            </div>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="skills_required">{{ __('govuk_alpha.jobs_t3.label_skills') }}</label>
            <div id="skills-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t3.hint_skills') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="skills_required" name="skills_required" type="text" value="{{ $val('skills_required') }}" maxlength="500" aria-describedby="skills-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="deadline">{{ __('govuk_alpha.jobs_t3.label_deadline') }}</label>
            <div id="deadline-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t3.hint_deadline') }}</div>
            <input class="govuk-input govuk-input--width-10" id="deadline" name="deadline" type="date" value="{{ $deadlineVal }}" aria-describedby="deadline-hint">
        </div>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.jobs.salary_label') }}</legend>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="salary_min">{{ __('govuk_alpha.jobs_t3.label_salary_min') }}</label>
                        <input class="govuk-input" id="salary_min" name="salary_min" type="number" min="0" step="any" value="{{ $val('salary_min') }}" inputmode="decimal">
                    </div>
                </div>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="salary_max">{{ __('govuk_alpha.jobs_t3.label_salary_max') }}</label>
                        <input class="govuk-input" id="salary_max" name="salary_max" type="number" min="0" step="any" value="{{ $val('salary_max') }}" inputmode="decimal">
                    </div>
                </div>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="salary_currency">{{ __('govuk_alpha.jobs_t3.label_salary_currency') }}</label>
                        <div id="currency-hint" class="govuk-hint">{{ __('govuk_alpha.jobs_t3.hint_salary_currency') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="salary_currency" name="salary_currency" type="text" value="{{ $val('salary_currency') }}" maxlength="3" aria-describedby="currency-hint">
                    </div>
                </div>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="salary_type">{{ __('govuk_alpha.jobs_t3.label_salary_type') }}</label>
                <select class="govuk-select" id="salary_type" name="salary_type">
                    @foreach (['' => __('govuk_alpha.jobs_t3.salary_type_none'), 'hourly' => __('govuk_alpha.jobs_t3.salary_type_hourly'), 'monthly' => __('govuk_alpha.jobs_t3.salary_type_monthly'), 'annual' => __('govuk_alpha.jobs_t3.salary_type_annual')] as $sv => $sl)
                        <option value="{{ $sv }}" @selected($val('salary_type') === $sv)>{{ $sl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="govuk-form-group">
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input type="hidden" name="salary_negotiable" value="0">
                        <input class="govuk-checkboxes__input" id="salary_negotiable" name="salary_negotiable" type="checkbox" value="1" @checked(old('salary_negotiable', $negotiableDefault) === '1')>
                        <label class="govuk-label govuk-checkboxes__label" for="salary_negotiable">{{ __('govuk_alpha.jobs_t3.label_salary_negotiable') }}</label>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="hours_per_week">{{ __('govuk_alpha.jobs_t3.label_hours') }}</label>
                    <input class="govuk-input govuk-input--width-5" id="hours_per_week" name="hours_per_week" type="number" min="0" step="any" value="{{ $val('hours_per_week') }}" inputmode="decimal">
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="time_credits">{{ __('govuk_alpha.jobs_t3.label_credits') }}</label>
                    <input class="govuk-input govuk-input--width-5" id="time_credits" name="time_credits" type="number" min="0" step="any" value="{{ $val('time_credits') }}" inputmode="decimal">
                </div>
            </div>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="contact_email">{{ __('govuk_alpha.jobs_t3.label_contact_email') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="contact_email" name="contact_email" type="email" value="{{ $val('contact_email') }}" maxlength="255" autocomplete="email">
        </div>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.jobs_t3.label_status') }}</legend>
            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="status-open" name="status" type="radio" value="open" @checked($val('status', 'open') === 'open')>
                    <label class="govuk-label govuk-radios__label" for="status-open">{{ __('govuk_alpha.jobs_t3.status_open_option') }}</label>
                </div>
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="status-draft" name="status" type="radio" value="draft" @checked($val('status', 'open') === 'draft')>
                    <label class="govuk-label govuk-radios__label" for="status-draft">{{ __('govuk_alpha.jobs_t3.status_draft_option') }}</label>
                </div>
            </div>
        </fieldset>

        <button type="submit" class="govuk-button" data-module="govuk-button">{{ $mode === 'edit' ? __('govuk_alpha.jobs_t3.submit_update') : __('govuk_alpha.jobs_t3.submit_create') }}</button>
    </form>
@endsection
