{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@component('mail::message')
# {{ __('emails_provisioning.rejection.title') }}

@isset($name)
{{ __('emails.common.greeting', ['name' => $name]) }}
@endisset

{{ __('emails_provisioning.rejection.body', ['org' => $orgName]) }}

@isset($reason)
@component('mail::panel')
**{{ __('emails_provisioning.rejection.reason_label') }}:** {{ $reason }}
@endcomponent
@endisset

{{ __('emails_provisioning.rejection.followup') }}

@component('mail::subcopy')
{{ config('app.name') }}
@endcomponent
@endcomponent
