{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Format cents with the tenant's currency. Known codes render as a
        // symbol prefix; any other ISO code renders as "CODE 12.00".
        $currencyCode = strtoupper(trim((string) ($currency ?? '')));
        $currencyPrefix = match ($currencyCode) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $currencyCode !== '' ? $currencyCode . ' ' : '',
        };
        $money = fn ($cents): string => $currencyPrefix . number_format(((int) $cents) / 100, 2);
    @endphp

    @if ($status === 'subscribe-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.premium.states.subscribe-failed') }}</li></ul></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.premium.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.premium.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.premium.description') }}</p>

    @if (!empty($currentTier) && trim((string) ($currentTier['tier_name'] ?? '')) !== '')
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.premium.current_plan_title') }}</h2>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.premium.current_plan_notice', ['name' => $currentTier['tier_name']]) }}</p>
        </div>
    @endif

    @if (empty($tiers))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.premium.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($tiers as $tier)
                @php
                    $tName = trim((string) ($tier['name'] ?? '')) ?: __('govuk_alpha.premium.title');
                    $monthly = (int) ($tier['monthly_price_cents'] ?? 0);
                    $yearly = (int) ($tier['yearly_price_cents'] ?? 0);
                    $features = is_array($tier['features'] ?? null) ? array_filter(array_map('strval', $tier['features'])) : [];
                    $tid = (int) ($tier['id'] ?? 0);
                    // Tiers offering both intervals get a per-form radio group
                    // (works without JavaScript). Single-interval tiers post a
                    // hidden input with their only available interval.
                    $tierHasBoth = $monthly > 0 && $yearly > 0;
                    $defaultInterval = ($yearly > 0 && $monthly === 0) ? 'yearly' : 'monthly';
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $tName }}</h2>
                    @if (trim((string) ($tier['description'] ?? '')) !== '')
                        <p class="govuk-body">{{ $tier['description'] }}</p>
                    @endif
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        @if ($monthly > 0)<strong>{{ $money($monthly) }}</strong> {{ __('govuk_alpha.premium.per_month') }}@endif
                        @if ($monthly > 0 && $yearly > 0) · @endif
                        @if ($yearly > 0)<strong>{{ $money($yearly) }}</strong> {{ __('govuk_alpha.premium.per_year') }}@endif
                    </p>
                    @if (!empty($features))
                        <ul class="govuk-list govuk-list--bullet">
                            @foreach ($features as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="post" action="{{ route('govuk-alpha.premium.subscribe', ['tenantSlug' => $tenantSlug]) }}">
                        @csrf
                        <input type="hidden" name="tier_id" value="{{ $tid }}">
                        @if ($tierHasBoth)
                            <div class="govuk-form-group">
                                <fieldset class="govuk-fieldset">
                                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.polish_commerce.premium_interval_heading') }}</legend>
                                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                        <div class="govuk-radios__item">
                                            <input class="govuk-radios__input" id="interval-{{ $tid }}-monthly" name="interval" type="radio" value="monthly" checked>
                                            <label class="govuk-label govuk-radios__label" for="interval-{{ $tid }}-monthly">{{ __('govuk_alpha.polish_commerce.premium_interval_monthly') }} — {{ $money($monthly) }}</label>
                                        </div>
                                        <div class="govuk-radios__item">
                                            <input class="govuk-radios__input" id="interval-{{ $tid }}-yearly" name="interval" type="radio" value="yearly">
                                            <label class="govuk-label govuk-radios__label" for="interval-{{ $tid }}-yearly">{{ __('govuk_alpha.polish_commerce.premium_interval_yearly') }} — {{ $money($yearly) }}</label>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        @else
                            <input type="hidden" name="interval" value="{{ $defaultInterval }}">
                        @endif
                        <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.premium.subscribe_button') }}</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
