{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@component('mail::message')
# {{ __('emails_provisioning.welcome.title') }}

@isset($name)
{{ __('emails.common.greeting', ['name' => $name]) }}
@endisset

{{ __('emails_provisioning.welcome.body', ['name' => $tenantName]) }}

@component('mail::panel')
**{{ __('emails_provisioning.welcome.tenant_url_label') }}:** {{ $tenantUrl }}
**{{ __('emails_provisioning.welcome.login_url_label') }}:** {{ $loginUrl }}
**{{ __('emails_provisioning.welcome.admin_email_label') }}:** {{ $adminEmail }}
@isset($tempPassword)
**{{ __('emails_provisioning.welcome.temp_password_label') }}:** {{ $tempPassword }}
@endisset
@endcomponent

{{ __('emails_provisioning.welcome.next_steps') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails_provisioning.welcome.cta') }}
@endcomponent

@component('mail::subcopy')
{{ config('app.name') }}
@endcomponent
@endcomponent
