{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="govuk-template">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? __('govuk_alpha.service_name') }} - {{ __('govuk_alpha.service_name') }}</title>
    @foreach (($assetEntrypoint['css'] ?? []) as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
</head>
<body class="govuk-template__body">
    <script>document.body.className = document.body.className ? document.body.className + ' js-enabled' : 'js-enabled';</script>
    <a href="#main-content" class="govuk-skip-link" data-module="govuk-skip-link">{{ __('govuk_alpha.skip_to_content') }}</a>

    <header class="nexus-alpha-header" role="banner">
        <div class="govuk-width-container nexus-alpha-header__container">
            <a class="nexus-alpha-header__brand govuk-!-font-size-24" href="{{ $tenantSlug ? route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) : route('govuk-alpha.tenant-chooser') }}">
                {{ __('govuk_alpha.service_name') }}
            </a>
            @if (!empty($tenantSlug))
                <span class="nexus-alpha-header__service govuk-body govuk-!-margin-bottom-0">
                    {{ __('govuk_alpha.header.community', ['name' => $tenant['name'] ?? $tenantSlug]) }}
                </span>
            @endif
        </div>
    </header>

    @if (!empty($tenantSlug))
        <section class="govuk-service-navigation" data-module="govuk-service-navigation" aria-label="{{ __('govuk_alpha.service_information_label') }}">
            <div class="govuk-width-container">
                <div class="govuk-service-navigation__container">
                    <span class="govuk-service-navigation__service-name">
                        <a class="govuk-service-navigation__link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) }}">
                            {{ $tenant['name'] ?? $tenantSlug }}
                        </a>
                    </span>
                    <nav class="govuk-service-navigation__wrapper" aria-label="{{ __('govuk_alpha.navigation_label') }}">
                        <button type="button" class="govuk-service-navigation__toggle govuk-js-service-navigation-toggle" aria-controls="alpha-navigation" hidden aria-hidden="true">
                            {{ __('govuk_alpha.nav.menu') }}
                        </button>
                        <ul class="govuk-service-navigation__list" id="alpha-navigation">
                            @foreach ([
                                'home' => route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]),
                                'feed' => route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]),
                                'listings' => route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]),
                                'members' => route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]),
                            ] as $key => $href)
                                <li class="govuk-service-navigation__item {{ ($activeNav ?? '') === $key ? 'govuk-service-navigation__item--active' : '' }}">
                                    <a class="govuk-service-navigation__link" href="{{ $href }}" @if (($activeNav ?? '') === $key) aria-current="page" @endif>
                                        @if (($activeNav ?? '') === $key)
                                            <strong class="govuk-service-navigation__active-fallback">{{ __('govuk_alpha.nav.' . $key) }}</strong>
                                        @else
                                            {{ __('govuk_alpha.nav.' . $key) }}
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                            <li class="govuk-service-navigation__item">
                                <a class="govuk-service-navigation__link" href="/{{ $tenantSlug }}">{{ __('govuk_alpha.nav.react_app') }}</a>
                            </li>
                            @if (!($isAuthenticated ?? false))
                                <li class="govuk-service-navigation__item {{ ($activeNav ?? '') === 'login' ? 'govuk-service-navigation__item--active' : '' }}">
                                    <a class="govuk-service-navigation__link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" @if (($activeNav ?? '') === 'login') aria-current="page" @endif>
                                        @if (($activeNav ?? '') === 'login')
                                            <strong class="govuk-service-navigation__active-fallback">{{ __('govuk_alpha.nav.login') }}</strong>
                                        @else
                                            {{ __('govuk_alpha.nav.login') }}
                                        @endif
                                    </a>
                                </li>
                                <li class="govuk-service-navigation__item {{ ($activeNav ?? '') === 'register' ? 'govuk-service-navigation__item--active' : '' }}">
                                    <a class="govuk-service-navigation__link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" @if (($activeNav ?? '') === 'register') aria-current="page" @endif>
                                        @if (($activeNav ?? '') === 'register')
                                            <strong class="govuk-service-navigation__active-fallback">{{ __('govuk_alpha.nav.register') }}</strong>
                                        @else
                                            {{ __('govuk_alpha.nav.register') }}
                                        @endif
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </nav>
                </div>
            </div>
        </section>
    @endif

    <div class="govuk-width-container">
        <div class="govuk-phase-banner">
            <p class="govuk-phase-banner__content">
                <strong class="govuk-tag govuk-phase-banner__content__tag">{{ __('govuk_alpha.phase') }}</strong>
                <span class="govuk-phase-banner__text">
                    <a class="govuk-link" href="{{ __('govuk_alpha.feedback_url') }}">{{ __('govuk_alpha.feedback') }}</a>
                </span>
            </p>
        </div>
        <main class="govuk-main-wrapper" id="main-content" tabindex="-1">
            @yield('content')
        </main>
    </div>

    <footer class="nexus-alpha-footer" role="contentinfo">
        <div class="govuk-width-container govuk-!-padding-top-6 govuk-!-padding-bottom-6">
            <p class="govuk-body-s">{{ __('govuk_alpha.footer.licence') }}</p>
            <p class="govuk-body-s">{{ __('govuk_alpha.footer.attribution') }}</p>
            <p class="govuk-body-s">
                <a class="govuk-link" href="https://github.com/jasperfordesq-ai/nexus-v1">{{ __('govuk_alpha.footer.source') }}</a>
            </p>
        </div>
    </footer>

    @if (!empty($assetEntrypoint['js']))
        <script type="module" src="{{ $assetEntrypoint['js'] }}"></script>
    @endif
</body>
</html>
