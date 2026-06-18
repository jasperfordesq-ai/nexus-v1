{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $credentials = $credentials ?? [];
        $status = $status ?? null;
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $typeOptions = ['police_check', 'first_aid', 'safeguarding', 'dbs', 'driving_licence', 'other'];
        $typeLabelKeys = [
            'police_check' => 'govuk_alpha_volunteering.credentials.type_police_check',
            'first_aid' => 'govuk_alpha_volunteering.credentials.type_first_aid',
            'safeguarding' => 'govuk_alpha_volunteering.credentials.type_safeguarding',
            'dbs' => 'govuk_alpha_volunteering.credentials.type_dbs',
            'driving_licence' => 'govuk_alpha_volunteering.credentials.type_driving_licence',
            'other' => 'govuk_alpha_volunteering.credentials.type_other',
        ];
        $typeLabel = function (string $value) use ($typeLabelKeys): string {
            return isset($typeLabelKeys[$value]) ? __($typeLabelKeys[$value]) : \Illuminate\Support\Str::headline($value);
        };
        $statusTag = [
            'pending' => 'govuk-tag--yellow',
            'verified' => 'govuk-tag--green',
            'rejected' => 'govuk-tag--red',
            'expired' => 'govuk-tag--grey',
        ];
        $statusLabel = [
            'pending' => 'govuk_alpha_volunteering.credentials.status_pending',
            'verified' => 'govuk_alpha_volunteering.credentials.status_verified',
            'rejected' => 'govuk_alpha_volunteering.credentials.status_rejected',
            'expired' => 'govuk_alpha_volunteering.credentials.status_expired',
        ];
        $fileErrors = ['credential-type-required', 'credential-file-required', 'credential-file-type', 'credential-file-size', 'credential-upload-failed', 'credential-delete-failed'];
        $errorMsg = [
            'credential-type-required' => 'govuk_alpha_volunteering.credentials.type_required',
            'credential-file-required' => 'govuk_alpha_volunteering.credentials.file_required',
            'credential-file-type' => 'govuk_alpha_volunteering.credentials.file_type',
            'credential-file-size' => 'govuk_alpha_volunteering.credentials.file_size',
            'credential-upload-failed' => 'govuk_alpha_volunteering.credentials.upload_failed',
            'credential-delete-failed' => 'govuk_alpha_volunteering.credentials.delete_failed',
        ];
        $successMsg = [
            'credential-uploaded' => 'govuk_alpha_volunteering.credentials.uploaded',
            'credential-deleted' => 'govuk_alpha_volunteering.credentials.deleted',
        ];
        $typeError = in_array($status, ['credential-type-required'], true);
        $fileError = in_array($status, ['credential-file-required', 'credential-file-type', 'credential-file-size'], true);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if (in_array($status, $fileErrors, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @if ($typeError)
                            <li><a href="#credential_type">{{ __('govuk_alpha_volunteering.credentials.error_type_required') }}</a></li>
                        @elseif ($fileError)
                            <li><a href="#document">{{ __($errorMsg[$status]) }}</a></li>
                        @else
                            <li>{{ __($errorMsg[$status]) }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @elseif (isset($successMsg[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="credential-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="credential-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __($successMsg[$status]) }}</p></div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.credentials.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.credentials.description') }}</p>

    {{-- Upload form --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.credentials.upload_title') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.volunteering.credentials.upload', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" class="govuk-!-margin-bottom-8">
        @csrf

        <div class="govuk-form-group{{ $typeError ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="credential_type">{{ __('govuk_alpha_volunteering.credentials.type_label') }}</label>
            <div id="credential_type-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.credentials.type_hint') }}</div>
            @if ($typeError)
                <p id="credential_type-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.credentials.type_required') }}</p>
            @endif
            <select class="govuk-select" id="credential_type" name="credential_type" aria-describedby="credential_type-hint{{ $typeError ? ' credential_type-error' : '' }}" required>
                <option value="">{{ __('govuk_alpha_volunteering.credentials.type_select') }}</option>
                @foreach ($typeOptions as $opt)
                    <option value="{{ $opt }}">{{ $typeLabel($opt) }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-form-group{{ $fileError ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="document">{{ __('govuk_alpha_volunteering.credentials.file_label') }}</label>
            <div id="document-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.credentials.file_hint') }}</div>
            @if ($fileError)
                <p id="document-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __($errorMsg[$status]) }}</p>
            @endif
            <input class="govuk-file-upload" id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" aria-describedby="document-hint{{ $fileError ? ' document-error' : '' }}" required>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="expiry_date">{{ __('govuk_alpha_volunteering.credentials.expiry_label') }} {{ __('govuk_alpha_volunteering.shared.optional') }}</label>
            <div id="expiry_date-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.credentials.expiry_hint') }}</div>
            <input class="govuk-input govuk-input--width-10" id="expiry_date" name="expiry_date" type="date" aria-describedby="expiry_date-hint">
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.credentials.upload_button') }}</button>
    </form>

    {{-- Existing credentials --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.credentials.list_title') }}</h2>
    @if (empty($credentials))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.credentials.empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.credentials.list_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.credentials.col_type') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.credentials.col_status') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.credentials.col_expiry') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.credentials.col_uploaded') }}</th>
                    <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.credentials.delete_button') }}</span></th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($credentials as $credential)
                    @php
                        $credId = (int) ($credential['id'] ?? 0);
                        $credType = (string) ($credential['credential_type'] ?? '');
                        $statusValue = (string) ($credential['status'] ?? 'pending');
                        $sTag = $statusTag[$statusValue] ?? 'govuk-tag--grey';
                        $sLabelKey = $statusLabel[$statusValue] ?? 'govuk_alpha_volunteering.credentials.status_pending';
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $typeLabel($credType) }}</td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $sTag }}">{{ __($sLabelKey) }}</strong></td>
                        <td class="govuk-table__cell">{{ $formatDate($credential['expires_at'] ?? null) ?? __('govuk_alpha_volunteering.credentials.no_expiry') }}</td>
                        <td class="govuk-table__cell">{{ $formatDate($credential['created_at'] ?? null) ?? '—' }}</td>
                        <td class="govuk-table__cell">
                            @if ($credId > 0)
                                <form method="post" action="{{ route('govuk-alpha.volunteering.credentials.delete', ['tenantSlug' => $tenantSlug, 'id' => $credId]) }}">
                                    @csrf
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                        {{ __('govuk_alpha_volunteering.credentials.delete_button') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_volunteering.credentials.delete_for', ['type' => $typeLabel($credType)]) }}</span>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
