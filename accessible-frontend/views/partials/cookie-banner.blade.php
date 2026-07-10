{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    GOV.UK cookie banner (https://design-system.service.gov.uk/components/cookie-banner/).
    Works with NO JavaScript: "Accept"/"Reject" submit a form that records the choice in
    the cookie_consents backend + sets a first-party cookie so the banner stays dismissed.
    The GOV.UK confirmation message shows once, via a flashed choice, on the page the
    member was returned to. Only shown on tenant pages (consent is tenant-scoped).
--}}
@php
    $alphaCookieChoice = session('alpha_cookie_choice');
    $alphaHasCookieChoice = request()->cookie('nexus_alpha_cookie_consent') !== null;
    $cookieService = $tenant['name'] ?? __('govuk_alpha.service_name');
@endphp
@if (!empty($tenantSlug) && ($alphaCookieChoice || ! $alphaHasCookieChoice))
    <div class="govuk-cookie-banner" data-nosnippet role="region" aria-label="{{ __('govuk_alpha.cookie_banner.aria_label', ['service' => $cookieService]) }}">
        @if ($alphaCookieChoice)
            <div class="govuk-cookie-banner__message govuk-width-container" role="alert">
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-two-thirds">
                        <div class="govuk-cookie-banner__content">
                            <p class="govuk-body">
                                {{ $alphaCookieChoice === 'accepted' ? __('govuk_alpha.cookie_banner.confirm_accepted') : __('govuk_alpha.cookie_banner.confirm_rejected') }}
                                <a class="govuk-link" href="{{ route('govuk-alpha.cookies', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.cookie_banner.confirm_change_link') }}</a>
                                {{ __('govuk_alpha.cookie_banner.confirm_change_suffix') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="govuk-button-group">
                    <a class="govuk-link" href="{{ request()->getRequestUri() }}">{{ __('govuk_alpha.cookie_banner.hide') }}</a>
                </div>
            </div>
        @else
            <form method="post" action="{{ route('govuk-alpha.cookies.store', ['tenantSlug' => $tenantSlug]) }}">
                @csrf
                <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
                <div class="govuk-cookie-banner__message govuk-width-container">
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <h2 class="govuk-cookie-banner__heading govuk-heading-m">{{ __('govuk_alpha.cookie_banner.heading', ['service' => $cookieService]) }}</h2>
                            <div class="govuk-cookie-banner__content">
                                <p class="govuk-body">{{ __('govuk_alpha.cookie_banner.intro') }}</p>
                                <p class="govuk-body">{{ __('govuk_alpha.cookie_banner.analytics_intro') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="govuk-button-group">
                        <button type="submit" name="cookies" value="accept" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.cookie_banner.accept') }}</button>
                        <button type="submit" name="cookies" value="reject" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.cookie_banner.reject') }}</button>
                        <a class="govuk-link" href="{{ route('govuk-alpha.cookies', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.cookie_banner.view') }}</a>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endif
