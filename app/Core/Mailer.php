<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use App\Models\EmailSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Email sending with multi-provider support (SMTP, Gmail API, SendGrid).
 */
class Mailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $fromEmail;
    private $fromName;
    private $socket;

    // Gmail API settings
    private $useGmailApi;
    private $gmailClientId;
    private $gmailClientSecret;
    private $gmailRefreshToken;

    // SendGrid settings
    private ?string $sendgridApiKey = null;

    // Driver: 'smtp', 'gmail_api', or 'sendgrid'
    private string $driver = 'smtp';

    // Tenant context
    private ?int $tenantId = null;

    // Redis cache keys
    private const CACHE_KEY_ACCESS_TOKEN = 'gmail_oauth_access_token';
    private const CACHE_KEY_TOKEN_EXPIRY = 'gmail_oauth_token_expiry';
    private const CACHE_KEY_REFRESH_ATTEMPTS = 'gmail_oauth_refresh_attempts';
    private const CACHE_KEY_CIRCUIT_BREAKER = 'gmail_oauth_circuit_breaker';
    private const CACHE_KEY_FAILURE_COUNT = 'gmail_oauth_failure_count';

    // Rate limiting & circuit breaker constants
    private const MAX_REFRESH_ATTEMPTS_PER_HOUR = 10;
    private const CIRCUIT_BREAKER_THRESHOLD = 3;
    private const CIRCUIT_BREAKER_TIMEOUT = 300;
    private const TOKEN_TTL = 3000;

    /**
     * @param int|null $tenantId When provided, loads per-tenant email config.
     *                           When null, uses platform-wide .env config.
     */
    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;

        // Always load .env values as the base/fallback config
        $envValues = $this->loadEnvValues();

        // Check if Gmail API is enabled (from .env)
        $useGmailApiRaw = $envValues['USE_GMAIL_API'] ?? 'false';
        $this->useGmailApi = strtolower($useGmailApiRaw) === 'true';

        if ($this->useGmailApi) {
            $this->gmailClientId = $envValues['GMAIL_CLIENT_ID'] ?? '';
            $this->gmailClientSecret = $envValues['GMAIL_CLIENT_SECRET'] ?? '';
            $this->gmailRefreshToken = $envValues['GMAIL_REFRESH_TOKEN'] ?? '';
            $this->fromEmail = $envValues['GMAIL_SENDER_EMAIL'] ?? $envValues['SMTP_FROM_EMAIL'] ?? '';
            $this->fromName = $envValues['GMAIL_SENDER_NAME'] ?? $envValues['SMTP_FROM_NAME'] ?? 'Project NEXUS';
            $this->driver = 'gmail_api';
        }

        // Always load SMTP credentials (used as primary when Gmail is disabled, or as fallback)
        $this->host = $envValues['SMTP_HOST'] ?? '';
        $this->port = $envValues['SMTP_PORT'] ?? 587;
        $this->username = $envValues['SMTP_USER'] ?? '';
        $this->password = $envValues['SMTP_PASS'] ?? '';
        $this->encryption = $envValues['SMTP_ENCRYPTION'] ?? 'tls';

        if (!$this->useGmailApi) {
            $this->fromEmail = $envValues['SMTP_FROM_EMAIL'] ?? '';
            $this->fromName = $envValues['SMTP_FROM_NAME'] ?? 'Project NEXUS';
        }

        // Load platform-wide SendGrid config from .env (if set)
        $envSendGridKey = $envValues['SENDGRID_API_KEY'] ?? '';
        if (!empty($envSendGridKey) && !$this->useGmailApi) {
            $this->sendgridApiKey = $envSendGridKey;
            $this->fromEmail = $envValues['SENDGRID_FROM_EMAIL'] ?? $this->fromEmail;
            $this->fromName = $envValues['SENDGRID_FROM_NAME'] ?? $this->fromName;
            $this->driver = 'sendgrid';
        }

        // Per-tenant override
        if ($tenantId !== null) {
            $this->loadTenantConfig($tenantId);
        }
    }

    /**
     * Factory: create a Mailer configured for the current tenant context.
     */
    public static function forCurrentTenant(): self
    {
        return new self(TenantContext::getId());
    }

    /**
     * Load per-tenant email provider config from the email_settings table.
     */
    private function loadTenantConfig(int $tenantId): void
    {
        try {
            if (!class_exists(EmailSettings::class)) { return; }
            $provider = EmailSettings::get($tenantId, 'email_provider');

            if (!$provider || $provider === 'platform_default') {
                return;
            }

            switch ($provider) {
                case 'sendgrid':
                    $apiKey = EmailSettings::get($tenantId, 'sendgrid_api_key');
                    if (!empty($apiKey)) {
                        $this->sendgridApiKey = $apiKey;
                        $this->driver = 'sendgrid';
                        $fromEmail = EmailSettings::get($tenantId, 'sendgrid_from_email');
                        $fromName = EmailSettings::get($tenantId, 'sendgrid_from_name');
                        if (!empty($fromEmail)) $this->fromEmail = $fromEmail;
                        if (!empty($fromName)) $this->fromName = $fromName;
                    }
                    break;

                case 'gmail_api':
                    $clientId = EmailSettings::get($tenantId, 'gmail_client_id');
                    $clientSecret = EmailSettings::get($tenantId, 'gmail_client_secret');
                    $refreshToken = EmailSettings::get($tenantId, 'gmail_refresh_token');
                    if (!empty($clientId) && !empty($clientSecret) && !empty($refreshToken)) {
                        $this->gmailClientId = $clientId;
                        $this->gmailClientSecret = $clientSecret;
                        $this->gmailRefreshToken = $refreshToken;
                        $this->useGmailApi = true;
                        $this->driver = 'gmail_api';
                        $senderEmail = EmailSettings::get($tenantId, 'gmail_sender_email');
                        $senderName = EmailSettings::get($tenantId, 'gmail_sender_name');
                        if (!empty($senderEmail)) $this->fromEmail = $senderEmail;
                        if (!empty($senderName)) $this->fromName = $senderName;
                    }
                    break;

                case 'smtp':
                    $smtpHost = EmailSettings::get($tenantId, 'smtp_host');
                    if (!empty($smtpHost)) {
                        $this->host = $smtpHost;
                        $this->port = EmailSettings::get($tenantId, 'smtp_port') ?? 587;
                        $this->username = EmailSettings::get($tenantId, 'smtp_user') ?? '';
                        $this->password = EmailSettings::get($tenantId, 'smtp_password') ?? '';
                        $this->encryption = EmailSettings::get($tenantId, 'smtp_encryption') ?? 'tls';
                        $this->driver = 'smtp';
                        $this->useGmailApi = false;
                        $fromEmail = EmailSettings::get($tenantId, 'smtp_from_email');
                        $fromName = EmailSettings::get($tenantId, 'smtp_from_name');
                        if (!empty($fromEmail)) $this->fromEmail = $fromEmail;
                        if (!empty($fromName)) $this->fromName = $fromName;
                    }
                    break;
            }
        } catch (\Throwable $e) {
            error_log("Mailer: Failed to load tenant config for tenant {$tenantId}: " . $e->getMessage());
        }
    }

    /**
     * Load mail configuration values via Laravel's config() helper.
     *
     * Credentials are pulled from config/mail.php (smtp + gmail_api + sendgrid)
     * at call time rather than read directly from the environment. This keeps
     * secrets out of long-lived object properties in downstream callers and
     * routes all mail credential access through a single auditable surface.
     */
    private function loadEnvValues(): array
    {
        return [
            'USE_GMAIL_API'       => config('mail.gmail_api.enabled') ? 'true' : 'false',
            'GMAIL_CLIENT_ID'     => (string) (config('mail.gmail_api.client_id') ?? ''),
            'GMAIL_CLIENT_SECRET' => (string) (config('mail.gmail_api.client_secret') ?? ''),
            'GMAIL_REFRESH_TOKEN' => (string) (config('mail.gmail_api.refresh_token') ?? ''),
            'GMAIL_SENDER_EMAIL'  => (string) (config('mail.gmail_api.sender_email') ?? ''),
            'GMAIL_SENDER_NAME'   => (string) (config('mail.gmail_api.sender_name') ?? ''),
            'SMTP_HOST'           => (string) (config('mail.mailers.smtp.host') ?? ''),
            'SMTP_PORT'           => config('mail.mailers.smtp.port') ?? 587,
            'SMTP_USER'           => (string) (config('mail.mailers.smtp.username') ?? ''),
            'SMTP_PASS'           => (string) (config('mail.mailers.smtp.password') ?? ''),
            'SMTP_ENCRYPTION'     => (string) (config('mail.mailers.smtp.encryption') ?? 'tls'),
            'SMTP_FROM_EMAIL'     => (string) (config('mail.from.address') ?? ''),
            'SMTP_FROM_NAME'      => (string) (config('mail.from.name') ?? 'Project NEXUS'),
            'SENDGRID_API_KEY'    => (string) (config('mail.sendgrid.api_key') ?? ''),
            'SENDGRID_FROM_EMAIL' => (string) (config('mail.sendgrid.from_email') ?? ''),
            'SENDGRID_FROM_NAME'  => (string) (config('mail.sendgrid.from_name') ?? ''),
        ];
    }

    /**
     * Mask an email address for safe logging (e.g., "j***@example.com").
     */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $masked = strlen($local) > 1 ? $local[0] . str_repeat('*', min(strlen($local) - 1, 5)) : '*';
        return $masked . '@' . $domain;
    }

    /**
     * Send an email.
     *
     * @param string      $to      Recipient email address
     * @param string      $subject Email subject
     * @param string      $body    HTML body
     * @param string|null $cc      CC recipient (optional)
     * @param string|null $replyTo Reply-To address (optional)
     * @return bool
     */
    public function send($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null): bool
    {
        // Sanitize header-injectable values — strip CR/LF to prevent email header injection
        $to = self::sanitizeHeaderValue($to);
        $subject = self::sanitizeHeaderValue($subject);
        $cc = $cc !== null ? self::sanitizeHeaderValue($cc) : null;
        $replyTo = $replyTo !== null ? self::sanitizeHeaderValue($replyTo) : null;

        // Route based on configured driver
        if ($this->driver === 'sendgrid') {
            $result = $this->sendViaSendGrid($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            if ($result) {
                return true;
            }

            if (!empty($this->host) && !empty($this->username)) {
                error_log("Mailer: SendGrid failed, falling back to SMTP for: " . self::maskEmail($to));
                return $this->sendViaSmtp($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            }

            error_log("Mailer: SendGrid failed and no SMTP fallback configured. Email not sent to: " . self::maskEmail($to));
            return false;
        }

        if ($this->driver === 'gmail_api') {
            $result = $this->sendViaGmailApi($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            if ($result) {
                return true;
            }

            if (!empty($this->host) && !empty($this->username)) {
                error_log("Mailer: Gmail API failed, falling back to SMTP for: " . self::maskEmail($to));
                return $this->sendViaSmtp($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            }

            error_log("Mailer: Gmail API failed and no SMTP fallback configured. Email not sent to: " . self::maskEmail($to));
            return false;
        }

        return $this->sendViaSmtp($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
    }

    /**
     * Send email via SendGrid Web API v3.
     */
    private function sendViaSendGrid($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null): bool
    {
        try {
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom($this->fromEmail, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($to);

            if ($cc) {
                $email->addCc($cc);
            }
            if ($replyTo) {
                // Parse RFC 5322 format "Name <email>" into separate parts for SendGrid
                if (preg_match('/^(.+?)\s*<([^>]+)>$/', $replyTo, $matches)) {
                    $email->setReplyTo(trim($matches[2]), trim($matches[1]));
                } else {
                    $email->setReplyTo($replyTo);
                }
            }

            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
            $plainText = preg_replace('/\n\s+/', "\n", $plainText);
            $email->addContent("text/plain", trim($plainText));
            $email->addContent("text/html", $body);

            $email->setClickTracking(false, false);
            $email->setOpenTracking(false);
            $email->setSubscriptionTracking(false);

            if ($this->tenantId) {
                $email->addCustomArg('tenant_id', (string) $this->tenantId);
                $email->addHeader('X-Nexus-Tenant', (string) $this->tenantId);
            }

            if ($unsubscribeUrl) {
                $email->addHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                $email->addHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }

            $sendgrid = new \SendGrid($this->sendgridApiKey);
            $response = $sendgrid->send($email);

            $statusCode = $response->statusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                if (class_exists(\App\Services\EmailMonitorService::class)) { \App\Services\EmailMonitorService::recordEmailSendStatic('sendgrid', true, $this->tenantId); }
                return true;
            }

            error_log("SendGrid error ({$statusCode}): " . $response->body());
            if (class_exists(\App\Services\EmailMonitorService::class)) { \App\Services\EmailMonitorService::recordEmailSendStatic('sendgrid', false, $this->tenantId); }
            return false;
        } catch (\Exception $e) {
            error_log("SendGrid Error: " . $e->getMessage());
            if (class_exists(\App\Services\EmailMonitorService::class)) { \App\Services\EmailMonitorService::recordEmailSendStatic('sendgrid', false, $this->tenantId); }
            return false;
        }
    }

    /**
     * Send email via Gmail API using OAuth 2.0.
     */
    private function sendViaGmailApi($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null)
    {
        try {
            $accessToken = $this->getGmailAccessToken();
            if (!$accessToken) {
                throw new \Exception("Failed to get Gmail API access token");
            }

            $rawEmail = $this->buildRawEmail($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            $encodedEmail = $this->base64urlEncode($rawEmail);

            $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode(['raw' => $encodedEmail]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? $response;
                throw new \Exception("Gmail API error ($httpCode): $errorMsg");
            }

            return true;

        } catch (\Exception $e) {
            error_log("Gmail API Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Gmail API access token, refreshing if necessary.
     */
    private function getGmailAccessToken()
    {
        $hasCache = true;
        try {
            // Test if cache is available
            Cache::has(self::CACHE_KEY_ACCESS_TOKEN);
        } catch (\Throwable $e) {
            $hasCache = false;
        }

        if ($hasCache) {
            $circuitBreakerExpiry = Cache::get(self::CACHE_KEY_CIRCUIT_BREAKER);
            if ($circuitBreakerExpiry && time() < $circuitBreakerExpiry) {
                $remainingSeconds = $circuitBreakerExpiry - time();
                error_log("Gmail API circuit breaker is open. Blocked for {$remainingSeconds}s more.");
                return null;
            }

            $cachedToken = Cache::get(self::CACHE_KEY_ACCESS_TOKEN);
            $cachedExpiry = Cache::get(self::CACHE_KEY_TOKEN_EXPIRY);

            if ($cachedToken && $cachedExpiry && $cachedExpiry > time()) {
                return $cachedToken;
            }

            $refreshAttempts = Cache::increment(self::CACHE_KEY_REFRESH_ATTEMPTS);
            if ($refreshAttempts === 1) {
                // First increment — set TTL for the key
                Cache::put(self::CACHE_KEY_REFRESH_ATTEMPTS, 1, 3600);
            }

            if ($refreshAttempts > self::MAX_REFRESH_ATTEMPTS_PER_HOUR) {
                error_log("Gmail API rate limit exceeded: {$refreshAttempts} refresh attempts in the last hour");
                return null;
            }
        }

        if (empty($this->gmailClientId) || empty($this->gmailClientSecret) || empty($this->gmailRefreshToken)) {
            error_log("Gmail API Error: Missing credentials");
            return null;
        }

        $token = $this->refreshGmailToken();

        if ($token) {
            if ($hasCache) { Cache::forget(self::CACHE_KEY_FAILURE_COUNT); }
            return $token;
        }

        if ($hasCache) {
            $failureCount = Cache::increment(self::CACHE_KEY_FAILURE_COUNT);
            if ($failureCount === 1) {
                Cache::put(self::CACHE_KEY_FAILURE_COUNT, 1, 3600);
            }

            if ($failureCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
                $breakerExpiry = time() + self::CIRCUIT_BREAKER_TIMEOUT;
                Cache::put(self::CACHE_KEY_CIRCUIT_BREAKER, $breakerExpiry, self::CIRCUIT_BREAKER_TIMEOUT);
                error_log("Gmail API circuit breaker opened after {$failureCount} consecutive failures.");
            }
        }

        return null;
    }

    /**
     * Refresh Gmail OAuth2 access token.
     */
    private function refreshGmailToken(): ?string
    {
        $url = 'https://oauth2.googleapis.com/token';

        $postData = http_build_query([
            'client_id' => $this->gmailClientId,
            'client_secret' => $this->gmailClientSecret,
            'refresh_token' => $this->gmailRefreshToken,
            'grant_type' => 'refresh_token'
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Gmail token refresh cURL error: $curlError");
            return null;
        }

        $data = json_decode($response, true);

        $logData = [
            'http_code' => $httpCode,
            'has_access_token' => isset($data['access_token']),
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null,
            'error' => $data['error'] ?? null,
            'error_description' => $data['error_description'] ?? null,
        ];
        error_log("Gmail token refresh response: " . json_encode($logData));

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("Gmail token refresh failed (HTTP $httpCode): " . ($data['error_description'] ?? $response));
            return null;
        }

        if (!isset($data['access_token'])) {
            error_log("Gmail token refresh response missing access_token field");
            return null;
        }

        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600;
        $expiryTimestamp = time() + $expiresIn;

        try {
            Cache::put(self::CACHE_KEY_ACCESS_TOKEN, $accessToken, self::TOKEN_TTL);
            Cache::put(self::CACHE_KEY_TOKEN_EXPIRY, $expiryTimestamp, self::TOKEN_TTL);
        } catch (\Throwable $e) {
            // Cache write failure is non-fatal
        }

        error_log("Gmail token refreshed successfully. Expires in {$expiresIn}s.");

        return $accessToken;
    }

    private function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function buildRawEmail($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null)
    {
        $boundary = 'boundary_' . md5(uniqid(time()));

        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
        $plainText = preg_replace('/\n\s+/', "\n", $plainText);
        $plainText = trim($plainText);

        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'To: ' . $to;
        if ($cc) {
            $headers[] = 'Cc: ' . $cc;
        }
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . md5(uniqid(time())) . '@' . parse_url($_ENV['APP_URL'] ?? 'localhost', PHP_URL_HOST) . '>';
        if ($unsubscribeUrl) {
            $headers[] = 'List-Unsubscribe: <' . $unsubscribeUrl . '>';
            $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n";

        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($plainText)) . "\r\n";

        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body)) . "\r\n";

        $message .= '--' . $boundary . '--';

        return $message;
    }

    private function sendViaSmtp($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null)
    {
        try {
            $this->connect();
            $this->auth();
            $this->sendData($to, $subject, $body, $cc, $replyTo, $unsubscribeUrl);
            $this->quit();
            return true;
        } catch (\Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function connect()
    {
        $host = $this->host;

        if ($this->encryption === 'ssl') {
            $host = "ssl://" . $host;
        }

        if (empty($this->host)) {
            throw new \Exception("SMTP host not configured");
        }

        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new \Exception("Could not connect to SMTP host: $errstr ($errno)");
        }

        $ehloHost = $_SERVER['HTTP_HOST'] ?? 'api.project-nexus.ie';

        $this->read();
        $this->write("EHLO " . $ehloHost);
        $this->read();

        if ($this->encryption === 'tls') {
            $this->write("STARTTLS");
            $this->read();
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->write("EHLO " . $ehloHost);
            $this->read();
        }
    }

    private function auth()
    {
        $this->write("AUTH LOGIN");
        $this->read();
        $this->write(base64_encode($this->username));
        $this->read();
        $this->write(base64_encode($this->password));
        $this->read();
    }

    private function sendData($to, $subject, $body, $cc = null, $replyTo = null, ?string $unsubscribeUrl = null)
    {
        $this->write("MAIL FROM: <{$this->fromEmail}>");
        $this->read();
        $this->write("RCPT TO: <$to>");
        $this->read();
        if ($cc) {
            $this->write("RCPT TO: <$cc>");
            $this->read();
        }
        $this->write("DATA");
        $this->read();

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "To: $to\r\n";
        if ($cc) {
            $headers .= "Cc: $cc\r\n";
        }
        if ($replyTo) {
            $headers .= "Reply-To: $replyTo\r\n";
        }
        // RFC 2047: encode subject so non-ASCII characters survive SMTP transport
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        if ($unsubscribeUrl) {
            $headers .= "List-Unsubscribe: <$unsubscribeUrl>\r\n";
            $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        }

        $this->write($headers . "\r\n" . $body . "\r\n.");
        $this->read();
    }

    private function quit()
    {
        $this->write("QUIT");
        fclose($this->socket);
    }

    private function write($cmd)
    {
        fputs($this->socket, $cmd . "\r\n");
    }

    private function read()
    {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    }

    /**
     * Test Gmail API connection.
     *
     * @return array{success: bool, message: string}
     */
    public static function testGmailConnection(): array
    {
        $mailer = new self();

        if (!$mailer->useGmailApi) {
            return ['success' => false, 'message' => 'Gmail API is not enabled'];
        }

        $token = $mailer->getGmailAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to obtain access token. Check credentials.'];
        }

        $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/profile');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $profile = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'Connected to Gmail API successfully. Email: ' . ($profile['emailAddress'] ?? 'unknown')
            ];
        }

        return ['success' => false, 'message' => 'Failed to verify Gmail API connection: ' . $response];
    }

    /**
     * Get current email provider type ('smtp', 'gmail_api', or 'sendgrid').
     */
    public function getProviderType(): string
    {
        return $this->driver;
    }

    /**
     * Sanitize a value used in email headers to prevent header injection.
     *
     * Strips carriage returns and line feeds which could be used to inject
     * additional headers (e.g., BCC, additional To, or arbitrary headers).
     */
    private static function sanitizeHeaderValue(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
