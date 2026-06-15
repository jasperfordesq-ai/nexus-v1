{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $discountLabel = function (array $c): string {
            $type = (string) ($c['discount_type'] ?? '');
            $value = $c['discount_value'] ?? null;
            if ($value === null || $value === '') { return ''; }
            $num = rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
            return $type === 'percentage' || $type === 'percent'
                ? __('govuk_alpha.coupons.percent_off', ['value' => $num])
                : __('govuk_alpha.coupons.amount_off', ['value' => $num]);
        };
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.coupons.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.coupons.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.coupons.description') }}</p>

    @if (empty($coupons))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.coupons.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($coupons as $c)
                @php
                    $cTitle = trim((string) ($c['title'] ?? '')) ?: trim((string) ($c['code'] ?? ''));
                    $discount = $discountLabel($c);
                    $until = $dateFmt($c['valid_until'] ?? null);
                    $code = trim((string) ($c['code'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.coupons.show', ['tenantSlug' => $tenantSlug, 'id' => $c['id']]) }}">{{ $cTitle }}</a></h2>
                        @if ($discount !== '')<strong class="govuk-tag govuk-tag--green">{{ $discount }}</strong>@endif
                    </div>
                    @if (trim((string) ($c['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($c['description'], 200) }}</p>
                    @endif
                    @if ($code !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha.coupons.code_label') }}: <strong>{{ $code }}</strong></p>
                    @endif
                    @if ($until !== null)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha.coupons.valid_until', ['date' => $until]) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
