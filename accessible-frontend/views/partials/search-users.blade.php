{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
<section>
    @if (!empty($showHeading))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.results.section_users') }}</h2>
    @endif
    <div class="nexus-alpha-card-list">
        @foreach ($items as $user)
            @php
                $uName = trim((string) ($user['name'] ?? '')) ?: __('govuk_alpha_search.results.section_users');
                $uId = (int) ($user['id'] ?? 0);
                $uHref = ($uId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.members.show'))
                    ? route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $uId])
                    : null;
                $uAvatar = trim((string) ($user['avatar'] ?? ($user['avatar_url'] ?? '')));
                $uTagline = trim((string) ($user['tagline'] ?? ($user['bio'] ?? '')));
                $uLocation = trim((string) ($user['location'] ?? ''));
            @endphp
            <article class="nexus-alpha-card">
                <div class="nexus-alpha-module-row">
                    <span class="nexus-alpha-actions">
                        @if ($uAvatar !== '')
                            <img class="nexus-alpha-avatar" src="{{ $uAvatar }}" alt="{{ __('govuk_alpha_search.results.image_alt', ['title' => $uName]) }}">
                        @else
                            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($uName, 0, 1)) }}</span>
                        @endif
                        <span>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                                @if ($uHref)<a class="govuk-link" href="{{ $uHref }}">{{ $uName }}</a>@else{{ $uName }}@endif
                            </h3>
                            @if ($uLocation !== '')
                                <span class="govuk-body-s nexus-alpha-meta">{{ $uLocation }}</span>
                            @endif
                        </span>
                    </span>
                </div>

                @if ($uTagline !== '')
                    <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($uTagline, 180) }}</p>
                @endif

                @if ($uHref)
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ $uHref }}">{{ __('govuk_alpha_search.results.view_member') }}</a>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
