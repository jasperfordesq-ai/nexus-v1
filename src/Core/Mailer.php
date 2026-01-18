<?php

namespace Nexus\Core;

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
    private $gmailAccessToken;
    private $gmailTokenExpiry;

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
        } else {
            // SMTP credentials
            $this->host = $envValues['SMTP_HOST'] ?? '';
            $this->port = $envValues['SMTP_PORT'] ?? 587;
            $this->username = $envValues['SMTP_USER'] ?? '';
            $this->password = $envValues['SMTP_PASS'] ?? '';
            $this->encryption = $envValues['SMTP_ENCRYPTION'] ?? 'tls';
            $this->fromEmail = $envValues['SMTP_FROM_EMAIL'] ?? '';
            $this->fromName = $envValues['SMTP_FROM_NAME'] ?? 'Project NEXUS';
        }
    }

    /**
     * Load values directly from .env file (fallback when $_ENV not populated)
     */
    private function loadEnvValues()
    {
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) {
            return [];
        }

        $values = [];
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

        return $values;
    }

    public function send($to, $subject, $body, $cc = null, $replyTo = null)
    {
        if ($this->useGmailApi) {
            return $this->sendViaGmailApi($to, $subject, $body, $cc, $replyTo);
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
                CURLOPT_SSL_VERIFYPEER => true
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
     */
    private function getGmailAccessToken()
    {
        // Check if we have a valid cached token
        if ($this->gmailAccessToken && $this->gmailTokenExpiry > time()) {
            return $this->gmailAccessToken;
        }

        // Validate credentials are present
        if (empty($this->gmailClientId) || empty($this->gmailClientSecret) || empty($this->gmailRefreshToken)) {
            error_log("Gmail API Error: Missing credentials - ClientID: " . (empty($this->gmailClientId) ? 'MISSING' : 'present') .
                      ", ClientSecret: " . (empty($this->gmailClientSecret) ? 'MISSING' : 'present') .
                      ", RefreshToken: " . (empty($this->gmailRefreshToken) ? 'MISSING' : 'present'));
            return null;
        }

        // Refresh the token
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
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Gmail token refresh cURL error: $curlError");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Gmail token refresh failed (HTTP $httpCode): $response");
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $this->gmailAccessToken = $data['access_token'];
            // Token expires in ~3600 seconds, cache for 3500 to be safe
            $this->gmailTokenExpiry = time() + ($data['expires_in'] ?? 3600) - 100;
            return $this->gmailAccessToken;
        }

        return null;
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

        $this->socket = fsockopen($host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new \Exception("Could not connect to SMTP host: $errstr ($errno)");
        }
        $this->read(); // Greeting
        $this->write("EHLO " . $_SERVER['HTTP_HOST']); // Handshake
        $this->read();

        // Handle TLS (Port 587 usually) via STARTTLS
        if ($this->encryption === 'tls') {
            $this->write("STARTTLS");
            $this->read();
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->write("EHLO " . $_SERVER['HTTP_HOST']);
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
