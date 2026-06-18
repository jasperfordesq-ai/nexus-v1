{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
<section>
    @if (!empty($showHeading))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.results.section_events') }}</h2>
    @endif
    <div class="nexus-alpha-card-list">
        @foreach ($items as $event)
            @php
                $eTitle = trim((string) ($event['title'] ?? '')) ?: __('govuk_alpha_search.results.section_events');
                $eId = (int) ($event['id'] ?? 0);
                $eHref = ($eId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.events.show'))
                    ? route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eId])
                    : null;
                $eWhenRaw = $event['start_time'] ?? ($event['start_date'] ?? null);
                $eWhen = '';
                if (!empty($eWhenRaw)) {
                    try {
                        $eWhen = \Illuminate\Support\Carbon::parse((string) $eWhenRaw)->isoFormat('LLL');
                    } catch (\Throwable $e) {
                        $eWhen = '';
                    }
                }
                $eLocation = trim((string) ($event['location'] ?? ''));
            @endphp
            <article class="nexus-alpha-card">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                    @if ($eHref)<a class="govuk-link" href="{{ $eHref }}">{{ $eTitle }}</a>@else{{ $eTitle }}@endif
                </h3>

                @if (trim((string) ($event['description'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit((string) $event['description'], 200) }}</p>
                @endif

                <dl class="nexus-alpha-inline-list govuk-!-margin-bottom-2">
                    @if ($eWhen !== '')
                        <div>
                            <dt class="govuk-visually-hidden">{{ __('govuk_alpha_search.results.section_events') }}</dt>
                            <dd>{{ $eWhen }}</dd>
                        </div>
                    @endif
                    @if ($eLocation !== '')
                        <div>
                            <dt class="govuk-visually-hidden">{{ __('govuk_alpha_search.filters.location') }}</dt>
                            <dd>{{ $eLocation }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($eHref)
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ $eHref }}">{{ __('govuk_alpha_search.results.view_event') }}</a>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
