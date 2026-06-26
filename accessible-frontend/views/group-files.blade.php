{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId      = (int) ($group['id'] ?? 0);
        $gName    = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_groups.files.title');
        $isAdmin  = (bool) ($isAdmin ?? false);
        $files    = is_array($files ?? null) ? $files : [];
        $status   = $status ?? null;

        $successStates = ['file-uploaded', 'file-deleted'];
        $errorStates   = [
            'file-upload-failed', 'file-too-large', 'file-type-invalid',
            'file-missing', 'file-delete-failed', 'file-forbidden', 'file-not-found',
        ];

        $formatBytes = function (int $bytes): string {
            if ($bytes >= 1_048_576) {
                return round($bytes / 1_048_576, 1) . ' MB';
            }
            if ($bytes >= 1_024) {
                return round($bytes / 1_024) . ' KB';
            }
            return $bytes . ' B';
        };

        $formatDate = fn ($value): ?string => $value
            ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y')
            : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha_groups.common.back_to_group') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_groups.files.caption', ['group' => $gName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_groups.files.title') }}</h1>

    {{-- ---- Status banners ---- --}}
    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="files-status-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="files-status-banner">{{ __('govuk_alpha_groups.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha_groups.states.' . $status) }}</p>
            </div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_groups.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>{{ __('govuk_alpha_groups.states.' . $status) }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body">{{ __('govuk_alpha_groups.files.intro') }}</p>

    {{-- ---- File list ---- --}}
    @if (count($files) === 0)
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_groups.files.empty') }}</p>
        </div>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_groups.files.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_groups.files.col_name') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_groups.files.col_size') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_groups.files.col_uploaded_by') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_groups.files.col_date') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_groups.files.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($files as $file)
                    @php
                        $fileId       = (int) ($file['id'] ?? 0);
                        $fileName     = (string) ($file['file_name'] ?? '');
                        $fileSize     = (int) ($file['file_size'] ?? 0);
                        $uploaderName = (string) ($file['uploader_name'] ?? '');
                        $uploadedAt   = $formatDate($file['created_at'] ?? null);
                        $uploadedById = (int) ($file['uploaded_by'] ?? 0);
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $fileName }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $formatBytes($fileSize) }}</td>
                        <td class="govuk-table__cell">{{ $uploaderName }}</td>
                        <td class="govuk-table__cell">{{ $uploadedAt }}</td>
                        <td class="govuk-table__cell">
                            <a class="govuk-link govuk-!-margin-right-2"
                               href="{{ route('govuk-alpha.groups.files.download', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'fileId' => $fileId]) }}"
                               aria-label="{{ __('govuk_alpha_groups.files.download_aria', ['name' => $fileName]) }}">
                                {{ __('govuk_alpha_groups.files.download_link') }}
                            </a>
                            @if ($isAdmin || ($currentUserId !== null && $currentUserId === $uploadedById))
                                <form method="POST"
                                      action="{{ route('govuk-alpha.groups.files.delete', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'fileId' => $fileId]) }}"
                                      style="display:inline">
                                    @csrf
                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0"
                                        aria-label="{{ __('govuk_alpha_groups.files.delete_aria', ['name' => $fileName]) }}">
                                        {{ __('govuk_alpha_groups.files.delete_button') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ---- Upload form (all members can upload; admins see it regardless) ---- --}}
    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_groups.files.upload_heading') }}</h2>

    <form method="POST"
          action="{{ route('govuk-alpha.groups.files.upload', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}"
          enctype="multipart/form-data"
          novalidate>
        @csrf

        <div class="govuk-form-group {{ in_array($status, ['file-missing', 'file-too-large', 'file-type-invalid', 'file-upload-failed'], true) ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="file-input">
                {{ __('govuk_alpha_groups.files.file_label') }}
            </label>
            <div id="file-input-hint" class="govuk-hint">{{ __('govuk_alpha_groups.files.file_hint') }}</div>
            @if (in_array($status, ['file-missing', 'file-too-large', 'file-type-invalid', 'file-upload-failed'], true))
                <p class="govuk-error-message" id="file-input-error">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span>
                    {{ __('govuk_alpha_groups.states.' . $status) }}
                </p>
            @endif
            <input class="govuk-file-upload {{ in_array($status, ['file-missing', 'file-too-large', 'file-type-invalid', 'file-upload-failed'], true) ? 'govuk-file-upload--error' : '' }}"
                   id="file-input" name="file" type="file"
                   aria-describedby="file-input-hint{{ in_array($status, ['file-missing', 'file-too-large', 'file-type-invalid', 'file-upload-failed'], true) ? ' file-input-error' : '' }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="file-folder">
                {{ __('govuk_alpha_groups.files.folder_label') }}
            </label>
            <div id="file-folder-hint" class="govuk-hint">{{ __('govuk_alpha_groups.files.folder_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="file-folder" name="folder" type="text"
                   maxlength="100" aria-describedby="file-folder-hint"
                   value="{{ old('folder') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="file-description">
                {{ __('govuk_alpha_groups.files.description_label') }}
            </label>
            <div id="file-description-hint" class="govuk-hint">{{ __('govuk_alpha_groups.files.description_hint') }}</div>
            <textarea class="govuk-textarea" id="file-description" name="description" rows="2"
                      maxlength="500" aria-describedby="file-description-hint">{{ old('description') }}</textarea>
        </div>

        <button type="submit" class="govuk-button">
            {{ __('govuk_alpha_groups.files.submit_upload') }}
        </button>
    </form>
@endsection
