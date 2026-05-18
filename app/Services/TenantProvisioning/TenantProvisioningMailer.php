<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\TenantProvisioning;

use App\Core\Env;
use App\Core\TenantContext;
use App\Core\EmailTemplateBuilder;
use App\I18n\LocaleContext;
use App\Services\EmailDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AG44 — Welcome / rejection emails for tenant provisioning.
 *
 * Uses the platform's EmailTemplateBuilder so styling matches the rest of
 * the system. Strings are translated against `emails_provisioning.*`.
 *
 * Locale: rendered in the applicant's `default_language` so they receive
 * the correct language even when the queue worker boots in English.
 */
class TenantProvisioningMailer
{
    /**
     * Send the "your community is ready" welcome email.
     */
    public static function sendWelcome(array $request, int $tenantId, ?string $tempPassword = null): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            return;
        }

        $applicantEmail = $request['applicant_email'] ?? null;
        if (empty($applicantEmail)) {
            return;
        }

        $locale = $request['default_language'] ?? 'en';
        try {
            TenantContext::setById($tenantId);
            LocaleContext::withLocale($locale, function () use ($request, $tenant, $tenantId, $applicantEmail, $tempPassword) {
                try {
                    $name      = $request['applicant_name'] ?? '';
                    $tenantUrl = self::tenantUrl($tenant);
                    $loginUrl  = $tenantUrl . '/login';

                    $builder = EmailTemplateBuilder::make()
                        ->theme('success')
                        ->title(__('emails_provisioning.welcome.title'))
                        ->previewText(__('emails_provisioning.welcome.preview', ['name' => $tenant->name]))
                        ->greeting($name ?: __('emails.common.fallback_name'))
                        ->paragraph(__('emails_provisioning.welcome.body', ['name' => $tenant->name]));

                    $info = [
                        __('emails_provisioning.welcome.tenant_url_label')  => $tenantUrl,
                        __('emails_provisioning.welcome.login_url_label')   => $loginUrl,
                        __('emails_provisioning.welcome.admin_email_label') => $applicantEmail,
                    ];
                    if (! empty($tempPassword)) {
                        $info[__('emails_provisioning.welcome.temp_password_label')] = $tempPassword;
                    }
                    $builder->infoCard($info);

                    $builder->paragraph(__('emails_provisioning.welcome.next_steps'));
                    $builder->button(__('emails_provisioning.welcome.cta'), $loginUrl);

                    $subject = __('emails_provisioning.welcome.subject', ['name' => $tenant->name]);
                    $html    = $builder->render();

                    if (!EmailDispatchService::sendRaw($applicantEmail, $subject, $html, null, null, null, 'tenant_provisioning', ['tenant_id' => $tenantId])) {
                        Log::warning('TenantProvisioningMailer welcome send returned false', [
                            'tenant_id' => $tenant->id ?? null,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning('TenantProvisioningMailer welcome failed', ['error' => $e->getMessage()]);
                }
            });
        } finally {
            TenantContext::reset();
        }
    }

    /**
     * Send the rejection email.
     */
    public static function sendRejection(array $request, string $reason): void
    {
        $applicantEmail = $request['applicant_email'] ?? null;
        if (empty($applicantEmail)) {
            return;
        }

        $locale = $request['default_language'] ?? 'en';
        LocaleContext::withLocale($locale, function () use ($request, $reason, $applicantEmail) {
            try {
                $name = $request['applicant_name'] ?? '';
                $org  = $request['org_name'] ?? '';

                $builder = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails_provisioning.rejection.title'))
                    ->previewText(__('emails_provisioning.rejection.preview'))
                    ->greeting($name ?: __('emails.common.fallback_name'))
                    ->paragraph(__('emails_provisioning.rejection.body', ['org' => $org]));

                if (! empty($reason)) {
                    $builder->infoCard([
                        __('emails_provisioning.rejection.reason_label') => $reason,
                    ]);
                }

                $builder->paragraph(__('emails_provisioning.rejection.followup'));

                $subject = __('emails_provisioning.rejection.subject');
                $html    = $builder->render();

                // Rejected provisioning requests do not have a tenant yet. Tell
                // the dispatcher this is an intentional platform/pre-tenant
                // send so a stale request or worker tenant is not inherited.
                if (!EmailDispatchService::sendRaw($applicantEmail, $subject, $html, null, null, null, 'tenant_provisioning', ['tenant_id' => null, 'allow_missing_tenant' => true])) {
                    Log::warning('TenantProvisioningMailer rejection send returned false');
                }
            } catch (Throwable $e) {
                Log::warning('TenantProvisioningMailer rejection failed', ['error' => $e->getMessage()]);
            }
        });
    }

    private static function tenantUrl(object $tenant): string
    {
        // Custom-domain tenant
        if (! empty($tenant->domain)) {
            return 'https://' . rtrim((string) $tenant->domain, '/');
        }

        // Sub-tenant sharing a parent's custom domain (e.g. timebanking.uk/cardiff)
        if (! empty($tenant->parent_id)) {
            $parentDomain = DB::table('tenants')
                ->where('id', (int) $tenant->parent_id)
                ->where('is_active', 1)
                ->value('domain');
            if ($parentDomain) {
                return 'https://' . rtrim((string) $parentDomain, '/') . '/' . $tenant->slug;
            }
        }

        // Shared platform host
        $base = rtrim((string) (Env::get('FRONTEND_URL') ?: 'https://app.project-nexus.ie'), '/');
        return $base . '/' . $tenant->slug;
    }
}
