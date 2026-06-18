{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $orgs = isset($organisations) && is_array($organisations) ? $organisations : [];

        $manageable = array_values(array_filter($orgs, static function ($o) {
            $status = is_array($o) ? (string) ($o['status'] ?? '') : '';
            $role = is_array($o) ? (string) ($o['member_role'] ?? '') : '';
            return in_array($status, ['approved', 'active'], true) && in_array($role, ['owner', 'admin'], true);
        }));
        $pending = array_values(array_filter($orgs, static function ($o) {
            $status = is_array($o) ? (string) ($o['status'] ?? '') : '';
            return $status === 'pending';
        }));

        $roleLabel = function (string $role): string {
            return match ($role) {
                'owner' => __('govuk_alpha_organisations.manage.role_owner'),
                'admin' => __('govuk_alpha_organisations.manage.role_admin'),
                default => __('govuk_alpha_organisations.manage.role_member'),
            };
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.browse', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.common.back_to_organisations') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_organisations.common.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_organisations.manage.title') }}</h1>

    @if (!empty($error))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_organisations.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_organisations.common.load_error') }}</li></ul>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_organisations.manage.description') }}</p>

    @if (empty($manageable) && empty($pending))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha_organisations.manage.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_organisations.manage.empty_body') }}</p>
            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.organisations.register.form', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.manage.register_cta') }}</a>
        </div>
    @else
        @if (!empty($manageable))
            <div class="nexus-alpha-card-list">
                @foreach ($manageable as $o)
                    @continue(empty($o['id']))
                    @php
                        $oName = trim((string) ($o['name'] ?? '')) ?: __('govuk_alpha_organisations.manage.title');
                        $role = (string) ($o['member_role'] ?? 'member');
                    @endphp
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $oName }}</h2>
                        <dl class="nexus-alpha-inline-list nexus-alpha-meta">
                            <div>
                                <dt>{{ __('govuk_alpha_organisations.manage.role_label') }}</dt>
                                <dd>{{ $roleLabel($role) }}</dd>
                            </div>
                        </dl>
                        <div class="nexus-alpha-actions">
                            <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" href="{{ route('govuk-alpha.volunteering.org.manage', ['tenantSlug' => $tenantSlug, 'id' => $o['id']]) }}">{{ __('govuk_alpha_organisations.manage.manage_button') }}</a>
                            <a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $o['id']]) }}">{{ __('govuk_alpha_organisations.common.view_organisation') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if (!empty($pending))
            <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_organisations.manage.status_pending') }}</h2>
            <div class="nexus-alpha-card-list">
                @foreach ($pending as $o)
                    @continue(empty($o['id']))
                    @php $oName = trim((string) ($o['name'] ?? '')) ?: __('govuk_alpha_organisations.manage.title'); @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $oName }}</h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha_organisations.manage.status_pending') }}</strong>
                        </p>
                        <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha_organisations.manage.status_pending_note') }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
@endsection
