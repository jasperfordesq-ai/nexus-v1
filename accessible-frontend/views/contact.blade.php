{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $contactUser = $contactUser ?? null;
        $errorBag = $errors ?? new \Illuminate\Support\MessageBag();
        $defaultName = trim((string) ($contactUser['name'] ?? ''));
        if ($defaultName === '') {
            $defaultName = trim(((string) ($contactUser['first_name'] ?? '')) . ' ' . ((string) ($contactUser['last_name'] ?? '')));
        }
        $nameValue = old('name', $defaultName);
        $emailValue = old('email', $contactUser['email'] ?? '');
        $subjectValue = old('subject', '');
        $messageValue = old('message', '');
        $validationFallback = ($status ?? '') === 'contact-validation' && ! $errorBag->any();
        $fieldErrors = [
            'name' => $errorBag->first('name') ?: ($validationFallback ? __('govuk_alpha.contact.errors.name_required') : ''),
            'email' => $errorBag->first('email') ?: ($validationFallback ? __('govuk_alpha.contact.errors.email_required') : ''),
            'message' => $errorBag->first('message') ?: ($validationFallback ? __('govuk_alpha.contact.errors.message_required') : ''),
        ];
        $hasValidationError = ($status ?? '') === 'contact-validation' || $errorBag->any();
        $statusMessages = [
            'contact-failed' => __('govuk_alpha.contact.error_fallback'),
            'contact-rate-limited' => __('govuk_alpha.contact.rate_limited'),
        ];
        $subjectOptions = ['general', 'account', 'technical', 'feedback', 'other'];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.contact.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.contact.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.contact.subtitle', ['name' => $communityName]) }}</p>

            @if (($status ?? '') === 'contact-sent')
                <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="contact-success-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="contact-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.contact.success_title') }}</p>
                        <p class="govuk-body">{{ __('govuk_alpha.contact.success_message') }}</p>
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            <a class="govuk-link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.contact.back_to_home') }}</a>
                        </p>
                    </div>
                </div>
            @endif

            @if ($hasValidationError)
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                @foreach (['name', 'email', 'message'] as $field)
                                    @if ($fieldErrors[$field] !== '')
                                        <li><a href="#{{ $field }}">{{ $fieldErrors[$field] }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @elseif (isset($statusMessages[$status ?? '']))
                <div class="govuk-notification-banner" role="region" aria-labelledby="contact-error-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="contact-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
                    </div>
                </div>
            @endif

            @if (($status ?? '') !== 'contact-sent')
                <form method="post" action="{{ route('govuk-alpha.contact.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf

                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha.contact.form.fieldset_legend') }}</legend>

                        <div class="govuk-form-group{{ $fieldErrors['name'] !== '' ? ' govuk-form-group--error' : '' }}">
                            <label class="govuk-label" for="name">{{ __('govuk_alpha.contact.form.name_label') }}</label>
                            @if ($fieldErrors['name'] !== '')
                                <p id="name-error" class="govuk-error-message">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErrors['name'] }}
                                </p>
                            @endif
                            <input class="govuk-input{{ $fieldErrors['name'] !== '' ? ' govuk-input--error' : '' }}" id="name" name="name" type="text" autocomplete="name" value="{{ $nameValue }}" @if ($fieldErrors['name'] !== '') aria-describedby="name-error" @endif>
                        </div>

                        <div class="govuk-form-group{{ $fieldErrors['email'] !== '' ? ' govuk-form-group--error' : '' }}">
                            <label class="govuk-label" for="email">{{ __('govuk_alpha.contact.form.email_label') }}</label>
                            @if ($fieldErrors['email'] !== '')
                                <p id="email-error" class="govuk-error-message">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErrors['email'] }}
                                </p>
                            @endif
                            <input class="govuk-input govuk-!-width-two-thirds{{ $fieldErrors['email'] !== '' ? ' govuk-input--error' : '' }}" id="email" name="email" type="email" autocomplete="email" value="{{ $emailValue }}" @if ($fieldErrors['email'] !== '') aria-describedby="email-error" @endif>
                        </div>

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="subject">{{ __('govuk_alpha.contact.form.subject_label') }}</label>
                            <select class="govuk-select" id="subject" name="subject">
                                <option value="">{{ __('govuk_alpha.contact.form.subject_placeholder') }}</option>
                                @foreach ($subjectOptions as $option)
                                    <option value="{{ $option }}" @selected($subjectValue === $option)>{{ __('govuk_alpha.contact.form.subjects.' . $option) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="govuk-form-group{{ $fieldErrors['message'] !== '' ? ' govuk-form-group--error' : '' }}">
                            <label class="govuk-label" for="message">{{ __('govuk_alpha.contact.form.message_label') }}</label>
                            @if ($fieldErrors['message'] !== '')
                                <p id="message-error" class="govuk-error-message">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErrors['message'] }}
                                </p>
                            @endif
                            <textarea class="govuk-textarea{{ $fieldErrors['message'] !== '' ? ' govuk-textarea--error' : '' }}" id="message" name="message" rows="5" @if ($fieldErrors['message'] !== '') aria-describedby="message-error" @endif>{{ $messageValue }}</textarea>
                        </div>
                    </fieldset>

                    @if($turnstileSiteKey ?? false)
                        {{-- Cloudflare Turnstile bot challenge --}}
                        <div class="govuk-form-group">
                            <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="auto"></div>
                        </div>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    @endif

                    <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.contact.form.submit') }}</button>

                    @if (!($isAuthenticated ?? false))
                        <p class="govuk-body-s">
                            {{ __('govuk_alpha.contact.form.login_prompt_before') }}
                            <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.contact.form.login_link') }}</a>
                            {{ __('govuk_alpha.contact.form.login_prompt_after') }}
                        </p>
                    @endif
                </form>
            @endif
        </div>
    </div>
@endsection
