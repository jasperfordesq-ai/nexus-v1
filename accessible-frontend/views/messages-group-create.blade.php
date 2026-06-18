{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $selectedIds = $selectedIds ?? [];
        $selectedMembers = $selectedMembers ?? [];
        $groupName = $groupName ?? '';
        $searchQuery = $searchQuery ?? '';
        $searchResults = $searchResults ?? [];
        $canCreate = count($selectedIds) >= 2;
        // Ids already selected, for filtering them out of fresh search results.
        $selectedIdSet = array_flip(array_map('intval', $selectedIds));
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.messages.groups.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_messages.create.back') }}</a>

    @include('accessible-frontend::partials.messages-status', ['status' => $status ?? null])

    <span class="govuk-caption-l">{{ __('govuk_alpha_messages.create.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_messages.create.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_messages.create.intro') }}</p>

    @if (!$directMessagingEnabled || !empty($restriction['messaging_disabled']))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="create-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="create-disabled-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.disabled_detail') }}</p>
            </div>
        </div>
    @endif

    {{-- Members chosen so far. Each is removed by re-running the GET form without
         that id (no JS). The current group name is preserved. --}}
    <div class="nexus-alpha-card govuk-!-margin-bottom-6">
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_messages.create.selected_title') }}</h2>
        @if (empty($selectedMembers))
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_messages.create.selected_none') }}</p>
        @else
            <ul class="govuk-list govuk-!-margin-bottom-0">
                <li class="govuk-!-margin-bottom-2"><strong>{{ __('govuk_alpha_messages.create.you_label') }}</strong></li>
                @foreach ($selectedMembers as $member)
                    @php
                        $memberId = (int) ($member['id'] ?? 0);
                        $memberName = trim((string) ($member['name'] ?? '')) !== '' ? (string) $member['name'] : __('govuk_alpha.members.unknown_member');
                        $remaining = array_values(array_diff(array_map('intval', $selectedIds), [$memberId]));
                    @endphp
                    <li class="govuk-!-margin-bottom-2" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                        <span>{{ $memberName }}</span>
                        <form method="get" action="{{ route('govuk-alpha.messages.groups.create', ['tenantSlug' => $tenantSlug]) }}" style="display:inline">
                            @if ($groupName !== '')
                                <input type="hidden" name="name" value="{{ $groupName }}">
                            @endif
                            @foreach ($remaining as $keepId)
                                <input type="hidden" name="members[]" value="{{ $keepId }}">
                            @endforeach
                            <button type="submit" class="govuk-link" style="background:none;border:0;padding:0;cursor:pointer;text-decoration:underline;color:#1d70b8">{{ __('govuk_alpha_messages.create.remove_member') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_messages.create.remove_member_hidden', ['name' => $memberName]) }}</span></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        @unless ($canCreate)
            <p class="govuk-hint govuk-!-margin-top-3 govuk-!-margin-bottom-0">{{ __('govuk_alpha_messages.create.min_members_hint') }}</p>
        @endunless
    </div>

    {{-- Member search (GET round-trip). Preserves name + already-selected members. --}}
    <div class="nexus-alpha-card govuk-!-margin-bottom-6">
        <form method="get" action="{{ route('govuk-alpha.messages.groups.create', ['tenantSlug' => $tenantSlug]) }}">
            @if ($groupName !== '')
                <input type="hidden" name="name" value="{{ $groupName }}">
            @endif
            @foreach ($selectedIds as $keepId)
                <input type="hidden" name="members[]" value="{{ (int) $keepId }}">
            @endforeach
            <div class="govuk-form-group govuk-!-margin-bottom-2">
                <label class="govuk-label govuk-label--m" for="group-member-search">{{ __('govuk_alpha_messages.create.search_label') }}</label>
                <div id="group-member-search-hint" class="govuk-hint">{{ __('govuk_alpha_messages.create.search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="group-member-search" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="group-member-search-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_messages.create.search_button') }}</button>
        </form>

        @if ($searchQuery !== '')
            @php
                $freshResults = array_values(array_filter($searchResults, static fn ($r) => !isset($selectedIdSet[(int) ($r['id'] ?? 0)])));
            @endphp
            @if (empty($freshResults))
                <p class="govuk-body govuk-!-margin-top-3 govuk-!-margin-bottom-0">{{ __('govuk_alpha_messages.create.search_empty') }}</p>
            @else
                <ul class="govuk-list govuk-!-margin-top-3 govuk-!-margin-bottom-0">
                    @foreach ($freshResults as $result)
                        @php
                            $resultId = (int) ($result['id'] ?? 0);
                            $resultName = trim((string) ($result['name'] ?? '')) !== '' ? (string) $result['name'] : __('govuk_alpha.members.unknown_member');
                        @endphp
                        <li class="govuk-!-margin-bottom-2" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                            <span>{{ $resultName }}</span>
                            <form method="get" action="{{ route('govuk-alpha.messages.groups.create', ['tenantSlug' => $tenantSlug]) }}" style="display:inline">
                                @if ($groupName !== '')
                                    <input type="hidden" name="name" value="{{ $groupName }}">
                                @endif
                                @foreach ($selectedIds as $keepId)
                                    <input type="hidden" name="members[]" value="{{ (int) $keepId }}">
                                @endforeach
                                <input type="hidden" name="members[]" value="{{ $resultId }}">
                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_messages.create.add_member') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>

    {{-- Create the group: posts the name + every selected member id. The
         member_ids[] field is always declared (an empty fallback when nobody is
         selected yet) so the form's contract is stable; an empty value is
         discarded server-side by messagesNormaliseMemberIds. --}}
    <form method="post" action="{{ route('govuk-alpha.messages.groups.store', ['tenantSlug' => $tenantSlug]) }}">
        @csrf
        @forelse ($selectedIds as $keepId)
            <input type="hidden" name="member_ids[]" value="{{ (int) $keepId }}">
        @empty
            <input type="hidden" name="member_ids[]" value="">
        @endforelse
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="group-name">{{ __('govuk_alpha_messages.create.name_label') }}</label>
            <div id="group-name-hint" class="govuk-hint">{{ __('govuk_alpha_messages.create.name_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="group-name" name="name" type="text" value="{{ $groupName }}" maxlength="100" aria-describedby="group-name-hint" required>
        </div>
        <button class="govuk-button" data-module="govuk-button" @unless ($canCreate && $directMessagingEnabled && empty($restriction['messaging_disabled'])) disabled aria-disabled="true" @endunless>{{ __('govuk_alpha_messages.create.create_button') }}</button>
    </form>
@endsection
