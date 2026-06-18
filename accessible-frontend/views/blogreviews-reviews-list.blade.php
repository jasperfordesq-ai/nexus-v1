{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $tab = in_array($reviewsTab ?? 'received', ['received', 'given'], true) ? $reviewsTab : 'received';
        $items = is_array($reviewsItems ?? null) ? $reviewsItems : [];
        $hasMore = (bool) ($reviewsHasMore ?? false);
        $cursor = $reviewsCursor ?? null;
        $isFirstPage = (bool) ($isFirstPage ?? true);
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $otherName = function ($r, array $keys): string {
            foreach ($keys as $k) {
                $n = trim((string) ($r[$k]['name'] ?? ''));
                if ($n !== '') { return $n; }
            }
            return __('govuk_alpha_blogreviews.reviews_list.unknown_member');
        };
        $reviewsHref = \Illuminate\Support\Facades\Route::has('govuk-alpha.reviews.index')
            ? route('govuk-alpha.reviews.index', ['tenantSlug' => $tenantSlug])
            : null;
    @endphp

    @if ($reviewsHref)
        <a class="govuk-back-link" href="{{ $reviewsHref }}">{{ __('govuk_alpha_blogreviews.reviews_list.back_to_reviews') }}</a>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_blogreviews.reviews_list.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_blogreviews.reviews_list.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_blogreviews.reviews_list.description') }}</p>

    {{-- Tab selector (received / given) as a no-JS GET form --}}
    <form method="get" action="{{ route('govuk-alpha.blogreviews.reviews.list', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_blogreviews.reviews_list.tab_legend') }}</legend>
            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="tab-received" name="tab" type="radio" value="received" @if ($tab === 'received') checked @endif>
                    <label class="govuk-label govuk-radios__label" for="tab-received">{{ __('govuk_alpha_blogreviews.reviews_list.received_tab') }}</label>
                </div>
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="tab-given" name="tab" type="radio" value="given" @if ($tab === 'given') checked @endif>
                    <label class="govuk-label govuk-radios__label" for="tab-given">{{ __('govuk_alpha_blogreviews.reviews_list.given_tab') }}</label>
                </div>
            </div>
        </fieldset>
        <button class="govuk-button govuk-button--secondary govuk-!-margin-top-2 govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.reviews_list.show') }}</button>
    </form>

    @if (empty($items))
        <div class="govuk-inset-text">
            <p class="govuk-body">
                {{ $tab === 'given' ? __('govuk_alpha_blogreviews.reviews_list.given_empty') : __('govuk_alpha_blogreviews.reviews_list.received_empty') }}
            </p>
        </div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($items as $r)
                @php
                    if ($tab === 'given') {
                        $name = $otherName($r, ['receiver', 'reviewee', 'user']);
                        $headingKey = 'govuk_alpha_blogreviews.reviews_list.for_label';
                    } else {
                        $name = ($r['is_anonymous'] ?? false) ? __('govuk_alpha_blogreviews.reviews_list.anonymous') : $otherName($r, ['reviewer', 'user']);
                        $headingKey = 'govuk_alpha_blogreviews.reviews_list.by_label';
                    }
                    $rating = max(0, min(5, (int) ($r['rating'] ?? 0)));
                    $reviewId = (int) ($r['id'] ?? 0);
                @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-3">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __($headingKey, ['name' => $name]) }}</h2>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        <progress value="{{ $rating }}" max="5" aria-label="{{ __('govuk_alpha_blogreviews.reviews_list.rating_aria', ['value' => $rating]) }}">{{ $rating }}/5</progress>
                        <span>{{ __('govuk_alpha_blogreviews.reviews_list.rating_label', ['value' => $rating]) }}</span>
                        @if ($d = $dateFmt($r['created_at'] ?? null)) <span aria-hidden="true">·</span> {{ $d }}@endif
                    </p>
                    @if (trim((string) ($r['comment'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ $r['comment'] }}</p>
                    @endif
                    @if ($reviewId > 0)
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <a class="govuk-link" href="{{ route('govuk-alpha.blogreviews.reviews.comments', ['tenantSlug' => $tenantSlug, 'id' => $reviewId]) }}">{{ __('govuk_alpha_blogreviews.reviews_list.moderate_link') }}</a>
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($hasMore && $cursor)
            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button"
               href="{{ route('govuk-alpha.blogreviews.reviews.list', ['tenantSlug' => $tenantSlug, 'tab' => $tab, 'cursor' => $cursor]) }}">
                {{ __('govuk_alpha_blogreviews.reviews_list.load_more') }}
            </a>
        @endif
    @endif
@endsection
