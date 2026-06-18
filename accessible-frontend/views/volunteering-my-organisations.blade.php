{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $organizations = $organizations ?? [];
        $meta = $meta ?? ['has_more' => false, 'cursor' => null];
        $roleFilter = $roleFilter ?? null;
        $statusTag = [
            'approved' => 'govuk-tag--green',
            'active' => 'govuk-tag--green',
            'pending' => 'govuk-tag--yellow',
            'declined' => 'govuk-tag--red',
            'suspended' => 'govuk-tag--red',
        ];
        $statusLabelKey = [
            'approved' => 'volunteering.status_values.approved',
            'active' => 'volunteering.status_values.active',
            'pending' => 'volunteering.status_values.pending',
            'declined' => 'volunteering.status_values.declined',
        ];
        $statusLabel = function (string $value) use ($statusLabelKey): string {
            $key = $statusLabelKey[$value] ?? null;
            return ($key !== null && \Illuminate\Support\Facades\Lang::has("govuk_alpha.$key"))
                ? __("govuk_alpha.$key")
                : \Illuminate\Support\Str::headline($value);
        };
        $roleLabel = function (string $value): string {
            $key = "govuk_alpha.volunteering.roles.$value";
            return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : \Illuminate\Support\Str::headline($value);
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'organisations']) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha_volunteering.my_orgs.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.my_orgs.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.my_orgs.description') }}</p>

    {{-- Role filter --}}
    <form method="get" action="{{ route('govuk-alpha.volunteering.my-organisations', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="role">{{ __('govuk_alpha_volunteering.my_orgs.filter_legend') }}</label>
            <select class="govuk-select" id="role" name="role">
                <option value="">{{ __('govuk_alpha_volunteering.my_orgs.filter_all') }}</option>
                <option value="owner" @selected($roleFilter === 'owner')>{{ __('govuk_alpha_volunteering.my_orgs.filter_owner') }}</option>
                <option value="admin" @selected($roleFilter === 'admin')>{{ __('govuk_alpha_volunteering.my_orgs.filter_admin') }}</option>
                <option value="member" @selected($roleFilter === 'member')>{{ __('govuk_alpha_volunteering.my_orgs.filter_member') }}</option>
            </select>
        </div>
        <div class="govuk-button-group">
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_volunteering.my_orgs.filter_apply') }}</button>
            @if ($roleFilter !== null)
                <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.my-organisations', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.my_orgs.filter_clear') }}</a>
            @endif
        </div>
    </form>

    @if (empty($organizations))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_volunteering.my_orgs.empty') }}</p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.my_orgs.empty_cta') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($organizations as $organization)
                @php
                    $orgId = (int) ($organization['id'] ?? 0);
                    $orgName = (string) ($organization['name'] ?? '');
                    $statusValue = (string) ($organization['status'] ?? 'pending');
                    $roleValue = (string) ($organization['member_role'] ?? 'member');
                    $tagClass = $statusTag[$statusValue] ?? 'govuk-tag--grey';
                    $canManage = in_array($roleValue, ['owner', 'admin'], true);
                    $isApproved = in_array($statusValue, ['approved', 'active'], true);
                    $website = (string) ($organization['website'] ?? '');
                    $websiteScheme = $website !== '' ? parse_url($website, PHP_URL_SCHEME) : null;
                    $websiteHref = in_array($websiteScheme, ['http', 'https'], true) ? $website : null;
                @endphp
                @if ($orgId > 0)
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ $orgName }}</a>
                        </h2>
                        <p class="govuk-!-margin-bottom-3">
                            <strong class="govuk-tag {{ $tagClass }}">{{ $statusLabel($statusValue) }}</strong>
                        </p>
                        <dl class="govuk-summary-list govuk-!-margin-bottom-0">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.my_orgs.role_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $roleLabel($roleValue) }}</dd>
                            </div>
                            @if (!empty($organization['contact_email']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.my_orgs.contact_email') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $organization['contact_email'] }}</dd>
                                </div>
                            @endif
                            @if ($website !== '')
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.my_orgs.website') }}</dt>
                                    <dd class="govuk-summary-list__value">
                                        @if ($websiteHref)
                                            <a class="govuk-link" href="{{ $websiteHref }}">{{ $website }}</a>
                                        @else
                                            {{ $website }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($organization['description']))
                            <p class="govuk-body govuk-!-margin-top-3">{{ \Illuminate\Support\Str::limit(strip_tags((string) $organization['description']), 220) }}</p>
                        @endif

                        @if ($canManage && $isApproved)
                            <a class="govuk-button govuk-!-margin-top-2 govuk-!-margin-bottom-0" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">
                                {{ __('govuk_alpha_volunteering.my_orgs.manage_dashboard') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_volunteering.my_orgs.manage_dashboard_for', ['name' => $orgName]) }}</span>
                            </a>
                        @elseif ($canManage && !$isApproved)
                            <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-1">
                                <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha_volunteering.my_orgs.awaiting_approval') }}</strong>
                            </p>
                            <p class="govuk-hint govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.my_orgs.awaiting_approval_hint') }}</p>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>

        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha_volunteering.my_orgs.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.volunteering.my-organisations', array_filter(['tenantSlug' => $tenantSlug, 'role' => $roleFilter, 'cursor' => $meta['cursor']])) }}" rel="next">
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                        <span class="govuk-visually-hidden">:</span>
                        <span class="govuk-pagination__link-label">{{ __('govuk_alpha_volunteering.my_orgs.more_label') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
