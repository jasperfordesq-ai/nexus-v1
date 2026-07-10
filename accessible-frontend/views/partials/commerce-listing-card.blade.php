{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    A single marketplace listing card. Expects $card (array, the formatted
    listing) and $tenantSlug. Shows title, price tag, location and thumbnail.
--}}
@php
    $cardAsUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
    $cardTitle = trim((string) ($card['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
    $cardTc = (float) ($card['time_credit_price'] ?? 0);
    $cardMoney = (float) ($card['price'] ?? 0);
    if ($cardTc > 0) {
        $cardPrice = rtrim(rtrim(number_format($cardTc, 2), '0'), '.') . ' ' . __('govuk_alpha_commerce.common.credits_label');
        $cardTag = 'govuk-tag--blue';
    } elseif ($cardMoney > 0) {
        $cardPrice = trim(trim((string) ($card['price_currency'] ?? '')) . ' ' . number_format($cardMoney, 2));
        $cardTag = 'govuk-tag--grey';
    } else {
        $cardPrice = __('govuk_alpha_commerce.common.free');
        $cardTag = 'govuk-tag--green';
    }
    $cardThumb = $cardAsUrl(trim((string) ($card['image']['thumbnail_url'] ?? ($card['image']['url'] ?? ''))));
    $cardLoc = trim((string) ($card['location'] ?? ''));
    $cardHref = route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]);
@endphp
<article class="nexus-alpha-card">
    <div class="nexus-alpha-listing-row">
        @if ($cardThumb !== '')
            <div class="nexus-alpha-listing-row__media">
                <img class="nexus-alpha-card-thumb" src="{{ $cardThumb }}" alt="{{ $cardTitle }}" width="120" height="90" loading="lazy" decoding="async">
            </div>
        @endif
        <div class="nexus-alpha-listing-row__body">
            <div class="nexus-alpha-module-row">
                <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $cardHref }}">{{ $cardTitle }}</a></h2>
                <strong class="govuk-tag {{ $cardTag }}">{{ $cardPrice }}</strong>
            </div>
            @if (trim((string) ($card['tagline'] ?? '')) !== '')
                <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($card['tagline'], 160) }}</p>
            @endif
            @if ($cardLoc !== '')
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $cardLoc }}</p>
            @endif
        </div>
    </div>
</article>
