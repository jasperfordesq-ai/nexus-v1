{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $s = $show ?? null;
        $oldVal = function (string $key, $fallback = '') use ($s) {
            $current = old($key);
            if ($current !== null) {
                return $current;
            }
            if (is_array($s) && array_key_exists($key, $s)) {
                return $s[$key];
            }
            return $fallback;
        };
        $formErrors = session('commercePodcastErrors', []);
        $showId = is_array($s) ? (int) ($s['id'] ?? 0) : 0;
        $sStatus = is_array($s) ? (string) ($s['status'] ?? 'draft') : 'draft';
        $isPublished = $sStatus === 'published';
        $episodes = $episodes ?? [];
        $visibilityLabels = [
            'public' => __('govuk_alpha_commerce.podcast_studio.visibility_public'),
            'members' => __('govuk_alpha_commerce.podcast_studio.visibility_members'),
            'private' => __('govuk_alpha_commerce.podcast_studio.visibility_private'),
        ];
        $episodeVisibilityLabels = [
            'inherit' => __('govuk_alpha_commerce.podcast_studio.episode_visibility_inherit'),
            'public' => __('govuk_alpha_commerce.podcast_studio.visibility_public'),
            'members' => __('govuk_alpha_commerce.podcast_studio.visibility_members'),
            'private' => __('govuk_alpha_commerce.podcast_studio.visibility_private'),
        ];
        $episodeTypeLabels = [
            'full' => __('govuk_alpha_commerce.podcast_studio.episode_type_full'),
            'trailer' => __('govuk_alpha_commerce.podcast_studio.episode_type_trailer'),
            'bonus' => __('govuk_alpha_commerce.podcast_studio.episode_type_bonus'),
        ];
        $epStatusLabels = [
            'published' => __('govuk_alpha_commerce.podcast_studio.episode_status_published'),
            'draft' => __('govuk_alpha_commerce.podcast_studio.episode_status_draft'),
            'archived' => __('govuk_alpha_commerce.podcast_studio.episode_status_archived'),
        ];
        $epStatusTags = [
            'published' => 'govuk-tag--green',
            'draft' => 'govuk-tag--grey',
            'archived' => 'govuk-tag--red',
        ];
        $statusMessages = [
            'show-created' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_created'), 'error' => false],
            'show-saved' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_saved'), 'error' => false],
            'show-save-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_save_failed'), 'error' => true],
            'show-published' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_published'), 'error' => false],
            'show-pending-review' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_pending_review'), 'error' => false],
            'show-publish-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_publish_failed'), 'error' => true],
            'episode-added' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_added'), 'error' => false],
            'episode-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_failed'), 'error' => true],
            'episode-title-missing' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_title_missing'), 'error' => true],
            'episode-audio-missing' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_audio_missing'), 'error' => true],
            'episode-invalid-audio' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_invalid_audio'), 'error' => true],
            'episode-saved' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_saved'), 'error' => false],
            'episode-save-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_failed'), 'error' => true],
            'episode-published' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_published'), 'error' => false],
            'episode-publish-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_publish_failed'), 'error' => true],
            'episode-deleted' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_deleted'), 'error' => false],
            'episode-delete-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_episode_delete_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
        $epStoreAction = $episodeStoreAction ?? route('govuk-alpha.podcasts.studio.episodes.store', ['tenantSlug' => $tenantSlug, 'id' => $showId]);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.podcasts.studio', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.podcast_studio.back_to_studio') }}</a>

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'browse'])

    @if ($statusEntry !== null)
        @if ($statusEntry['error'])
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusEntry['msg'] }}</li></ul></div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
                </div>
            </div>
        @endif
    @endif

    @if (!empty($formErrors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($formErrors as $msg)
                            <li><a href="#title">{{ $msg }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.podcast_studio.title_edit') }}</h1>

    @if (!$isPublished)
        <p class="govuk-body">{{ __('govuk_alpha_commerce.podcast_studio.publish_hint') }}</p>
    @endif

    <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="title">{{ __('govuk_alpha_commerce.podcast_studio.show_title_label') }}</label>
            <input class="govuk-input" id="title" name="title" type="text" maxlength="200" value="{{ $oldVal('title') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="summary">{{ __('govuk_alpha_commerce.podcast_studio.summary_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input" id="summary" name="summary" type="text" maxlength="600" value="{{ $oldVal('summary') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.podcast_studio.description_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <textarea class="govuk-textarea" id="description" name="description" rows="5">{{ $oldVal('description') }}</textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="category">{{ __('govuk_alpha_commerce.podcast_studio.category_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input govuk-input--width-20" id="category" name="category" type="text" maxlength="120" value="{{ $oldVal('category') }}">
        </div>

        <h2 class="govuk-heading-m">{{ __('govuk_alpha_commerce.podcast_studio.rss_metadata_heading') }}</h2>

        @if (!empty($s['artwork_url']))
            <img src="{{ $s['artwork_url'] }}" alt="" width="160" height="160" class="govuk-!-margin-bottom-3">
        @endif
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="artwork">{{ __('govuk_alpha_commerce.podcast_studio.artwork_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="artwork-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.artwork_replace_hint') }}</div>
            <input class="govuk-file-upload" id="artwork" name="artwork" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="artwork-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="language">{{ __('govuk_alpha_commerce.podcast_studio.language_label') }}</label>
            <div id="language-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.language_hint') }}</div>
            <input class="govuk-input govuk-input--width-10" id="language" name="language" type="text" maxlength="20" value="{{ $oldVal('language') }}" aria-describedby="language-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="author_name">{{ __('govuk_alpha_commerce.podcast_studio.author_name_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input" id="author_name" name="author_name" type="text" maxlength="200" value="{{ $oldVal('author_name') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="owner_email">{{ __('govuk_alpha_commerce.podcast_studio.owner_email_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="owner_email-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.owner_email_hint') }}</div>
            <input class="govuk-input" id="owner_email" name="owner_email" type="email" maxlength="320" value="{{ $oldVal('owner_email') }}" aria-describedby="owner_email-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="copyright">{{ __('govuk_alpha_commerce.podcast_studio.copyright_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input" id="copyright" name="copyright" type="text" maxlength="300" value="{{ $oldVal('copyright') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="funding_url">{{ __('govuk_alpha_commerce.podcast_studio.funding_url_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input" id="funding_url" name="funding_url" type="url" inputmode="url" value="{{ $oldVal('funding_url') }}">
        </div>

        <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-6" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="explicit" name="explicit" type="checkbox" value="1" @checked((bool) $oldVal('explicit', false))>
                <label class="govuk-label govuk-checkboxes__label" for="explicit">{{ __('govuk_alpha_commerce.podcast_studio.explicit_label') }}</label>
            </div>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.podcast_studio.visibility_label') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach (($visibilities ?? array_keys($visibilityLabels)) as $idx => $vis)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}" name="visibility" type="radio" value="{{ $vis }}" @checked((string) $oldVal('visibility', 'public') === $vis)>
                            <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}">{{ $visibilityLabels[$vis] ?? $vis }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.submit_edit') }}</button>
    </form>

    {{-- Episodes --}}
    <h2 class="govuk-heading-m govuk-!-margin-top-8">{{ __('govuk_alpha_commerce.podcast_studio.episodes_heading') }}</h2>

    @if (empty($episodes))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.podcast_studio.no_episodes') }}</p>
    @else
        <ul class="govuk-list">
            @foreach ($episodes as $ep)
                @php $epStatus = (string) ($ep['status'] ?? 'draft'); @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-3">
                    <div class="nexus-alpha-module-row">
                        <span>
                            @if (!empty($ep['episode_number']))<span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_commerce.podcast_studio.episode_number_short', ['number' => (int) $ep['episode_number']]) }} — </span>@endif
                            {{ $ep['title'] ?? '' }}
                        </span>
                        <strong class="govuk-tag {{ $epStatusTags[$epStatus] ?? 'govuk-tag--grey' }}">{{ $epStatusLabels[$epStatus] ?? $epStatus }}</strong>
                    </div>
                    <div class="nexus-alpha-actions govuk-!-margin-top-2">
                        @if ($epStatus !== 'published')
                            <form method="post" action="{{ route('govuk-alpha.podcasts.studio.episodes.publish', ['tenantSlug' => $tenantSlug, 'id' => $showId, 'episodeId' => (int) ($ep['id'] ?? 0)]) }}" class="govuk-!-display-inline">
                                @csrf
                                <button class="govuk-button govuk-button--secondary govuk-button--small" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.episode_action_publish') }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.podcasts.studio.episodes.delete', ['tenantSlug' => $tenantSlug, 'id' => $showId, 'episodeId' => (int) ($ep['id'] ?? 0)]) }}" class="govuk-!-display-inline">
                            @csrf
                            <button class="govuk-button govuk-button--warning govuk-button--small" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.episode_action_delete') }}</button>
                        </form>
                    </div>
                    <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="govuk-!-margin-top-4" novalidate>
                        @csrf
                        <input type="hidden" name="episode_id" value="{{ (int) ($ep['id'] ?? 0) }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="episode_title_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_title_label') }}</label>
                            <input class="govuk-input" id="episode_title_{{ (int) ($ep['id'] ?? 0) }}" name="episode_title" type="text" maxlength="200" value="{{ $ep['title'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="episode_summary_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_summary_label') }}</label>
                            <input class="govuk-input" id="episode_summary_{{ (int) ($ep['id'] ?? 0) }}" name="episode_summary" type="text" maxlength="600" value="{{ $ep['summary'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="episode_description_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_description_label') }}</label>
                            <textarea class="govuk-textarea" id="episode_description_{{ (int) ($ep['id'] ?? 0) }}" name="episode_description" rows="3">{{ $ep['description'] ?? '' }}</textarea>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="episode_number_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_number_label') }}</label>
                            <input class="govuk-input govuk-input--width-5" id="episode_number_{{ (int) ($ep['id'] ?? 0) }}" name="episode_number" type="text" inputmode="numeric" value="{{ $ep['episode_number'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="season_number_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.season_number_label') }}</label>
                            <input class="govuk-input govuk-input--width-5" id="season_number_{{ (int) ($ep['id'] ?? 0) }}" name="season_number" type="text" inputmode="numeric" value="{{ $ep['season_number'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="duration_seconds_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.duration_seconds_label') }}</label>
                            <input class="govuk-input govuk-input--width-10" id="duration_seconds_{{ (int) ($ep['id'] ?? 0) }}" name="duration_seconds" type="text" inputmode="numeric" value="{{ $ep['duration_seconds'] ?? '' }}">
                        </div>
                        @if (!empty($ep['audio_url']))
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="episode_audio_url_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.audio_url_label') }}</label>
                                <input class="govuk-input" id="episode_audio_url_{{ (int) ($ep['id'] ?? 0) }}" name="audio_url" type="url" value="{{ $ep['audio_url'] }}">
                            </div>
                        @endif
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="audio_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.audio_file_label') }}</label>
                            <div class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.audio_replace_hint') }}</div>
                            <input class="govuk-file-upload" id="audio_{{ (int) ($ep['id'] ?? 0) }}" name="audio" type="file" accept="audio/*">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="audio_mime_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.audio_mime_label') }}</label>
                            <input class="govuk-input govuk-input--width-20" id="audio_mime_{{ (int) ($ep['id'] ?? 0) }}" name="audio_mime" type="text" maxlength="120" value="{{ $ep['audio_mime'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="audio_bytes_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.audio_bytes_label') }}</label>
                            <input class="govuk-input govuk-input--width-10" id="audio_bytes_{{ (int) ($ep['id'] ?? 0) }}" name="audio_bytes" type="text" inputmode="numeric" value="{{ $ep['audio_bytes'] ?? '' }}">
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="episode_type_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_type_label') }}</label>
                            <select class="govuk-select" id="episode_type_{{ (int) ($ep['id'] ?? 0) }}" name="episode_type">
                                @foreach ($episodeTypeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($ep['episode_type'] ?? 'full') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="episode_visibility_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_visibility_label') }}</label>
                            <select class="govuk-select" id="episode_visibility_{{ (int) ($ep['id'] ?? 0) }}" name="episode_visibility">
                                @foreach (array_values(array_unique(array_merge($episodeVisibilities ?? ['inherit', 'public'], [(string) ($ep['visibility'] ?? 'inherit')]))) as $value)
                                    <option value="{{ $value }}" @selected((string) ($ep['visibility'] ?? 'inherit') === $value)>{{ $episodeVisibilityLabels[$value] ?? $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="episode_explicit_{{ (int) ($ep['id'] ?? 0) }}" name="episode_explicit" type="checkbox" value="1" @checked((bool) ($ep['explicit'] ?? false))>
                                <label class="govuk-label govuk-checkboxes__label" for="episode_explicit_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.episode_explicit_label') }}</label>
                            </div>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="scheduled_for_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.scheduled_for_label') }}</label>
                            <input class="govuk-input" id="scheduled_for_{{ (int) ($ep['id'] ?? 0) }}" name="scheduled_for" type="datetime-local" value="{{ $ep['scheduled_for'] ?? '' }}">
                        </div>
                        @if ($transcriptsEnabled ?? false)
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="transcript_language_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.transcript_language_label') }}</label>
                                <input class="govuk-input govuk-input--width-10" id="transcript_language_{{ (int) ($ep['id'] ?? 0) }}" name="transcript_language" type="text" maxlength="20" value="{{ $ep['transcript_language'] ?? '' }}">
                            </div>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="transcript_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.transcript_label') }}</label>
                                <textarea class="govuk-textarea" id="transcript_{{ (int) ($ep['id'] ?? 0) }}" name="transcript" rows="8">{{ $ep['transcript'] ?? '' }}</textarea>
                            </div>
                        @endif
                        @if ($chaptersEnabled ?? false)
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="chapters_json_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.chapters_label') }}</label>
                                <div class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.chapters_hint') }}</div>
                                <textarea class="govuk-textarea" id="chapters_json_{{ (int) ($ep['id'] ?? 0) }}" name="chapters_json" rows="6">{{ $ep['chapters_json'] ?? '' }}</textarea>
                            </div>
                        @endif
                        @if (!empty($ep['cover_image_url']))
                            <img src="{{ $ep['cover_image_url'] }}" alt="" width="120" height="120" class="govuk-!-margin-bottom-3">
                        @endif
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="cover_{{ (int) ($ep['id'] ?? 0) }}">{{ __('govuk_alpha_commerce.podcast_studio.cover_label') }}</label>
                            <input class="govuk-file-upload" id="cover_{{ (int) ($ep['id'] ?? 0) }}" name="cover" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                        <button class="govuk-button govuk-button--secondary govuk-button--small" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.submit_edit') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h3 class="govuk-heading-s govuk-!-margin-top-4">{{ __('govuk_alpha_commerce.podcast_studio.add_episode_heading') }}</h3>
    <form method="post" action="{{ $epStoreAction }}" enctype="multipart/form-data" novalidate class="govuk-!-margin-bottom-6">
        @csrf

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_title">{{ __('govuk_alpha_commerce.podcast_studio.episode_title_label') }}</label>
            <div id="episode_title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.episode_title_hint') }}</div>
            <input class="govuk-input" id="episode_title" name="episode_title" type="text" maxlength="200" aria-describedby="episode_title-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_slug">{{ __('govuk_alpha_commerce.podcast_studio.episode_slug_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="episode_slug-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.slug_hint') }}</div>
            <input class="govuk-input" id="episode_slug" name="episode_slug" type="text" maxlength="200" aria-describedby="episode_slug-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_number">{{ __('govuk_alpha_commerce.podcast_studio.episode_number_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="episode_number-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.episode_number_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="episode_number" name="episode_number" type="text" inputmode="numeric" aria-describedby="episode_number-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="season_number">{{ __('govuk_alpha_commerce.podcast_studio.season_number_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input govuk-input--width-5" id="season_number" name="season_number" type="text" inputmode="numeric">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="duration_seconds">{{ __('govuk_alpha_commerce.podcast_studio.duration_seconds_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input govuk-input--width-10" id="duration_seconds" name="duration_seconds" type="text" inputmode="numeric">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_summary">{{ __('govuk_alpha_commerce.podcast_studio.episode_summary_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="episode_summary-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.episode_summary_hint') }}</div>
            <input class="govuk-input" id="episode_summary" name="episode_summary" type="text" maxlength="600" aria-describedby="episode_summary-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_description">{{ __('govuk_alpha_commerce.podcast_studio.episode_description_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="episode_description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.episode_description_hint') }}</div>
            <textarea class="govuk-textarea" id="episode_description" name="episode_description" rows="3" aria-describedby="episode_description-hint"></textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="audio">{{ __('govuk_alpha_commerce.podcast_studio.audio_file_label') }}</label>
            <div id="audio-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.audio_file_hint') }}</div>
            <input class="govuk-file-upload" id="audio" name="audio" type="file" accept="audio/*" aria-describedby="audio-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="audio_url">{{ __('govuk_alpha_commerce.podcast_studio.audio_url_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="audio_url-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.audio_url_hint') }}</div>
            <input class="govuk-input" id="audio_url" name="audio_url" type="url" inputmode="url" aria-describedby="audio_url-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="audio_mime">{{ __('govuk_alpha_commerce.podcast_studio.audio_mime_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input govuk-input--width-20" id="audio_mime" name="audio_mime" type="text" maxlength="120">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="audio_bytes">{{ __('govuk_alpha_commerce.podcast_studio.audio_bytes_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <input class="govuk-input govuk-input--width-10" id="audio_bytes" name="audio_bytes" type="text" inputmode="numeric">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_type">{{ __('govuk_alpha_commerce.podcast_studio.episode_type_label') }}</label>
            <select class="govuk-select" id="episode_type" name="episode_type">
                @foreach ($episodeTypeLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="episode_visibility">{{ __('govuk_alpha_commerce.podcast_studio.episode_visibility_label') }}</label>
            <select class="govuk-select" id="episode_visibility" name="episode_visibility">
                @foreach (($episodeVisibilities ?? ['inherit', 'public']) as $value)
                    <option value="{{ $value }}">{{ $episodeVisibilityLabels[$value] ?? $value }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="episode_explicit" name="episode_explicit" type="checkbox" value="1">
                <label class="govuk-label govuk-checkboxes__label" for="episode_explicit">{{ __('govuk_alpha_commerce.podcast_studio.episode_explicit_label') }}</label>
            </div>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="scheduled_for">{{ __('govuk_alpha_commerce.podcast_studio.scheduled_for_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="scheduled_for-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.scheduled_for_hint') }}</div>
            <input class="govuk-input" id="scheduled_for" name="scheduled_for" type="datetime-local" aria-describedby="scheduled_for-hint">
        </div>

        @if ($transcriptsEnabled ?? false)
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="transcript_language">{{ __('govuk_alpha_commerce.podcast_studio.transcript_language_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <input class="govuk-input govuk-input--width-10" id="transcript_language" name="transcript_language" type="text" maxlength="20">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="transcript">{{ __('govuk_alpha_commerce.podcast_studio.transcript_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <textarea class="govuk-textarea" id="transcript" name="transcript" rows="8"></textarea>
            </div>
        @endif

        @if ($chaptersEnabled ?? false)
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="chapters_json">{{ __('govuk_alpha_commerce.podcast_studio.chapters_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="chapters_json-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.chapters_hint') }}</div>
                <textarea class="govuk-textarea" id="chapters_json" name="chapters_json" rows="6" aria-describedby="chapters_json-hint"></textarea>
            </div>
        @endif

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="cover">{{ __('govuk_alpha_commerce.podcast_studio.cover_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="cover-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.cover_hint') }}</div>
            <input class="govuk-file-upload" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="cover-hint">
        </div>

        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.add_episode_button') }}</button>
    </form>

    {{-- Publish + delete show --}}
    @if (!$isPublished)
        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.podcast_studio.action_publish_heading') }}</h2>
        <form method="post" action="{{ route('govuk-alpha.podcasts.studio.publish', ['tenantSlug' => $tenantSlug, 'id' => $showId]) }}">
            @csrf
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.action_publish') }}</button>
        </form>
    @endif

    <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.podcast_studio.delete_heading') }}</h2>
    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
            {{ __('govuk_alpha_commerce.podcast_studio.delete_warning') }}
        </strong>
    </div>
    <form method="post" action="{{ route('govuk-alpha.podcasts.studio.delete', ['tenantSlug' => $tenantSlug, 'id' => $showId]) }}">
        @csrf
        <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.action_delete') }}</button>
    </form>
@endsection
