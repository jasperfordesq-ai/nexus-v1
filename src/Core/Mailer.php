<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Services\RedisCache;

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

    // Redis cache keys
    private const CACHE_KEY_ACCESS_TOKEN = 'gmail_oauth_access_token';
    private const CACHE_KEY_TOKEN_EXPIRY = 'gmail_oauth_token_expiry';
    private const CACHE_KEY_REFRESH_ATTEMPTS = 'gmail_oauth_refresh_attempts';
    private const CACHE_KEY_CIRCUIT_BREAKER = 'gmail_oauth_circuit_breaker';
    private const CACHE_KEY_FAILURE_COUNT = 'gmail_oauth_failure_count';

    // Rate limiting & circuit breaker constants
    private const MAX_REFRESH_ATTEMPTS_PER_HOUR = 10;
    private const CIRCUIT_BREAKER_THRESHOLD = 3; // failures
    private const CIRCUIT_BREAKER_TIMEOUT = 300; // 5 minutes in seconds
    private const TOKEN_TTL = 3000; // 50 minutes in seconds (tokens expire at ~60 min)

    public function __construct()
    {
        // Read directly from .env file (more reliable than $_ENV)
        $envValues = $this->loadEnvValues();

        // Check if Gmail API is enabled
        $useGmailApiRaw = $envValues['USE_GMAIL_API'] ?? 'false';
        $this->useGmailApi = strtolower($useGmailApiRaw) === 'true';

        if ($this->useGmailApi) {
            // Gmail API credentials
            $this->gmailClientId = $envValues['GMAIL_CLIENT_ID'] ?? '';
            $this->gmailClientSecret = $envValues['GMAIL_CLIENT_SECRET'] ?? '';
            $this->gmailRefreshToken = $envValues['GMAIL_REFRESH_TOKEN'] ?? '';
            $this->fromEmail = $envValues['GMAIL_SENDER_EMAIL'] ?? $envValues['SMTP_FROM_EMAIL'] ?? '';
            $this->fromName = $envValues['GMAIL_SENDER_NAME'] ?? $envValues['SMTP_FROM_NAME'] ?? 'Project NEXUS';
        }

        // Always load SMTP credentials (used as primary when Gmail is disabled, or as fallback when Gmail fails)
        $this->host = $envValues['SMTP_HOST'] ?? '';
        $this->port = $envValues['SMTP_PORT'] ?? 587;
        $this->username = $envValues['SMTP_USER'] ?? '';
        $this->password = $envValues['SMTP_PASS'] ?? '';
        $this->encryption = $envValues['SMTP_ENCRYPTION'] ?? 'tls';

        if (!$this->useGmailApi) {
            $this->fromEmail = $envValues['SMTP_FROM_EMAIL'] ?? '';
            $this->fromName = $envValues['SMTP_FROM_NAME'] ?? 'Project NEXUS';
        }
    }

    /**
     * Load values from .env file with fallback to environment variables (for Docker)
     */
    private function loadEnvValues()
    {
        $values = [];

        // First try to load from .env file
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Remove surrounding quotes (both single and double)
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    $values[$key] = $value;
                }
            }
        }

        // Fallback to environment variables (Docker/container environments)
        // These take precedence if .env file doesn't exist or doesn't have the key
        $envKeys = [
            'USE_GMAIL_API',
            'GMAIL_CLIENT_ID',
            'GMAIL_CLIENT_SECRET',
            'GMAIL_REFRESH_TOKEN',
            'GMAIL_SENDER_EMAIL',
            'GMAIL_SENDER_NAME',
            'SMTP_HOST',
            'SMTP_PORT',
            'SMTP_USER',
            'SMTP_PASS',
            'SMTP_ENCRYPTION',
            'SMTP_FROM_EMAIL',
            'SMTP_FROM_NAME',
        ];

        foreach ($envKeys as $key) {
            $envValue = getenv($key);
            // Environment variables take precedence over .env file (for Docker)
            if ($envValue !== false) {
                $values[$key] = $envValue;
            }
        }

        return $values;
    }

    public function send($to, $subject, $body, $cc = null, $replyTo = null)
    {
        // Default: USE_GMAIL_API=false (recommended - SMTP is more reliable)
        // Set USE_GMAIL_API=true in .env only if you need Gmail API specifically
        if ($this->useGmailApi) {
            $result = $this->sendViaGmailApi($to, $subject, $body, $cc, $replyTo);
            if ($result) {
                return true;
            }

            // Gmail API failed — fall back to SMTP if credentials are configured
            // Triggers: token refresh failure, rate limit exceeded, circuit breaker open, API errors
            if (!empty($this->host) && !empty($this->username)) {
                error_log("Mailer: Gmail API failed, falling back to SMTP for: $to");
                return $this->sendViaSmtp($to, $subject, $body, $cc, $replyTo);
            }

            error_log("Mailer: Gmail API failed and no SMTP fallback configured. Email not sent to: $to");
            return false;
        }
        return $this->sendViaSmtp($to, $subject, $body, $cc, $replyTo);
    }

    /**
     * Send email via Gmail API using OAuth 2.0
     */
    private function sendViaGmailApi($to, $subject, $body, $cc = null, $replyTo = null)
    {
        try {
            // Get access token (refresh if needed)
            $accessToken = $this->getGmailAccessToken();
            if (!$accessToken) {
                throw new \Exception("Failed to get Gmail API access token");
            }

            // Build RFC 2822 formatted email
            $rawEmail = $this->buildRawEmail($to, $subject, $body, $cc, $replyTo);

            // Base64url encode
            $encodedEmail = $this->base64urlEncode($rawEmail);

            // Send via Gmail API
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
                CURLOPT_TIMEOUT => 10, // 10 second timeout to prevent blocking
                CURLOPT_CONNECTTIMEOUT => 5, // 5 second connection timeout
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }

            if ($httpCode !== 200) {
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
     * Get Gmail API access token, refreshing if necessary
     * Uses Redis for persistent caching with rate limiting and circuit breaker
     */
    private function getGmailAccessToken()
    {
        // Check circuit breaker - if open (too many failures), use SMTP fallback
        $circuitBreakerExpiry = RedisCache::get(self::CACHE_KEY_CIRCUIT_BREAKER, null);
        if ($circuitBreakerExpiry && time() < $circuitBreakerExpiry) {
            $remainingSeconds = $circuitBreakerExpiry - time();
            error_log("Gmail API circuit breaker is open (too many consecutive failures). Blocked for {$remainingSeconds}s more.");
            return null;
        }

        // Try to get valid token from Redis cache
        $cachedToken = RedisCache::get(self::CACHE_KEY_ACCESS_TOKEN, null);
        $cachedExpiry = RedisCache::get(self::CACHE_KEY_TOKEN_EXPIRY, null);

        if ($cachedToken && $cachedExpiry && $cachedExpiry > time()) {
            // Valid cached token exists
            return $cachedToken;
        }

        // Token expired or missing - need to refresh
        // First check rate limiting (max 10 refreshes per hour)
        $refreshAttempts = RedisCache::increment(self::CACHE_KEY_REFRESH_ATTEMPTS, 3600, null); // 1 hour TTL

        if ($refreshAttempts > self::MAX_REFRESH_ATTEMPTS_PER_HOUR) {
            error_log("Gmail API rate limit exceeded: {$refreshAttempts} refresh attempts in the last hour (limit: " . self::MAX_REFRESH_ATTEMPTS_PER_HOUR . ")");
            return null;
        }

        // Validate credentials are present
        if (empty($this->gmailClientId) || empty($this->gmailClientSecret) || empty($this->gmailRefreshToken)) {
            error_log("Gmail API Error: Missing credentials - ClientID: " . (empty($this->gmailClientId) ? 'MISSING' : 'present') .
                      ", ClientSecret: " . (empty($this->gmailClientSecret) ? 'MISSING' : 'present') .
                      ", RefreshToken: " . (empty($this->gmailRefreshToken) ? 'MISSING' : 'present'));
            return null;
        }

        // Attempt token refresh
        $token = $this->refreshGmailToken();

        if ($token) {
            // Success - reset failure count
            RedisCache::delete(self::CACHE_KEY_FAILURE_COUNT, null);
            return $token;
        }

        // Token refresh failed - increment failure counter
        $failureCount = RedisCache::increment(self::CACHE_KEY_FAILURE_COUNT, 3600, null); // 1 hour TTL

        if ($failureCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
            // Too many consecutive failures - open circuit breaker
            $breakerExpiry = time() + self::CIRCUIT_BREAKER_TIMEOUT;
            RedisCache::set(self::CACHE_KEY_CIRCUIT_BREAKER, $breakerExpiry, self::CIRCUIT_BREAKER_TIMEOUT, null);
            error_log("Gmail API circuit breaker opened after {$failureCount} consecutive failures. Disabling Gmail API for " . self::CIRCUIT_BREAKER_TIMEOUT . "s.");
        }

        return null;
    }

    /**
     * Refresh Gmail OAuth2 access token using refresh token
     * Stores result in Redis cache on success
     *
     * @return string|null Access token on success, null on failure
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
            CURLOPT_TIMEOUT => 10, // 10 second timeout
            CURLOPT_CONNECTTIMEOUT => 5, // 5 second connection timeout
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Gmail token refresh cURL error: $curlError");
            return null;
        }

        // Parse response
        $data = json_decode($response, true);

        // Log sanitized response for debugging (no secrets)
        $logData = [
            'http_code' => $httpCode,
            'has_access_token' => isset($data['access_token']),
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null,
            'error' => $data['error'] ?? null,
            'error_description' => $data['error_description'] ?? null,
        ];
        error_log("Gmail token refresh response: " . json_encode($logData));

        if ($httpCode !== 200) {
            error_log("Gmail token refresh failed (HTTP $httpCode): " . ($data['error_description'] ?? $response));
            return null;
        }

        if (!isset($data['access_token'])) {
            error_log("Gmail token refresh response missing access_token field");
            return null;
        }

        // Success - cache the token in Redis
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600;
        $expiryTimestamp = time() + $expiresIn;

        // Store in Redis with 50 minute TTL (safe margin before 60 min expiry)
        RedisCache::set(self::CACHE_KEY_ACCESS_TOKEN, $accessToken, self::TOKEN_TTL, null);
        RedisCache::set(self::CACHE_KEY_TOKEN_EXPIRY, $expiryTimestamp, self::TOKEN_TTL, null);

        error_log("Gmail token refreshed successfully. Expires in {$expiresIn}s. Cached for " . self::TOKEN_TTL . "s.");

        return $accessToken;
    }

    /**
     * Base64url encode (Gmail API requires URL-safe base64)
     */
    private function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Build RFC 2822 formatted raw email with HTML support
     */
    private function buildRawEmail($to, $subject, $body, $cc = null, $replyTo = null)
    {
        $boundary = 'boundary_' . md5(uniqid(time()));

        // Strip HTML for plain text version
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

        $message = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain text part
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($plainText)) . "\r\n";

        // HTML part
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body)) . "\r\n";

        // Close boundary
        $message .= '--' . $boundary . '--';

        return $message;
    }

    /**
     * Send email via SMTP (original method)
     */
    private function sendViaSmtp($to, $subject, $body, $cc = null, $replyTo = null)
    {
        try {
            $this->connect();
            $this->auth();
            $this->sendData($to, $subject, $body, $cc, $replyTo);
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

        // Handle SSL (Port 465 usually)
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

        // Use HTTP_HOST or fallback to a safe default for CLI context
        $ehloHost = $_SERVER['HTTP_HOST'] ?? 'api.project-nexus.ie';

        $this->read(); // Greeting
        $this->write("EHLO " . $ehloHost); // Handshake
        $this->read();

        // Handle TLS (Port 587 usually) via STARTTLS
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

    private function sendData($to, $subject, $body, $cc = null, $replyTo = null)
    {
        $this->write("MAIL FROM: <{$this->fromEmail}>");
        $this->read();
        $this->write("RCPT TO: <$to>");
        $this->read();
        // Add CC recipient to SMTP envelope
        if ($cc) {
            $this->write("RCPT TO: <$cc>");
            $this->read();
        }
        $this->write("DATA");
        $this->read();

        // Headers
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
        $headers .= "Subject: $subject\r\n";

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
     * Test Gmail API connection
     * @return array ['success' => bool, 'message' => string]
     */
    public static function testGmailConnection()
    {
        $mailer = new self();

        if (!$mailer->useGmailApi) {
            return ['success' => false, 'message' => 'Gmail API is not enabled'];
        }

        $token = $mailer->getGmailAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to obtain access token. Check credentials.'];
        }

        // Verify token by getting user profile
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
     * Get current email provider type
     */
    public function getProviderType()
    {
        return $this->useGmailApi ? 'gmail_api' : 'smtp';
    }
}
