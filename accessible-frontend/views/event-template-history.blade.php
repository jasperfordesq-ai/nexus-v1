{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $libraryParameters = array_filter([
            'tenantSlug' => $tenantSlug,
            'filter' => $filter,
            'cursor' => $libraryCursor,
        ]);
        $templateTitle = (string) ($template['version']['configuration']['title'] ?? $template['source_event']['title']);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.templates.index', $libraryParameters) }}">{{ __('event_templates.back_to_library') }}</a>

    <span class="govuk-caption-xl">{{ $templateTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_templates.audit_title') }}</h1>
    <p class="govuk-body-l">{{ __('event_templates.audit_intro') }}</p>

    <div class="govuk-inset-text">{{ __('event_templates.audit_immutable') }}</div>

    @if (empty($audits))
        <p class="govuk-body">{{ __('event_templates.audit_empty') }}</p>
    @else
        <ol class="govuk-list">
            @foreach ($audits as $audit)
                @php
                    $evidence = is_array($audit['evidence'] ?? null) ? $audit['evidence'] : [];
                    $integrityHash = (string) ($evidence['snapshot_hash'] ?? $evidence['effective_snapshot_hash'] ?? '');
                    $copiedFields = is_array($evidence['copied_fields'] ?? null) ? $evidence['copied_fields'] : [];
                    $overrideFields = is_array($evidence['override_fields'] ?? null) ? $evidence['override_fields'] : [];
                @endphp
                <li class="govuk-summary-card govuk-!-margin-bottom-6">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">{{ __('event_templates.audit_actions.' . $audit['action']) }}</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <dl class="govuk-summary-list">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_templates.audit_recorded_at') }}</dt>
                                <dd class="govuk-summary-list__value">
                                    <time datetime="{{ $audit['created_at'] }}">{{ \Carbon\CarbonImmutable::parse($audit['created_at'])->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                                </dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_templates.version') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $audit['template_version'] }}</dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_templates.source') }}</dt>
                                <dd class="govuk-summary-list__value">{{ __('event_templates.audit_event_reference', ['id' => $audit['source_event_id']]) }}</dd>
                            </div>
                            @if ($audit['materialized_event_id'] !== null)
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_templates.audit_created_event') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ __('event_templates.audit_event_reference', ['id' => $audit['materialized_event_id']]) }}</dd>
                                </div>
                            @endif
                            @if ($integrityHash !== '')
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_templates.audit_integrity') }}</dt>
                                    <dd class="govuk-summary-list__value"><code>{!! implode('<wbr>', array_map('e', str_split($integrityHash, 8))) !!}</code></dd>
                                </div>
                            @endif
                            @if (array_key_exists('archive_reason_recorded', $evidence))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('event_templates.audit_archive_reason_recorded') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $evidence['archive_reason_recorded'] ? __('event_templates.audit_yes') : __('event_templates.audit_no') }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($copiedFields !== [])
                            <h3 class="govuk-heading-s">{{ __('event_templates.copied_title') }}</h3>
                            <ul class="govuk-list govuk-list--bullet">
                                @foreach ($copiedFields as $field)
                                    @if (is_string($field) && \Illuminate\Support\Facades\Lang::has('event_templates.fields.' . $field))
                                        <li>{{ __('event_templates.fields.' . $field) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif

                        @if ($overrideFields !== [])
                            <h3 class="govuk-heading-s">{{ __('event_templates.audit_override_fields') }}</h3>
                            <ul class="govuk-list govuk-list--bullet">
                                @foreach ($overrideFields as $field)
                                    @if (is_string($field) && \Illuminate\Support\Facades\Lang::has('event_templates.fields.' . $field))
                                        <li>{{ __('event_templates.fields.' . $field) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if (!empty($pagination['has_more']) && !empty($pagination['next_cursor']))
        <nav class="govuk-pagination govuk-pagination--block" aria-label="{{ __('event_templates.audit_pagination_label') }}">
            <div class="govuk-pagination__next">
                <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.templates.history', array_filter(['tenantSlug' => $tenantSlug, 'templateId' => $template['id'], 'cursor' => $pagination['next_cursor'], 'filter' => $filter, 'library_cursor' => $libraryCursor])) }}">
                    <span class="govuk-pagination__link-title">{{ __('event_templates.audit_load_more') }}</span>
                </a>
            </div>
        </nav>
    @endif
@endsection
