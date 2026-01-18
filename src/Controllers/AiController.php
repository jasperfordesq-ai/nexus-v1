<?php

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;
use Nexus\Models\AiSettings;
use Nexus\Models\AiConversation;
use Nexus\Models\AiUsage;
use Nexus\Models\AiUserLimit;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI Controller
 *
 * Handles web routes for AI features.
 */
class AiController
{
    /**
     * GET /ai
     * Main AI page
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Check if AI is enabled
        $aiEnabled = AIServiceFactory::isEnabled();

        // Get available providers - wrap in try/catch to prevent 500 errors
        try {
            $providers = AIServiceFactory::getAvailableProviders();
        } catch (\Exception $e) {
            error_log("AiController index() - Error loading providers: " . $e->getMessage());
            $providers = []; // Fallback to empty array
        }

        $defaultProvider = AIServiceFactory::getDefaultProvider();
        $limits = AiUserLimit::canMakeRequest($userId);

        // Get recent conversations
        $conversations = AiConversation::getByUserId($userId, 20);

        // Get customizable welcome message
        $welcomeMessage = AiSettings::get($tenantId, 'ai_welcome_message') ?:
            "Hello! I am your new Platform Assistant. 🧠\n\nI am currently in Learning Mode and digesting the database of Members and Listings. Please bear with me while I learn the ropes—I will do my best to connect you with the right offers!";

        \Nexus\Core\View::render('ai/index', [
            'aiEnabled' => $aiEnabled,
            'providers' => $providers,
            'defaultProvider' => $defaultProvider,
            'limits' => $limits,
            'conversations' => $conversations,
            'conversation' => null,
            'messages' => [],
            'welcomeMessage' => $welcomeMessage
        ]);
    }

    /**
     * GET /ai/chat/:id
     * Specific chat conversation
     */
    public function chat($conversationId = null)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $conversation = null;
        $messages = [];

        if ($conversationId) {
            $conversation = AiConversation::getWithMessages((int) $conversationId);
            if (!$conversation || !AiConversation::belongsToUser((int) $conversationId, $userId)) {
                header('Location: ' . TenantContext::getBasePath() . '/ai');
                exit;
            }
            $messages = $conversation['messages'] ?? [];
        }

        $aiEnabled = AIServiceFactory::isEnabled();

        // Get available providers - wrap in try/catch to prevent 500 errors
        try {
            $providers = AIServiceFactory::getAvailableProviders();
        } catch (\Exception $e) {
            error_log("AiController chat() - Error loading providers: " . $e->getMessage());
            $providers = []; // Fallback to empty array
        }

        $limits = AiUserLimit::canMakeRequest($userId);
        $conversations = AiConversation::getByUserId($userId, 20);

        // Get customizable welcome message
        $welcomeMessage = AiSettings::get($tenantId, 'ai_welcome_message') ?:
            "Hello! I am your new Platform Assistant. 🧠\n\nI am currently in Learning Mode and digesting the database of Members and Listings. Please bear with me while I learn the ropes—I will do my best to connect you with the right offers!";

        \Nexus\Core\View::render('ai/index', [
            'aiEnabled' => $aiEnabled,
            'providers' => $providers,
            'defaultProvider' => null,
            'limits' => $limits,
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'welcomeMessage' => $welcomeMessage
        ]);
    }
}
