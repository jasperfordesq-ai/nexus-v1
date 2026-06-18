{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Sub-navigation between direct messages and group conversations. Server-side
    tab switching (no JS). Expects: $tenantSlug, $messagesActive ('direct'|'groups').
--}}
@php
    $messagesActive = $messagesActive ?? 'groups';
@endphp
<nav class="govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha.messages.tabs_title') }}">
    <ul class="govuk-list" style="display:flex;gap:1rem;list-style:none;padding:0;margin:0 0 1rem">
        <li>
            <a class="govuk-link{{ $messagesActive === 'direct' ? ' govuk-link--no-visited-state' : '' }}"
               href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]) }}"
               @if ($messagesActive === 'direct') aria-current="page" @endif>{{ __('govuk_alpha_messages.groups.tab_direct') }}</a>
        </li>
        <li>
            <a class="govuk-link{{ $messagesActive === 'groups' ? ' govuk-link--no-visited-state' : '' }}"
               href="{{ route('govuk-alpha.messages.groups.index', ['tenantSlug' => $tenantSlug]) }}"
               @if ($messagesActive === 'groups') aria-current="page" @endif>{{ __('govuk_alpha_messages.groups.tab_groups') }}</a>
        </li>
    </ul>
</nav>
