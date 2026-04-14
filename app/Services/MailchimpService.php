<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MailchimpService — manages Mailchimp newsletter subscriptions.
 *
 * Requires MAILCHIMP_API_KEY and MAILCHIMP_LIST_ID env vars.
 * When unconfigured, methods no-op gracefully (callers wrap in try/catch).
 */
class MailchimpService
{
    private ?string $apiKey;
    private ?string $listId;
    private ?string $server;

    public function __construct()
    {
        $this->apiKey = env('MAILCHIMP_API_KEY');
        $this->listId = env('MAILCHIMP_LIST_ID');
        // Mailchimp API keys end with -usXX where XX is the server prefix
        $this->server = $this->apiKey ? last(explode('-', $this->apiKey)) : null;
    }

    /**
     * Subscribe an email address to the configured Mailchimp list.
     */
    public function subscribe(string $email, ?string $firstName = null, ?string $lastName = null): void
    {
        if (!$this->isConfigured()) {
            Log::debug('MailchimpService::subscribe skipped — not configured');
            return;
        }

        $subscriberHash = md5(strtolower($email));

        Http::withBasicAuth('anystring', $this->apiKey)
            ->timeout(10)
            ->put("{$this->baseUrl()}/lists/{$this->listId}/members/{$subscriberHash}", [
                'email_address' => $email,
                'status_if_new' => 'subscribed',
                'status' => 'subscribed',
                'merge_fields' => array_filter([
                    'FNAME' => $firstName,
                    'LNAME' => $lastName,
                ]),
            ])
            ->throw();
    }

    /**
     * Unsubscribe an email address from the configured Mailchimp list.
     */
    public function unsubscribe(string $email): void
    {
        if (!$this->isConfigured()) {
            Log::debug('MailchimpService::unsubscribe skipped — not configured');
            return;
        }

        $subscriberHash = md5(strtolower($email));

        Http::withBasicAuth('anystring', $this->apiKey)
            ->timeout(10)
            ->patch("{$this->baseUrl()}/lists/{$this->listId}/members/{$subscriberHash}", [
                'status' => 'unsubscribed',
            ])
            ->throw();
    }

    private function isConfigured(): bool
    {
        return $this->apiKey && $this->listId && $this->server;
    }

    private function baseUrl(): string
    {
        return "https://{$this->server}.api.mailchimp.com/3.0";
    }
}
