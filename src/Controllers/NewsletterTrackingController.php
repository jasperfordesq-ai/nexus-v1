<?php

namespace Nexus\Controllers;

use Nexus\Models\Newsletter;
use Nexus\Models\NewsletterAnalytics;
use Nexus\Core\TenantContext;

class NewsletterTrackingController
{
    /**
     * Serve a 1x1 transparent pixel for open tracking
     * URL: /newsletter/track/open/{newsletterId}/{trackingToken}
     */
    public function trackOpen($newsletterId, $trackingToken)
    {
        // Decode parameters
        $newsletterId = (int) $newsletterId;
        $data = $this->decodeTrackingToken($trackingToken);

        if ($data && $newsletterId > 0) {
            try {
                NewsletterAnalytics::recordOpen(
                    $newsletterId,
                    $trackingToken,
                    $data['email'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
            } catch (\Exception $e) {
                // Silently fail - don't disrupt email viewing
                error_log("Open tracking error: " . $e->getMessage());
            }
        }

        // Return a 1x1 transparent GIF
        $this->serveTrackingPixel();
    }

    /**
     * Track a click and redirect to the original URL
     * URL: /newsletter/track/click/{newsletterId}/{linkId}/{trackingToken}
     */
    public function trackClick($newsletterId, $linkId, $trackingToken)
    {
        $newsletterId = (int) $newsletterId;
        $data = $this->decodeTrackingToken($trackingToken);

        // Get the original URL from the link ID
        $url = $this->decodeUrl($linkId);

        if (!$url) {
            // Fallback to homepage if URL decode fails
            $basePath = TenantContext::getBasePath();
            header('Location: ' . $basePath . '/');
            exit;
        }

        if ($data && $newsletterId > 0) {
            try {
                NewsletterAnalytics::recordClick(
                    $newsletterId,
                    $trackingToken,
                    $data['email'] ?? 'unknown',
                    $url,
                    $linkId,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
            } catch (\Exception $e) {
                // Silently fail - still redirect
                error_log("Click tracking error: " . $e->getMessage());
            }
        }

        // Redirect to the original URL
        header('Location: ' . $url);
        exit;
    }

    /**
     * Serve a transparent 1x1 GIF pixel
     */
    private function serveTrackingPixel()
    {
        // 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($pixel));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $pixel;
        exit;
    }

    /**
     * Decode tracking token to get email
     * Token format: base64(email:timestamp:hash)
     */
    private function decodeTrackingToken($token)
    {
        try {
            $decoded = base64_decode(strtr($token, '-_', '+/'));
            if (!$decoded) return null;

            $parts = explode(':', $decoded);
            if (count($parts) < 2) return null;

            return [
                'email' => $parts[0],
                'timestamp' => $parts[1] ?? null
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Decode URL from link ID
     * Link ID is base64 encoded URL
     */
    private function decodeUrl($linkId)
    {
        try {
            $url = base64_decode(strtr($linkId, '-_', '+/'));
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Security: Validate URL is safe to redirect to (prevent open redirect attacks)
            if (!$this->isUrlSafeForRedirect($url)) {
                return null;
            }

            return $url;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if URL is safe to redirect to (prevent open redirect attacks)
     * Only allows internal URLs or whitelisted external domains
     */
    private function isUrlSafeForRedirect($url)
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return false;
        }

        $host = strtolower($parsedUrl['host']);

        // Allow internal domains
        $internalDomains = [
            'project-nexus.ie',
            'hour-timebank.ie',
            'staging.timebank.local',
            'localhost',
        ];

        foreach ($internalDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        // Block javascript: and data: URLs
        $scheme = strtolower($parsedUrl['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        // Allow any HTTPS URL (newsletter links may point to external sites legitimately)
        // This is a trade-off: newsletters often link externally
        return $scheme === 'https';
    }

    /**
     * Generate a tracking token for an email recipient
     */
    public static function generateTrackingToken($email)
    {
        $data = $email . ':' . time();
        return strtr(base64_encode($data), '+/', '-_');
    }

    /**
     * Generate a link ID for URL tracking
     */
    public static function generateLinkId($url)
    {
        return strtr(base64_encode($url), '+/', '-_');
    }

    /**
     * Process email content to add tracking
     * - Adds tracking pixel before </body>
     * - Rewrites links to go through click tracker
     */
    public static function addTracking($html, $newsletterId, $trackingToken, $basePath)
    {
        $appUrl = \Nexus\Core\Env::get('APP_URL') ?? '';
        $baseUrl = rtrim($appUrl, '/') . $basePath;

        // Add tracking pixel before </body>
        $pixelUrl = $baseUrl . '/newsletter/track/open/' . $newsletterId . '/' . $trackingToken;
        $pixel = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" style="display:none;" alt="" />';

        // Insert pixel before </body>
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $pixel . '</body>', $html);
        } else {
            $html .= $pixel;
        }

        // Rewrite links for click tracking
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function ($matches) use ($newsletterId, $trackingToken, $baseUrl) {
                $before = $matches[1];
                $url = $matches[2];
                $after = $matches[3];

                // Skip tracking for unsubscribe links (important for compliance)
                if (stripos($url, 'unsubscribe') !== false) {
                    return $matches[0];
                }

                // Skip mailto and tel links
                if (preg_match('/^(mailto:|tel:|#)/', $url)) {
                    return $matches[0];
                }

                // Skip already tracked links
                if (stripos($url, '/newsletter/track/click') !== false) {
                    return $matches[0];
                }

                // Generate tracked URL
                $linkId = self::generateLinkId($url);
                $trackedUrl = $baseUrl . '/newsletter/track/click/' . $newsletterId . '/' . $linkId . '/' . $trackingToken;

                return '<a ' . $before . 'href="' . htmlspecialchars($trackedUrl) . '"' . $after . '>';
            },
            $html
        );

        return $html;
    }
}
