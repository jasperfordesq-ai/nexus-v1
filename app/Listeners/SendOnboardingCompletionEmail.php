<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\OnboardingCompleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends a confirmation email to the member when they finish the onboarding wizard.
 *
 * Queued so it never delays the wizard completion response.
 */
class SendOnboardingCompletionEmail implements ShouldQueue
{
    public function handle(OnboardingCompleted $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $user = User::find($event->userId);
            if (!$user || empty($user->email)) {
                return;
            }

            $firstName = $user->first_name ?? $user->name ?? '';
            $communityName = TenantContext::getName() ?: 'the community';

            $profileUrl = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/profile';
            $exploreUrl = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/listings';

            $html = EmailTemplateBuilder::make()
                ->theme('success')
                ->title(__('emails_misc.onboarding_completed.title'))
                ->previewText(__('emails_misc.onboarding_completed.preview', ['community' => $communityName]))
                ->greeting($firstName)
                ->paragraph(__('emails_misc.onboarding_completed.greeting_line', ['community' => $communityName]))
                ->paragraph(__('emails_misc.onboarding_completed.next_steps'))
                ->bulletList([
                    __('emails_misc.onboarding_completed.step_browse'),
                    __('emails_misc.onboarding_completed.step_connect'),
                    __('emails_misc.onboarding_completed.step_events'),
                ])
                ->paragraph('<em>' . __('emails_misc.onboarding_completed.footer_note') . '</em>')
                ->button(__('emails_misc.onboarding_completed.cta'), $exploreUrl)
                ->render();

            $subject = __('emails_misc.onboarding_completed.subject', [
                'name'      => $firstName,
                'community' => $communityName,
            ]);

            $mailer = Mailer::forCurrentTenant();
            $sent   = $mailer->send($user->email, $subject, $html);

            if (!$sent) {
                Log::warning('SendOnboardingCompletionEmail: email returned false', [
                    'user_id'   => $event->userId,
                    'tenant_id' => $event->tenantId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SendOnboardingCompletionEmail: failed', [
                'user_id'   => $event->userId ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
