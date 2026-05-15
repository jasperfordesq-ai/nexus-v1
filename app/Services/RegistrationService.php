<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Core\Validator as NexusValidator;
use App\Events\UserRegistered;
use App\I18n\LocaleContext;
use App\Models\User;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
/**
 * RegistrationService — Laravel DI-based service for user registration.
 *
 * Eloquent/DI counterpart to legacy static registration logic.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class RegistrationService
{
    public function __construct(
        private readonly User $user,
        private readonly TenantSettingsService $tenantSettings,
        private readonly TurnstileService $turnstile,
        private readonly PwnedPasswordService $pwnedPassword,
    ) {}

    /**
     * Register a new user account.
     *
     * Honeypot: if $data['honeypot'] is non-empty, the submission is treated as
     * a bot probe — we silently return a success-shaped response without
     * creating any DB row or firing any events. Real users never see the
     * hidden field; bots that auto-fill every input give themselves away.
     *
     * @return array Registration result with user data and status flags
     */
    public function register(array $data, int $tenantId): array
    {
        // Bot honeypot — must run BEFORE validation so attackers can't
        // distinguish "honeypot triggered" from "validation failed" via the
        // error message returned. Mirrors the success response shape.
        if (!empty($data['honeypot'])) {
            Log::info('registration.honeypot_triggered', [
                'tenant_id' => $tenantId,
                'ip' => request()?->ip(),
                'ua' => substr((string) request()?->userAgent(), 0, 200),
                'honeypot_value' => substr((string) $data['honeypot'], 0, 100),
            ]);
            return [
                'user' => null,
                'requires_verification' => true,
                'message' => __('emails_misc.registration.success_message'),
            ];
        }

        // Cloudflare Turnstile verification. Token is the cf-turnstile-response
        // hidden input rendered by the widget on the client. Service no-ops
        // (returns true) when TURNSTILE_SECRET_KEY is unset, so dev/CI work
        // unchanged. A returned `error` field surfaces the failure to the
        // controller, which renders the form again so the user can retry.
        $turnstileToken = $data['turnstile_token'] ?? null;
        if (! $this->turnstile->verify($turnstileToken, request()?->ip())) {
            return ['error' => __('api.turnstile_failed')];
        }

        $validator = validator($data, [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
            'location'   => 'required|string|max:255',
            'phone'      => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || !NexusValidator::isPhone($value)) {
                        $fail(__('api.phone_invalid'));
                    }
                },
            ],
            'password'   => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ], [
            'location.required' => __('api.location_required'),
            'phone.required' => __('api.phone_required'),
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return ['error' => $errors];
        }

        // Have I Been Pwned k-anonymity check — reject passwords that
        // appear in known breach corpora. Defends against credential-
        // stuffing in a way password complexity rules cannot.
        if ($this->pwnedPassword->isPwned((string) $data['password'])) {
            return ['error' => __('api.password_pwned')];
        }

        $user = DB::transaction(function () use ($data, $tenantId) {
            // Retried up to 3x by the outer DB::transaction(..., 3) call below
            // to recover from MySQL 1213 deadlocks under registration spikes
            // (Fixes NEXUS-PHP-M).
            // Check uniqueness inside the transaction to prevent race conditions
            // where two concurrent registrations with the same email both pass the check
            $exists = $this->user->newQuery()
                ->where('email', strtolower(trim($data['email'])))
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                return null; // Duplicate — handled below
            }

            $user = $this->user->newInstance();
            $user->tenant_id = $tenantId;
            $user->first_name = trim($data['first_name']);
            $user->last_name = trim($data['last_name']);
            $user->email = strtolower(trim($data['email']));
            $user->password_hash = Hash::make($data['password']);
            $user->status = 'pending';
            // Defensive: never trust the column default for an auth-gating flag
            $user->onboarding_completed = false;
            if (Schema::hasColumn('users', 'newsletter_opt_in')) {
                $user->newsletter_opt_in = filter_var($data['newsletter_opt_in'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            // Welcome credits are granted during admin approval (AdminUsersController::grantWelcomeCredits)
            // NOT at registration time — to avoid double-crediting on tenants with admin_approval enabled
            $user->balance = 0;

            // Optional fields from frontend
            $user->phone = preg_replace('/[\s\-\(\)\.]/', '', trim((string) $data['phone']));
            $user->location = trim((string) $data['location']);
            if (!empty($data['latitude'])) {
                $user->latitude = (float) $data['latitude'];
            }
            if (!empty($data['longitude'])) {
                $user->longitude = (float) $data['longitude'];
            }
            if (!empty($data['profile_type'])) {
                $user->profile_type = $data['profile_type'];
            }
            if (!empty($data['organization_name'])) {
                $user->organization_name = $data['organization_name'];
            }

            $user->save();

            return $user;
        }, 3);

        if ($user === null) {
            return ['error' => __('emails_misc.registration.error_generic')];
        }

        // Dispatch UserRegistered event (triggers welcome notification, etc.)
        try {
            event(new UserRegistered($user, $tenantId));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('UserRegistered event dispatch failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'requires_verification' => true,
            'message' => __('emails_misc.registration.success_message'),
        ];
    }

    /**
     * Verify a user's email with the verification token.
     */
    public function verifyEmail(string $token): bool
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('verification_token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $user) {
            return false;
        }

        // Honour the tenant's admin_approval toggle. When ON, email
        // verification clears the email-verify gate but the account stays
        // pending until an admin promotes it (was previously promoted to
        // 'active' unconditionally — the toggle's "must be approved before
        // activation" label was effectively a no-op for the alpha frontend).
        $requiresAdminApproval = $this->tenantSettings
            ->requiresAdminApproval((int) $user->tenant_id);

        $user->update([
            'status'             => $requiresAdminApproval ? 'pending' : 'active',
            'verification_token' => null,
            'email_verified_at'  => now(),
        ]);

        return true;
    }

    /**
     * Resend verification email by regenerating the token.
     *
     * @return string|null The new verification token, or null if user not found.
     */
    public function resendVerification(string $email, int $tenantId = 0): ?string
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('email', strtolower(trim($email)))
            ->where('status', 'pending')
            ->first();

        if (! $user) {
            return null;
        }

        $token = Str::random(64);
        $user->update(['verification_token' => $token]);

        // Send the verification email
        try {
            LocaleContext::withLocale($user, function () use ($user, $token) {
                $appUrl = TenantContext::getFrontendUrl();
                $basePath = TenantContext::getSlugPrefix();
                $verifyUrl = $appUrl . $basePath . '/verify-email?token=' . $token;

                $tenantName = 'Project NEXUS';
                try {
                    $tenant = TenantContext::get();
                    $tenantName = $tenant['name'] ?? 'Project NEXUS';
                } catch (\Throwable $e) {
                    // Use default
                }

                $firstName = $user->first_name ?? __('emails.common.fallback_name');

                $html = \App\Core\EmailTemplateBuilder::make()
                    ->theme('brand')
                    ->title(__('emails_misc.registration.verify_title'))
                    ->greeting(__('emails_misc.registration.verify_greeting', ['name' => $firstName, 'tenant' => $tenantName]))
                    ->paragraph(__('emails_misc.registration.verify_body'))
                    ->button(__('emails_misc.registration.verify_cta'), $verifyUrl)
                    ->render();

                $mailer = Mailer::forCurrentTenant();
                $mailer->send($user->email, __('emails_misc.registration.verify_subject', ['tenant' => $tenantName]), $html);
            });
        } catch (\Throwable $e) {
            Log::warning('RegistrationService: Failed to send verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $token;
    }
}
