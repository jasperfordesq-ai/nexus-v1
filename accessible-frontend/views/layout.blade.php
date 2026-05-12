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
<body class="govuk-template__body js-enabled govuk-frontend-supported">
    <a href="#main-content" class="govuk-skip-link" data-module="govuk-skip-link">{{ __('govuk_alpha.skip_to_content') }}</a>

    <header class="nexus-alpha-header" role="banner">
        <div class="govuk-width-container nexus-alpha-header__container">
            <a class="nexus-alpha-header__brand govuk-!-font-size-24" href="{{ $tenantSlug ? route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) : route('govuk-alpha.tenant-chooser') }}">
                {{ !empty($tenantSlug) ? ($tenant['name'] ?? $tenantSlug) : __('govuk_alpha.service_name') }}
            </a>
            @if (!empty($tenantSlug))
                <nav class="nexus-alpha-header__links" aria-label="{{ __('govuk_alpha.header.links_label') }}">
                    @if ($isAuthenticated ?? false)
                        <a class="nexus-alpha-header__link" href="{{ route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]) }}" @if (($activeNav ?? '') === 'profile') aria-current="page" @endif>
                            {{ __('govuk_alpha.nav.profile') }}
                        </a>
                    @endif
                    <a class="nexus-alpha-header__link" href="{{ $mainSiteUrl ?? '/' }}">
                        {{ __('govuk_alpha.header.back_to_main_site') }}
                    </a>
                </nav>
            @endif
        </div>
    </header>

    @if (!empty($tenantSlug))
        <section class="govuk-service-navigation" data-module="govuk-service-navigation" aria-label="{{ __('govuk_alpha.service_information_label') }}">
            <div class="govuk-width-container">
                <div class="govuk-service-navigation__container">
                    <nav class="govuk-service-navigation__wrapper" aria-label="{{ __('govuk_alpha.navigation_label') }}">
                        <button type="button" class="govuk-service-navigation__toggle govuk-js-service-navigation-toggle" aria-controls="alpha-navigation" hidden aria-hidden="true">
                            {{ __('govuk_alpha.nav.menu') }}
                        </button>
                        <ul class="govuk-service-navigation__list" id="alpha-navigation">
                            @foreach (($alphaNavItems ?? []) as $key => $href)
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
                    <a class="govuk-link" href="{{ $feedbackUrl ?? __('govuk_alpha.feedback_url') }}">{{ __('govuk_alpha.feedback') }}</a>
                </span>
            </p>
        </div>
        <main class="govuk-main-wrapper" id="main-content" tabindex="-1">
            @yield('content')
        </main>
    </div>

    <footer class="nexus-alpha-footer" role="contentinfo">
        <div class="govuk-width-container govuk-!-padding-top-6 govuk-!-padding-bottom-6">
            @if (!empty($alphaFooterLinks))
                <nav class="nexus-alpha-footer__links" aria-label="{{ __('govuk_alpha.footer.links_label') }}">
                    <h2 class="govuk-heading-s">{{ __('govuk_alpha.footer.links_heading') }}</h2>
                    <ul class="govuk-list">
                        @foreach ($alphaFooterLinks as $key => $href)
                            <li>
                                <a class="govuk-link" href="{{ $href }}">{{ __('govuk_alpha.footer.links.' . $key) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
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
