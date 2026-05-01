<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\EmailTemplate;
use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TwoFactorController -- Two-factor authentication setup.
 *
 * All methods use Laravel DI services.
 */
class TwoFactorController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TotpService $totpService,
    ) {}

    /** GET auth/2fa/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        return $this->respondWithData([
            'enabled' => $this->totpService->isEnabled($userId),
            'setup_required' => $this->totpService->isSetupRequired($userId),
            'backup_codes_remaining' => $this->totpService->getBackupCodeCount($userId),
        ]);
    }

    /** POST auth/2fa/setup */
    public function setup(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_setup', 5, 300);

        if ($this->totpService->isEnabled($userId)) {
            return $this->respondWithError(
                'ALREADY_ENABLED',
                '2FA is already enabled on your account',
                null,
                409
            );
        }

        try {
            $result = $this->totpService->initializeSetup($userId);

            // Convert raw SVG to data URI for use in <img src="...">
            $svgDataUri = 'data:image/svg+xml;base64,' . base64_encode($result['qr_code']);

            return $this->respondWithData([
                'qr_code_url' => $svgDataUri,
                'secret' => $result['secret'],
                'backup_codes' => [],
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(
                'SETUP_FAILED',
                'Failed to initialize 2FA setup',
                null,
                500
            );
        }
    }

    /** POST auth/2fa/verify */
    public function verify(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_verify', 10, 300);

        $data = $this->getAllInput();
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Verification code is required',
                'code',
                400
            );
        }

        $result = $this->totpService->completeSetup($userId, $code);

        if (!$result['success']) {
            return $this->respondWithError(
                'VERIFICATION_FAILED',
                $result['error'] ?? 'Invalid verification code',
                'code',
                400
            );
        }

        // Security notification + email: render in the user's preferred_language
        // so both bell text and the email match the recipient's locale, not the
        // request caller's (which can differ for impersonation/admin flows).
        try {
            $user = User::query()->find($userId);
            $userLocale = $user->preferred_language ?? null;

            LocaleContext::withLocale($userLocale, function () use ($userId) {
                try {
                    Notification::createNotification(
                        $userId,
                        __('api_controllers_2.two_factor.enabled_notification'),
                        null,
                        '2fa_enabled'
                    );
                } catch (\Throwable $e) {
                    Log::warning('[2FA] Failed to create 2FA enabled notification: ' . $e->getMessage(), ['user_id' => $userId]);
                }
            });

            if ($user && $user->email) {
                LocaleContext::withLocale($userLocale, function () use ($user, $userId) {
                    $mailer     = Mailer::forCurrentTenant();
                    $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
                    $userName   = $user->first_name ?? $user->name ?? '';

                    $html = EmailTemplateBuilder::make()
                        ->theme('success')
                        ->title(__('emails_security_alerts.2fa_enabled.title'))
                        ->previewText(__('emails_security_alerts.2fa_enabled.preview'))
                        ->greeting($userName)
                        ->paragraph(__('emails_security_alerts.2fa_enabled.body'))
                        ->paragraph(__('emails_security_alerts.2fa_enabled.warning'))
                        ->render();

                    $subject = __('emails_security_alerts.2fa_enabled.subject', ['community' => $tenantName]);
                    if (!$mailer->send($user->email, $subject, $html)) {
                        Log::warning('[2FA] Failed to send 2FA enabled email', ['user_id' => $userId]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[2FA] Failed to send 2FA enabled email: ' . $e->getMessage(), ['user_id' => $userId]);
        }

        return $this->respondWithData([
            'backup_codes' => $result['backup_codes'] ?? [],
        ]);
    }

    /** POST auth/2fa/disable */
    public function disable(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_disable', 3, 3600);

        $data = $this->getAllInput();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Password is required',
                'password',
                400
            );
        }

        $result = $this->totpService->disable($userId, $password);

        if (!$result['success']) {
            return $this->respondWithError(
                'DISABLE_FAILED',
                $result['error'] ?? 'Failed to disable 2FA',
                'password',
                403
            );
        }

        // Security notification + email: render in the user's preferred_language.
        try {
            $user = User::query()->find($userId);
            $userLocale = $user->preferred_language ?? null;

            LocaleContext::withLocale($userLocale, function () use ($userId) {
                try {
                    Notification::createNotification(
                        $userId,
                        __('api_controllers_2.two_factor.disabled_notification'),
                        null,
                        '2fa_disabled'
                    );
                } catch (\Throwable $e) {
                    Log::warning('[2FA] Failed to create 2FA disabled notification: ' . $e->getMessage(), ['user_id' => $userId]);
                }
            });

            if ($user && $user->email) {
                LocaleContext::withLocale($userLocale, function () use ($user, $userId) {
                    $mailer     = Mailer::forCurrentTenant();
                    $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
                    $userName   = $user->first_name ?? $user->name ?? '';

                    $html = EmailTemplateBuilder::make()
                        ->theme('danger')
                        ->title(__('emails_security_alerts.2fa_disabled.title'))
                        ->previewText(__('emails_security_alerts.2fa_disabled.preview'))
                        ->greeting($userName)
                        ->paragraph(__('emails_security_alerts.2fa_disabled.body'))
                        ->paragraph(__('emails_security_alerts.2fa_disabled.warning'))
                        ->render();

                    $subject = __('emails_security_alerts.2fa_disabled.subject', ['community' => $tenantName]);
                    if (!$mailer->send($user->email, $subject, $html)) {
                        Log::warning('[2FA] Failed to send 2FA disabled email', ['user_id' => $userId]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[2FA] Failed to send 2FA disabled email: ' . $e->getMessage(), ['user_id' => $userId]);
        }

        return $this->respondWithData([
            'message' => __('api_controllers_2.two_factor.disabled'),
        ]);
    }
}
