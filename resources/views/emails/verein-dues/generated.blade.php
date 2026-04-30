{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@component('mail::message')
# {{ __('emails.verein_dues.generated_title', ['year' => $year]) }}

{{ __('emails.common.greeting_name', ['name' => $name]) }}

{{ __('emails.verein_dues.generated_body', ['year' => $year, 'amount' => $amount, 'organization' => $organization]) }}

@component('mail::button', ['url' => $url, 'color' => 'primary'])
{{ __('emails.verein_dues.cta_pay') }}
@endcomponent

{{ __('emails.common.signoff') }}
@endcomponent
