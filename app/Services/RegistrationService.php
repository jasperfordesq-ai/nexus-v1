<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplate;
use App\Core\TenantContext;
use App\Core\Validator as NexusValidator;
use App\Events\UserRegistered;
use App\I18n\LocaleContext;
use App\Models\User;
use App\Services\DisposableEmailService;
use App\Services\MxRecordValidator;
use App\Services\Identity\InviteCodeService;
use App\Services\Identity\RegistrationPolicyService;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
        private readonly PwnedPasswordService $pwnedPassword,
        private readonly DisposableEmailService $disposableEmail,
        private readonly MxRecordValidator $mxValidator,
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
        // Reverted from the multi-field decoy version 2026-05-16: browsers
        // (Chrome especially) autofill semantic field names like
        // `confirm_email` and `address_line_2` regardless of autocomplete=off,
        // which silently blocked legitimate users.
        $honeypotFields = ['honeypot', 'website'];
        foreach ($honeypotFields as $field) {
            if (!empty($data[$field])) {
                Log::info('registration.honeypot_triggered', [
                    'tenant_id' => $tenantId,
                    'ip' => request()?->ip(),
                    'ua' => substr((string) request()?->userAgent(), 0, 200),
                    'field' => $field,
                    'honeypot_value' => substr((string) $data[$field], 0, 100),
                ]);
                return [
                    'user' => null,
                    'requires_verification' => true,
                    'message' => __('emails_misc.registration.success_message'),
                ];
            }
        }

        // Minimum-time bot gate — form must take >= 5 seconds. Mirrors the
        // React frontend's client check so the server cannot be bypassed by
        // a script that POSTs directly. Fails silently (same success-shaped
        // response as the honeypot) so bots can't distinguish this from a
        // real registration.
        if (isset($data['form_started_at']) && is_numeric($data['form_started_at'])) {
            $startedAtMs = (int) $data['form_started_at'];
            $nowMs = (int) (microtime(true) * 1000);
            if ($startedAtMs > 0 && ($nowMs - $startedAtMs) < 5000) {
                Log::info('registration.too_fast', [
                    'tenant_id' => $tenantId,
                    'ip' => request()?->ip(),
                    'elapsed_ms' => $nowMs - $startedAtMs,
                ]);
                return [
                    'user' => null,
                    'requires_verification' => true,
                    'message' => __('emails_misc.registration.success_message'),
                ];
            }
        }

        // Registration Turnstile gate removed 2026-05-16 — member feedback
        // showed the widget was deterring legitimate sign-ups. Bot defence on
        // this path is the honeypot field + min-form-time gate + per-IP
        // route throttle (3/5min) + admin-approval gate.

        // Per-tenant hourly circuit breaker. If a single tenant gets a flood
        // of signups in one hour (default >20), pause further signups for
        // that tenant for 1 hour and warn the admin. Auto-resumes when the
        // pause flag's TTL elapses; admin can also clear it manually via
        // POST /api/v2/admin/registration/resume-signups.
        //
        // Why this matters: even with per-IP caps in place, a determined
        // attacker on a rotating-proxy network can grind out hundreds of
        // accounts in an hour. The breaker is the last-line containment —
        // worst case the tenant loses 1 hour of legitimate signups, which
        // is way better than waking up to 10,000 fake accounts.
        $tenantHourlyCap = (int) (getenv('REGISTRATION_TENANT_HOURLY_CAP') ?: 20);
        $breakerKey = 'register_tenant_breaker:' . $tenantId;
        $tenantHourlyKey = 'register_tenant_hourly:' . $tenantId;
        if ($tenantHourlyCap > 0) {
            if (\Illuminate\Support\Facades\Cache::get($breakerKey)) {
                $retryAfter = (int) \Illuminate\Support\Facades\Cache::get($breakerKey . ':ttl', 3600);
                Log::info('registration.tenant_paused', [
                    'tenant_id' => $tenantId,
                    'ip' => request()?->ip(),
                ]);
                return [
                    'error' => __('api.registration_tenant_paused'),
                    'code'  => 'REGISTRATION_TENANT_PAUSED',
                    'status' => 503,
                    'retry_after' => $retryAfter,
                ];
            }
        }

        // Per-IP daily cap on SUCCESSFUL registrations. Stacks on top of the
        // existing 3/5min route throttle: that one caps raw request volume,
        // this one caps how many accounts a single IP can actually create
        // per 24h. Configurable via env REGISTRATION_DAILY_CAP_PER_IP (set
        // to 0 to disable). Default 5 — comfortably above any plausible
        // household-shared-IP use case, brutal for anyone trying to grind
        // out hundreds of fake accounts overnight from a single residential
        // proxy. The counter only increments on a successful create, so a
        // user typing wrong passwords doesn't burn quota.
        $dailyCap = (int) (getenv('REGISTRATION_DAILY_CAP_PER_IP') ?: 5);
        $ip = request()?->ip() ?: '0.0.0.0';
        $dailyCapKey = 'register_success_ip:' . $ip;
        if ($dailyCap > 0 && RateLimiter::tooManyAttempts($dailyCapKey, $dailyCap)) {
            $retryAfter = RateLimiter::availableIn($dailyCapKey);
            Log::info('registration.daily_cap_exceeded', [
                'tenant_id' => $tenantId,
                'ip' => $ip,
                'retry_after_s' => $retryAfter,
            ]);
            return [
                'error' => __('api.registration_daily_limit'),
                'code'  => 'REGISTRATION_DAILY_LIMIT',
                'status' => 429,
                'retry_after' => $retryAfter,
            ];
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
            // NIST SP 800-63B: length is the primary signal, character-class
            // rules ARE NOT (they push users toward predictable patterns).
            // Real defence is the HIBP breach check that runs immediately
            // after this validator.
            'password'   => ['required', 'string', Password::min(12)],
            // Terms acceptance is a legal-compliance gate; both frontends
            // present it as a mandatory checkbox. Enforce server-side so a
            // scripted submission cannot bypass it.
            'terms_accepted' => 'accepted',
            // profile_type is enumerated; organization_name required when
            // profile_type=organisation.
            'profile_type' => 'sometimes|string|in:individual,organisation',
            'organization_name' => 'required_if:profile_type,organisation|nullable|string|max:255',
            // Verified-location gate (anti-fraud). OPT-IN per tenant /
            // platform via env REGISTRATION_REQUIRE_VERIFIED_LOCATION (default
            // off). When off, lat/lng are accepted if supplied but plain-text
            // locations still pass. When on, the user must pick a place
            // suggestion. Reverted to opt-in 2026-05-16 because the hard
            // requirement broke signups on every tenant without Google Maps
            // configured (no autocomplete → never any lat/lng → 100% reject).
            // The Null-Island check below still fires when lat/lng ARE
            // supplied, so an attacker can't pass `0,0` to bypass.
            'latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
        ], [
            'location.required' => __('api.location_required'),
            'phone.required' => __('api.phone_required'),
            'terms_accepted.accepted' => __('api.terms_required'),
            'latitude.numeric' => __('api.location_not_verified'),
            'longitude.numeric' => __('api.location_not_verified'),
            'latitude.between' => __('api.location_not_verified'),
            'longitude.between' => __('api.location_not_verified'),
        ]);

        if ($validator->fails()) {
            // If terms specifically failed, surface a distinct code so the
            // frontend can render a specific message instead of the generic
            // "check the form" fallback.
            if ($validator->errors()->has('terms_accepted')) {
                return [
                    'error' => __('api.terms_required'),
                    'code'  => 'TERMS_REQUIRED',
                    'status' => 422,
                ];
            }
            if ($validator->errors()->has('latitude') || $validator->errors()->has('longitude')) {
                return [
                    'error' => __('api.location_not_verified'),
                    'code'  => 'LOCATION_NOT_VERIFIED',
                    'status' => 422,
                ];
            }
            $errors = $validator->errors()->first();
            return [
                'error' => $errors,
                'code'  => \App\Core\ApiErrorCodes::VALIDATION_ERROR,
                'status' => 422,
            ];
        }

        // When the platform has opted in to verified-location enforcement,
        // require lat/lng AND reject Null Island. Off by default to avoid
        // bricking tenants without Google Maps configured.
        $requireVerifiedLocation = filter_var(
            getenv('REGISTRATION_REQUIRE_VERIFIED_LOCATION') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $hasLat = isset($data['latitude']) && $data['latitude'] !== '' && $data['latitude'] !== null;
        $hasLng = isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null;
        if ($requireVerifiedLocation && (!$hasLat || !$hasLng)) {
            return [
                'error' => __('api.location_not_verified'),
                'code'  => 'LOCATION_NOT_VERIFIED',
                'status' => 422,
            ];
        }
        // Null Island guard — only fires when lat/lng were supplied. (lat=0
        // AND lng=0 is a single point in the Gulf of Guinea overwhelmingly
        // associated with default-zero coordinates, not a real address.)
        if ($hasLat && $hasLng && (float) $data['latitude'] === 0.0 && (float) $data['longitude'] === 0.0) {
            return [
                'error' => __('api.location_not_verified'),
                'code'  => 'LOCATION_NOT_VERIFIED',
                'status' => 422,
            ];
        }

        // Password confirmation match — cheap local check, runs BEFORE any
        // DNS / external lookups so a user who typo'd their password gets a
        // useful inline error instead of an opaque email-domain rejection.
        // Optional field: a client that omits it bypasses this check, but
        // both first-party frontends include it.
        if (array_key_exists('password_confirmation', $data)) {
            if ((string) ($data['password_confirmation'] ?? '') !== (string) $data['password']) {
                return [
                    'error' => __('api.password_mismatch'),
                    'code'  => 'PASSWORD_MISMATCH',
                    'status' => 422,
                ];
            }
        }

        // Reject disposable / throwaway email domains (mailinator, 10minutemail,
        // tempmail, etc.). Removes the cheapest bot-signup path: no inbox to
        // pay for, no SMS to pay for = zero cost per registration. Forcing
        // attackers onto real providers raises their per-account cost from
        // near-zero to "whatever Gmail's account-creation friction is".
        if ($this->disposableEmail->isDisposable((string) $data['email'])) {
            Log::info('registration.disposable_email_blocked', [
                'tenant_id' => $tenantId,
                'ip' => request()?->ip(),
                'email_domain' => substr(strrchr(strtolower((string) $data['email']), '@') ?: '', 1),
            ]);
            return [
                'error' => __('api.email_disposable'),
                'code'  => 'EMAIL_DISPOSABLE',
                'status' => 422,
            ];
        }

        // MX-record check — domain must actually be able to receive email.
        // Catches typos (`gmial.com`), made-up domains bots fall back to,
        // and freshly-registered burner domains that haven't been wired up
        // for mail. Fails open on DNS errors so an outage can't lock users
        // out. Results are cached 24h.
        if (!$this->mxValidator->isResolvable((string) $data['email'])) {
            Log::info('registration.invalid_email_domain', [
                'tenant_id' => $tenantId,
                'ip' => request()?->ip(),
                'email_domain' => substr(strrchr(strtolower((string) $data['email']), '@') ?: '', 1),
            ]);
            return [
                'error' => __('api.email_domain_invalid'),
                'code'  => 'EMAIL_DOMAIN_INVALID',
                'status' => 422,
            ];
        }

        // Have I Been Pwned k-anonymity check — reject passwords that
        // appear in known breach corpora. Defends against credential-
        // stuffing in a way password complexity rules cannot.
        if ($this->pwnedPassword->isPwned((string) $data['password'])) {
            return [
                'error' => __('api.password_pwned'),
                'code'  => 'PASSWORD_PWNED',
                'status' => 422,
            ];
        }

        // Invite-code gate — when the tenant's effective registration policy
        // is `invite_only`, the submission MUST carry a valid, unused,
        // non-expired invite code. Validated here (before user creation) so
        // we don't insert a half-registered user when the code is bad.
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);
        $inviteRequired = ($policy['registration_mode'] ?? 'open') === 'invite_only';
        $inviteCode = isset($data['invite_code']) ? strtoupper(trim((string) $data['invite_code'])) : '';
        if ($inviteRequired) {
            if ($inviteCode === '') {
                return [
                    'error' => __('api.invite_code_required'),
                    'code'  => 'INVITE_REQUIRED',
                    'status' => 422,
                ];
            }
            $inviteResult = InviteCodeService::validate($tenantId, $inviteCode);
            if (!($inviteResult['valid'] ?? false)) {
                return [
                    'error' => __('api.invite_code_invalid'),
                    'code'  => 'INVITE_INVALID',
                    'status' => 422,
                ];
            }
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
            return [
                'error' => __('emails_misc.registration.error_generic'),
                'code'  => \App\Core\ApiErrorCodes::VALIDATION_DUPLICATE,
                'status' => 409,
            ];
        }

        // Redeem the invite code now that the user row exists. There is a
        // small race window between validate() and redeem() where a one-use
        // code could be consumed by another concurrent registration; if the
        // redeem fails we deactivate the freshly-created user so the code
        // remains the gating signal instead of leaving an orphan account.
        if ($inviteRequired) {
            $redeemed = InviteCodeService::redeem($tenantId, $inviteCode, (int) $user->id);
            if (!$redeemed) {
                // Soft-delete by setting status; the email is now reserved
                // against re-registration, but the account cannot be used.
                $user->update(['status' => 'rejected']);
                return [
                    'error' => __('api.invite_code_invalid'),
                    'code'  => 'INVITE_INVALID',
                    'status' => 422,
                ];
            }
        }

        // Dispatch UserRegistered event (triggers welcome notification, etc.)
        try {
            event(new UserRegistered($user, $tenantId));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('UserRegistered event dispatch failed', [
                'user_id' => null,
                'error' => $e->getMessage(),
            ]);
        }

        // Burn one slot from the per-IP daily cap (see top of method).
        // Done here, after the user row is committed and the event has
        // fired, so failed attempts don't count against the quota.
        if ($dailyCap > 0) {
            RateLimiter::hit($dailyCapKey, 86400);
        }

        // Tick the per-tenant hourly counter. If this signup pushed the
        // tenant over the threshold, trip the circuit breaker so the NEXT
        // signup attempt is rejected with REGISTRATION_TENANT_PAUSED.
        // Atomic: Cache::increment under Redis or DB cache is safe under
        // concurrent registrations.
        if ($tenantHourlyCap > 0) {
            try {
                $count = \Illuminate\Support\Facades\Cache::increment($tenantHourlyKey);
                if ($count === false || $count === 1) {
                    // First hit in the window — set TTL.
                    \Illuminate\Support\Facades\Cache::put($tenantHourlyKey, 1, 3600);
                    $count = 1;
                }
                if ((int) $count >= $tenantHourlyCap) {
                    \Illuminate\Support\Facades\Cache::put($breakerKey, true, 3600);
                    \Illuminate\Support\Facades\Cache::put($breakerKey . ':ttl', 3600, 3600);
                    Log::warning('registration.tenant_breaker_tripped', [
                        'tenant_id' => $tenantId,
                        'count_in_hour' => $count,
                        'threshold' => $tenantHourlyCap,
                        'ip' => request()?->ip(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::info('registration.tenant_counter_failed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
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
        $tenantId = TenantContext::getId();

        /** @var User|null $user */
        $query = $this->user->newQuery()
            ->where('verification_token', $token)
            ->where('status', 'pending');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $user = $query->first();

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
        $tenantId = $tenantId > 0 ? $tenantId : (int) TenantContext::getId();

        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('email', strtolower(trim($email)))
            ->where('status', 'pending')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $user) {
            return null;
        }

        $token = Str::random(64);
        $user->update(['verification_token' => $token]);

        if ($tenantId <= 0) {
            return $token;
        }

        // Send the verification email
        $previousTenantId = TenantContext::getId();
        try {
            TenantContext::setById($tenantId);
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

                if (!EmailDispatchService::sendRaw($user->email, __('emails_misc.registration.verify_subject', ['tenant' => $tenantName]), $html, null, null, null, 'email_verification')) {
                    Log::warning('RegistrationService: verification email send returned false', [
                        'user_id' => $user->id ?? null,
                        'tenant_id' => TenantContext::getId(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('RegistrationService: Failed to send verification email', [
                'user_id' => null,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($previousTenantId !== null) {
                TenantContext::setById($previousTenantId);
            } else {
                TenantContext::reset();
            }
        }

        return $token;
    }
}
