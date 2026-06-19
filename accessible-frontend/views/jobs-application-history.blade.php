{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Reuse the application-status labels from the applications list.
        $statusLabel = function (?string $s): string {
            $s = trim((string) $s);
            if ($s === '') { return ''; }
            $key = 'govuk_alpha.jobs_t2.app_status_' . $s;
            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : \Illuminate\Support\Str::headline($s);
        };
        $fmtWhen = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y, g:ia') : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.jobs.applications', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.history.back_link') }}</a>

    <span class="govuk-caption-xl">{{ $vacancyTitle !== '' ? $vacancyTitle : ($tenant['name'] ?? $tenantSlug) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.history.title') }}</h1>

    @if (empty($history))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.history.empty') }}</p></div>
    @else
        <ol class="govuk-list">
            @foreach ($history as $entry)
                @php
                    $to = $statusLabel($entry['to_status'] ?? '');
                    $from = $statusLabel($entry['from_status'] ?? '');
                    $when = $fmtWhen($entry['changed_at'] ?? null);
                    $by = trim((string) ($entry['changed_by_name'] ?? ''));
                    $notes = trim((string) ($entry['notes'] ?? ''));
                @endphp
                <li class="govuk-!-margin-bottom-4">
                    <strong class="govuk-tag govuk-tag--blue">{{ $to !== '' ? $to : __('govuk_alpha_jobs.history.status_unknown') }}</strong>
                    @if ($from !== '')
                        <span class="govuk-body-s nexus-alpha-meta"> · {{ __('govuk_alpha_jobs.history.from', ['status' => $from]) }}</span>
                    @endif
                    @if ($when)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1 govuk-!-margin-top-1">{{ $when }}@if ($by !== '') · {{ __('govuk_alpha_jobs.history.by', ['name' => $by]) }}@endif</p>
                    @endif
                    @if ($notes !== '')
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ $notes }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
@endsection
