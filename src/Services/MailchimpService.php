<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

class MailchimpService
{
    private $apiKey;
    private $listId;
    private $dataCenter;

    public function __construct()
    {
        // Fetch from Tenant Configuration
        $tenant = \Nexus\Core\TenantContext::get();
        $config = json_decode($tenant['configuration'] ?? '{}', true);

        $this->apiKey = $config['mailchimp_api_key'] ?? '';
        $this->listId = $config['mailchimp_list_id'] ?? '';

        if ($this->apiKey) {
            // Extract DC from API Key (e.g. xxxxx-us19)
            $parts = explode('-', $this->apiKey);
            $this->dataCenter = end($parts);
        }
    }

    public function subscribe($email, $firstName, $lastName)
    {
        if (empty($this->apiKey) || empty($this->listId)) {
            error_log("Mailchimp Service: Missing API Key or List ID");
            return false;
        }

        $url = "https://{$this->dataCenter}.api.mailchimp.com/3.0/lists/{$this->listId}/members/" . md5(strtolower($email));

        $data = [
            'email_address' => $email,
            'status_if_new' => 'subscribed', // Force subscription
            'status'        => 'subscribed', // Ensure existing members are subscribed
            'merge_fields'  => [
                'FNAME' => $firstName,
                'LNAME' => $lastName
            ]
        ];

        $json = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // PUT creates or updates
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $msg = "Mailchimp Success: $email subscribed (List: {$this->listId})";
            error_log($msg);
            return ['success' => true, 'message' => $msg];
        } else {
            // Enhanced logging for debugging - SECURITY: Never log API keys
            $cleanResult = str_replace(["\n", "\r"], " ", $result);
            $msg = "Mailchimp Error ($httpCode): $cleanResult";
            error_log($msg);
            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Unsubscribe an email from the Mailchimp list
     */
    public function unsubscribe($email)
    {
        if (empty($this->apiKey) || empty($this->listId)) {
            error_log("Mailchimp Service: Missing API Key or List ID");
            return false;
        }

        $subscriberHash = md5(strtolower($email));
        $url = "https://{$this->dataCenter}.api.mailchimp.com/3.0/lists/{$this->listId}/members/{$subscriberHash}";

        $data = [
            'status' => 'unsubscribed'
        ];

        $json = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $msg = "Mailchimp Success: $email unsubscribed (List: {$this->listId})";
            error_log($msg);
            return ['success' => true, 'message' => $msg];
        } else {
            $cleanResult = str_replace(["\n", "\r"], " ", $result);
            $msg = "Mailchimp Unsubscribe Error ($httpCode): $cleanResult";
            error_log($msg);
            return ['success' => false, 'message' => $msg];
        }
    }
}
