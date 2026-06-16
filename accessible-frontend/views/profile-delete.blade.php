{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $community = $tenant['name'] ?? $tenantSlug;
        $errorMessages = [
            'delete-password-required' => __('govuk_alpha.delete_account.error_password'),
            'delete-confirm-required' => __('govuk_alpha.delete_account.error_confirm'),
            'delete-password-incorrect' => __('govuk_alpha.delete_account.error_password_incorrect'),
            'delete-failed' => __('govuk_alpha.delete_account.error_failed'),
        ];
        $currentError = $errorMessages[$status ?? ''] ?? null;
        $passwordError = in_array($status ?? '', ['delete-password-required', 'delete-password-incorrect'], true);
        $confirmError = ($status ?? '') === 'delete-confirm-required';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_profile') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.delete_account.title') }}</h1>

            @if ($currentError)
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#{{ $confirmError ? 'confirm' : 'password' }}">{{ $currentError }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.delete_account.warning_prefix') }}</span>
                    {{ __('govuk_alpha.delete_account.warning', ['community' => $community]) }}
                </strong>
            </div>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.delete_account.what_happens_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.delete_account.what_happens') }}</p>

            <form method="post" action="{{ route('govuk-alpha.profile.delete.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf

                <div class="govuk-form-group{{ $passwordError ? ' govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.delete_account.password_label') }}</label>
                    <div id="password-hint" class="govuk-hint">{{ __('govuk_alpha.delete_account.password_hint') }}</div>
                    @if ($passwordError)
                        <p id="password-error" class="govuk-error-message">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $errorMessages[$status] }}
                        </p>
                    @endif
                    <input class="govuk-input govuk-input--width-20{{ $passwordError ? ' govuk-input--error' : '' }}" id="password" name="password" type="password" autocomplete="current-password" aria-describedby="password-hint{{ $passwordError ? ' password-error' : '' }}">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="reason">{{ __('govuk_alpha.delete_account.reason_label') }}</label>
                    <textarea class="govuk-textarea" id="reason" name="reason" rows="3"></textarea>
                </div>

                <div class="govuk-form-group{{ $confirmError ? ' govuk-form-group--error' : '' }}">
                    @if ($confirmError)
                        <p id="confirm-error" class="govuk-error-message">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $errorMessages[$status] }}
                        </p>
                    @endif
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="confirm" name="confirm" type="checkbox" value="1" @if ($confirmError) aria-describedby="confirm-error" @endif>
                            <label class="govuk-label govuk-checkboxes__label" for="confirm">{{ __('govuk_alpha.delete_account.confirm_label') }}</label>
                        </div>
                    </div>
                </div>

                <div class="govuk-button-group">
                    <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">{{ __('govuk_alpha.delete_account.submit') }}</button>
                    <a class="govuk-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.delete_account.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
