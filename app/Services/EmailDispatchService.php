<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Log;

/**
 * Central raw email send path.
 *
 * NotificationDispatcher remains the preferred business-event dispatcher. This
 * service is the narrow escape hatch for legacy/raw HTML emails while those
 * paths are migrated: it preserves tenant context, records missing categories,
 * and treats false returns as audit-worthy failures.
 */
class EmailDispatchService
{
    public static function sendRaw(
        string $to,
        string $subject,
        string $body,
        ?string $cc = null,
        ?string $replyTo = null,
        ?string $unsubscribeUrl = null,
        ?string $category = null,
        array $options = []
    ): bool {
        $options['cc'] = $cc;
        $options['replyTo'] = $replyTo;
        $options['unsubscribeUrl'] = $unsubscribeUrl;
        $options['category'] = $category;
        $options['source'] ??= 'EmailDispatchService::sendRaw';

        return app(self::class)->send($to, $subject, $body, $options);
    }

    /**
     * Compatibility helper for legacy app(EmailService::class)->send(...)
     * paths. New business events should use NotificationDispatcher.
     *
     * @param array<string,mixed> $options
     */
    public static function sendWithOptions(string $to, string $subject, string $body, array $options = []): bool
    {
        $options['source'] ??= 'EmailDispatchService::sendWithOptions';

        return app(self::class)->send($to, $subject, $body, $options);
    }

    /**
     * @param array{
     *   cc?:string|null,
     *   replyTo?:string|null,
     *   unsubscribeUrl?:string|null,
     *   category?:string|null,
     *   tenant_id?:int|null,
     *   tenantId?:int|null,
     *   source?:string|null
     * } $options
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $category = trim((string) ($options['category'] ?? ''));
        $tenantId = $this->resolveTenantId($options);
        $source = (string) ($options['source'] ?? 'EmailDispatchService');

        if ($category === '') {
            Log::warning('EmailDispatchService::send called without category', [
                'tenant_id' => $tenantId,
                'source' => $source,
                'to' => $this->maskEmail($to),
            ]);
        }

        if ($tenantId === null) {
            Log::warning('EmailDispatchService::send called without tenant context', [
                'source' => $source,
                'category' => $category !== '' ? $category : null,
                'to' => $this->maskEmail($to),
            ]);
        }

        try {
            return (bool) TenantContext::runForTenant($tenantId, function () use ($to, $subject, $body, $options, $category, $tenantId, $source): bool {
                $sent = Mailer::forCurrentTenant()->send(
                    $to,
                    $subject,
                    $body,
                    $options['cc'] ?? null,
                    $options['replyTo'] ?? null,
                    $options['unsubscribeUrl'] ?? null,
                    $category !== '' ? $category : null,
                );

                if (!$sent) {
                    Log::warning('EmailDispatchService::send returned false', [
                        'tenant_id' => $tenantId,
                        'source' => $source,
                        'category' => $category !== '' ? $category : null,
                        'to' => $this->maskEmail($to),
                    ]);
                }

                return $sent;
            });
        } catch (\Throwable $e) {
            Log::error('EmailDispatchService::send failed', [
                'tenant_id' => $tenantId,
                'source' => $source,
                'category' => $category !== '' ? $category : null,
                'to' => $this->maskEmail($to),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    private function resolveTenantId(array $options): ?int
    {
        $tenantId = $options['tenant_id'] ?? $options['tenantId'] ?? TenantContext::getId();

        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        return (int) $tenantId;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $masked = strlen($local) > 1 ? $local[0] . str_repeat('*', min(strlen($local) - 1, 5)) : '*';

        return $masked . '@' . $parts[1];
    }
}
