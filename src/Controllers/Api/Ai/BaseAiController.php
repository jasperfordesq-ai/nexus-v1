<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers\Api\Ai;

use Nexus\Core\ApiAuth;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Base AI Controller
 *
 * Shared functionality for all AI API controllers.
 */
abstract class BaseAiController
{
    use ApiAuth;

    protected function jsonResponse($data, $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    protected function getUserId(): int
    {
        return $this->requireAuth();
    }

    protected function getInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * Convert technical errors to user-friendly messages
     */
    protected function getFriendlyErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // Rate limit errors
        if (strpos($message, '429') !== false || stripos($message, 'quota') !== false || stripos($message, 'rate') !== false) {
            return "I'm getting a lot of requests right now. Please wait a moment and try again.";
        }

        // API key errors
        if (stripos($message, 'api key') !== false || stripos($message, 'unauthorized') !== false || strpos($message, '401') !== false) {
            return "There's a configuration issue with the AI service. Please contact an administrator.";
        }

        // Model not found
        if (stripos($message, 'not found') !== false && stripos($message, 'model') !== false) {
            return "The AI model is temporarily unavailable. Please try again later.";
        }

        // Network errors
        if (stripos($message, 'curl') !== false || stripos($message, 'connection') !== false || stripos($message, 'timeout') !== false) {
            return "I couldn't connect to the AI service. Please check your internet connection and try again.";
        }

        // Content filter
        if (stripos($message, 'safety') !== false || stripos($message, 'blocked') !== false || stripos($message, 'filter') !== false) {
            return "I couldn't process that request. Please try rephrasing your message.";
        }

        // Token/length errors
        if (stripos($message, 'token') !== false || stripos($message, 'length') !== false || stripos($message, 'too long') !== false) {
            return "Your message is too long. Please try a shorter message.";
        }

        // Server errors
        if (strpos($message, '500') !== false || strpos($message, '502') !== false || strpos($message, '503') !== false) {
            return "The AI service is temporarily down. Please try again in a few minutes.";
        }

        // Generic fallback
        return "Something went wrong. Please try again. If the problem persists, contact support.";
    }

    /**
     * Get user profile context for personalized generation
     */
    protected function getUserProfileContext(int $userId): string
    {
        $context = '';

        try {
            $db = Database::getConnection();

            // Get user info
            $stmt = $db->prepare("SELECT name, bio, location FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                if (!empty($user['name'])) {
                    $context .= "- Name: {$user['name']}\n";
                }
                if (!empty($user['location'])) {
                    $context .= "- Location: {$user['location']}\n";
                }
                if (!empty($user['bio'])) {
                    // Truncate long bios
                    $bio = strlen($user['bio']) > 200 ? substr($user['bio'], 0, 200) . '...' : $user['bio'];
                    $context .= "- Bio: {$bio}\n";
                }
            }

            // Get existing listings to understand their style
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("SELECT title, type FROM listings WHERE user_id = ? AND tenant_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId, $tenantId]);
            $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($listings)) {
                $listingTitles = array_map(fn($l) => "[{$l['type']}] {$l['title']}", $listings);
                $context .= "- Other listings: " . implode(', ', $listingTitles) . "\n";
            }

        } catch (\Exception $e) {
            // Silently fail - context is optional
        }

        return $context;
    }
}
