{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@component('mail::message')
# {{ __('emails.appreciation.title') }}

{{ __('emails.appreciation.body', ['sender' => $senderName ?? '']) }}

@component('mail::panel')
{{ $message ?? '' }}
@endcomponent

@if (!empty($isPublic))
{{ __('emails.appreciation.public_note') }}
@else
{{ __('emails.appreciation.private_note') }}
@endif

@component('mail::button', ['url' => $url ?? '#'])
{{ __('emails.appreciation.cta_view') }}
@endcomponent

{{ __('emails.common.signoff', ['app' => config('app.name')]) }}
@endcomponent
