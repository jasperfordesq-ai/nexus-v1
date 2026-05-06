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
            <a class="nexus-alpha-header__brand govuk-!-font-size-24" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">
                {{ $tenant['name'] ?? __('govuk_alpha.service_name') }}
            </a>
            <span class="nexus-alpha-header__service govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.service_name') }}</span>
        </div>
    </header>

    <nav class="govuk-service-navigation" aria-label="{{ __('govuk_alpha.navigation_label') }}">
        <div class="govuk-width-container">
            <ul class="govuk-service-navigation__list">
                @foreach ([
                    'feed' => route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]),
                    'listings' => route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]),
                    'members' => route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]),
                ] as $key => $href)
                    <li class="govuk-service-navigation__item {{ ($activeNav ?? '') === $key ? 'govuk-service-navigation__item--active' : '' }}">
                        <a class="govuk-service-navigation__link" href="{{ $href }}" @if (($activeNav ?? '') === $key) aria-current="page" @endif>
                            {{ __('govuk_alpha.nav.' . $key) }}
                        </a>
                    </li>
                @endforeach
                <li class="govuk-service-navigation__item">
                    <a class="govuk-service-navigation__link" href="/{{ $tenantSlug }}">{{ __('govuk_alpha.nav.react_app') }}</a>
                </li>
            </ul>
        </div>
    </nav>

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
