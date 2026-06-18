{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
<section>
    @if (!empty($showHeading))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.results.section_groups') }}</h2>
    @endif
    <div class="nexus-alpha-card-list">
        @foreach ($items as $group)
            @php
                $gName = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_search.results.section_groups');
                $gId = (int) ($group['id'] ?? 0);
                $gHref = ($gId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.groups.show'))
                    ? route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId])
                    : null;
                $gMembers = (int) ($group['members_count'] ?? ($group['member_count'] ?? 0));
            @endphp
            <article class="nexus-alpha-card">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                    @if ($gHref)<a class="govuk-link" href="{{ $gHref }}">{{ $gName }}</a>@else{{ $gName }}@endif
                </h3>

                @if (trim((string) ($group['description'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit((string) $group['description'], 200) }}</p>
                @endif

                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                    {{ trans_choice('govuk_alpha_search.results.members_count', $gMembers, ['count' => $gMembers]) }}
                </p>

                @if ($gHref)
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ $gHref }}">{{ __('govuk_alpha_search.results.view_group') }}</a>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
