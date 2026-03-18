<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Centralized, secure client IP extraction.
 *
 * Handles the full proxy chain: Client → Cloudflare → Docker → Apache → PHP.
 *
 * Priority order:
 *   1. CF-Connecting-IP  (Cloudflare's verified real client IP)
 *   2. X-Forwarded-For   (left-most IP after stripping trusted proxies)
 *   3. X-Real-IP         (single-IP header from reverse proxies)
 *   4. REMOTE_ADDR       (fallback — correct if mod_remoteip is active)
 *
 * Security: We ONLY trust forwarded headers when REMOTE_ADDR is a known
 * trusted proxy (Docker gateway, localhost, or Cloudflare IP ranges).
 * This prevents spoofing from direct public connections.
 */
/**
 *  Use AppCoreClientIp instead. This class is maintained for backward compatibility only.
 */
/**
 * @deprecated Use AppCoreClientIp instead. Maintained for backward compatibility.
 */
class ClientIp
{
    /**
     * Trusted proxy CIDRs.
     * These are the only REMOTE_ADDR values that are allowed to set forwarded headers.
     */
    private const TRUSTED_PROXIES = [
        // Docker bridge networks (default and custom)
        '172.16.0.0/12',
        '10.0.0.0/8',
        '192.168.0.0/16',
        // Localhost
        '127.0.0.0/8',
        '::1',
        // Cloudflare IPv4 ranges (https://www.cloudflare.com/ips-v4/)
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // Cloudflare IPv6 ranges (https://www.cloudflare.com/ips-v6/)
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /** Cached result for the current request */
    private static ?string $cachedIp = null;

    /**
     * Get the real client IP address.
     *
     * Safe to call from anywhere — result is cached per-request.
     */
    public static function get(): string
    {
        if (self::$cachedIp !== null) {
            return self::$cachedIp;
        }

        self::$cachedIp = self::resolve();
        return self::$cachedIp;
    }

    /**
     * Clear the cached IP (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cachedIp = null;
    }

    /**
     * Resolve the real client IP from request headers.
     */
    private static function resolve(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // If REMOTE_ADDR is NOT a trusted proxy, it IS the real client.
        // Do NOT trust any forwarded headers — they could be spoofed.
        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        // REMOTE_ADDR is a trusted proxy — safe to inspect forwarded headers.

        // 1. CF-Connecting-IP: Cloudflare sets this to the true client IP.
        //    This is the most reliable header when behind Cloudflare.
        $cfIp = self::getHeader('HTTP_CF_CONNECTING_IP');
        if ($cfIp !== null && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        // 2. X-Forwarded-For: comma-separated list, left-most is the client.
        //    Format: "client, proxy1, proxy2"
        $xff = self::getHeader('HTTP_X_FORWARDED_FOR');
        if ($xff !== null) {
            $ips = array_map('trim', explode(',', $xff));
            // Walk from right to left, skipping trusted proxies.
            // The first non-trusted IP is the real client.
            for ($i = count($ips) - 1; $i >= 0; $i--) {
                $ip = $ips[$i];
                if (filter_var($ip, FILTER_VALIDATE_IP) && !self::isTrustedProxy($ip)) {
                    return $ip;
                }
            }
            // If all IPs in XFF are trusted (shouldn't happen), use left-most
            $leftMost = trim($ips[0]);
            if (filter_var($leftMost, FILTER_VALIDATE_IP)) {
                return $leftMost;
            }
        }

        // 3. X-Real-IP: single IP, set by some reverse proxies (nginx, etc.)
        $realIp = self::getHeader('HTTP_X_REAL_IP');
        if ($realIp !== null && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        // 4. Fallback to REMOTE_ADDR (will be Docker gateway IP, but better than null)
        return $remoteAddr;
    }

    /**
     * Check if an IP is within our trusted proxy ranges.
     */
    private static function isTrustedProxy(string $ip): bool
    {
        foreach (self::TRUSTED_PROXIES as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an IP address falls within a CIDR range.
     * Supports both IPv4 and IPv6.
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        // Handle single IP (no /) — exact match
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Must be same address family (IPv4 vs IPv6)
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        // Build bitmask
        $byteLen = strlen($ipBin);
        $mask = str_repeat("\xff", (int) ($bits / 8));
        if ($bits % 8 !== 0) {
            $mask .= chr(0xff << (8 - ($bits % 8)) & 0xff);
        }
        $mask = str_pad($mask, $byteLen, "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }

    /**
     * Get a $_SERVER header value, trimmed.
     */
    private static function getHeader(string $key): ?string
    {
        $value = $_SERVER[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return trim($value);
    }

    /**
     * Get all IP-related debug information for the current request.
     * Only call this from admin/debug endpoints — never expose to public.
     */
    public static function debug(): array
    {
        return [
            'resolved_ip' => self::get(),
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_CF_CONNECTING_IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
            'HTTP_CF_RAY' => $_SERVER['HTTP_CF_RAY'] ?? null,
            'HTTP_CF_IPCOUNTRY' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
            'remote_addr_is_trusted' => self::isTrustedProxy($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'mod_remoteip_active' => isset($_SERVER['REMOTE_ADDR']) && !self::isTrustedProxy($_SERVER['REMOTE_ADDR']),
        ];
    }
}
