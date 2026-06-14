{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.saved.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.saved.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.saved.description') }}</p>

    @if (empty($savedItems))
        <p class="govuk-inset-text">{{ __('govuk_alpha.saved.empty') }}</p>
    @else
        <ul class="govuk-list govuk-list--spaced">
            @foreach ($savedItems as $s)
                @php
                    $sTitle = trim((string) ($s['title'] ?? ''));
                    $sType = (string) ($s['bookmarkable_type'] ?? '');
                    $sType = $sType !== '' ? \Illuminate\Support\Str::headline(class_basename($sType)) : '';
                @endphp
                @if ($sTitle !== '' || $sType !== '')
                    <li>
                        @if ($sTitle !== ''){{ $sTitle }}@endif
                        @if ($sType !== '') <span class="govuk-body-s nexus-alpha-meta">— {{ $sType }}</span>@endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
@endsection
